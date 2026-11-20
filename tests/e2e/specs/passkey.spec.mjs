// Passkey login, checked with a virtual authenticator in a real browser against a real privacyIDEA.
// The setup script writes the policies that the tests need (relying party id "localhost" and passkey_trigger_by_pin).
// Every test enrolls its own passkeys, because a virtual authenticator forgets its credentials when the page closes.
import { test, expect } from "@playwright/test";
import { adminPage, freshOtp, loginPassword, piAudit, resetAppConfig, setAppConfig } from "../helpers.mjs";
import { addAuthenticator, enrollPasskey, removePasskeys } from "../passkey-helpers.mjs";

// The challenge page of the app. Its query string can name /apps/files, so the tests check the path.
const CHALLENGE_PAGE = /\/login\/challenge\/privacyidea/;

let admin;
test.beforeAll(async ({ browser }) => {
  admin = await adminPage(browser);
});

// The users that get passkeys in these tests. The tests remove the tokens before and after each test.
const PASSKEY_USERS = ["erin", "frank"];
const removeAllPasskeys = async () => {
  for (const user of PASSKEY_USERS) await removePasskeys(user);
};

test.beforeEach(async () => {
  await resetAppConfig(admin);
  // privacyIDEA sends a passkey challenge only to a client that asks for it. The app asks with triggerchallenge.
  await setAppConfig(admin, "triggerchallenges", "1");
  await removeAllPasskeys();
});

test.afterAll(async () => {
  await resetAppConfig(admin);
  await removeAllPasskeys();
});

/** A page with a virtual authenticator that holds a passkey of the given users. */
async function pageWithPasskeys(browser, users) {
  const page = await (await browser.newContext()).newPage();
  const authenticator = await addAuthenticator(page);
  await page.goto("/index.php/login"); // the registration needs the origin of ownCloud
  const credentialIds = {};
  for (const user of users) credentialIds[user] = await enrollPasskey(page, user);
  return { page, authenticator, credentialIds };
}

/** Count the calls of navigator.credentials.get in the page, over reloads (sessionStorage survives them). */
async function countPasskeyPrompts(page) {
  await page.addInitScript(() => {
    const original = navigator.credentials.get.bind(navigator.credentials);
    navigator.credentials.get = (...args) => {
      sessionStorage.setItem("piPrompts", String(Number(sessionStorage.getItem("piPrompts") || 0) + 1));
      return original(...args);
    };
  });
}
const promptCount = async (page) => Number(await page.evaluate(() => sessionStorage.getItem("piPrompts") || 0));

test("P1: erin logs in with a passkey after the password", async ({ browser }) => {
  const { page } = await pageWithPasskeys(browser, ["erin"]);
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await loginPassword(page, "erin");
  // The page asks for the passkey by itself. The virtual authenticator answers.
  await page.waitForURL(/\/apps\/files/);
  expect(errors).toEqual([]);
  // The answer has its own header (Origin). It must still carry the user agent of the app.
  const [answer] = await piAudit("*validate/check*");
  expect(answer.user).toBe("erin");
  expect(answer.user_agent).toBe("ownCloud");
});

test("P2: without a usable passkey the page falls back and the passkey button tries again", async ({ browser }) => {
  const { page, authenticator, credentialIds } = await pageWithPasskeys(browser, ["erin"]);
  const [credential] = await authenticator.credentials();
  await authenticator.removeCredential(credentialIds.erin); // the user has no passkey on this device

  await loginPassword(page, "erin");
  // The browser refuses. The page goes back to the OTP mode and does not ask again by itself.
  await expect(page.locator("#passkeyButton")).toBeVisible();
  await expect(page).toHaveURL(CHALLENGE_PAGE);
  await page.waitForTimeout(1500);
  await expect(page.locator("#passkeyButton")).toBeVisible();

  await authenticator.addCredential(credential); // the user connects the device
  await page.click("#passkeyButton");
  await page.waitForURL(/\/apps\/files/);
});

test("P3: the passkey of another user does not pass the challenge of erin", async ({ browser }) => {
  // The device of the attacker holds only the passkey of frank. The attacker knows the password of erin.
  const { page, authenticator, credentialIds } = await pageWithPasskeys(browser, ["erin", "frank"]);
  await authenticator.removeCredential(credentialIds.erin);
  await countPasskeyPrompts(page);

  await loginPassword(page, "erin");
  // The browser signs the challenge of erin with the passkey of frank. privacyIDEA refuses the answer.
  await expect(page.locator("#passkeyButton")).toBeVisible();
  await expect(page.locator("span.warning")).toBeVisible();
  await page.waitForTimeout(1500);
  await expect(page).toHaveURL(CHALLENGE_PAGE);
  // One prompt only. A refused answer must not start the prompt again and again.
  expect(await promptCount(page)).toBe(1);
  // ownCloud still wants the second factor of erin.
  await page.goto("/index.php/apps/files/");
  await expect(page).toHaveURL(CHALLENGE_PAGE);
  await expect(page.locator("#passkeyButton")).toBeVisible();
});

test("P4: with the passkey button off, a user without a passkey sees the OTP field and no passkey button", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "0");
  await setAppConfig(admin, "passkeybutton", "0");
  const [before] = await piAudit("*validate/initialize*", 1);
  await loginPassword(page, "alice");
  await expect(page.locator("#otp")).toBeVisible();
  await expect(page.locator("#passkeyButton")).toBeHidden();
  // The app did not ask privacyIDEA for a challenge.
  const [after] = await piAudit("*validate/initialize*", 1);
  expect(after?.number).toBe(before?.number);
});

// ---- The passkey button without a triggered challenge. The app asks privacyIDEA for a challenge with /validate/initialize.

test("P6: without a triggered challenge, the passkey button logs in", async ({ browser }) => {
  await setAppConfig(admin, "triggerchallenges", "0");
  const { page } = await pageWithPasskeys(browser, ["erin"]);
  await countPasskeyPrompts(page);

  await loginPassword(page, "erin");
  // privacyIDEA sent no challenge, so the page waits for the user. It shows the OTP field and the button.
  await expect(page).toHaveURL(CHALLENGE_PAGE);
  await expect(page.locator("#otp")).toBeVisible();
  await expect(page.locator("#passkeyButton")).toBeVisible();
  await page.waitForTimeout(1000);
  expect(await promptCount(page)).toBe(0);

  await page.click("#passkeyButton");
  await page.waitForURL(/\/apps\/files/);
  expect(await promptCount(page)).toBe(1);
  // The request for the challenge and the answer carry the user agent of the app. The answer has the user.
  const [init] = await piAudit("*validate/initialize*", 1);
  expect(init.user_agent).toBe("ownCloud");
  const [answer] = await piAudit("*validate/check*", 1);
  expect(answer.user).toBe("erin");
  expect(answer.user_agent).toBe("ownCloud");
});

test("P7: the passkey of another user does not pass the passkey button of erin", async ({ browser }) => {
  await setAppConfig(admin, "triggerchallenges", "0");
  // The device of the attacker holds only the passkey of frank. The attacker knows the password of erin.
  // The challenge of the button belongs to no token, so only the user name in the answer stops this.
  const { page, authenticator, credentialIds } = await pageWithPasskeys(browser, ["erin", "frank"]);
  await authenticator.removeCredential(credentialIds.erin);
  await countPasskeyPrompts(page);
  const [lastChallenge] = await piAudit("*validate/initialize*", 1);

  await loginPassword(page, "erin");
  await expect(page.locator("#passkeyButton")).toBeVisible();
  await page.click("#passkeyButton");
  await expect(page.locator("span.warning")).toBeVisible();
  await page.waitForTimeout(1500);
  await expect(page).toHaveURL(CHALLENGE_PAGE);
  expect(await promptCount(page)).toBe(1);
  await page.goto("/index.php/apps/files/");
  await expect(page).toHaveURL(CHALLENGE_PAGE);
  // The next try uses a new challenge, because the first one was for one answer only.
  // Two requests in this test: one for the first page and one for the page after the refused answer.
  await expect(page.locator("#passkeyButton")).toBeVisible();
  const challenges = (await piAudit("*validate/initialize*", 10)).filter((e) => e.number > (lastChallenge?.number ?? 0));
  expect(challenges).toHaveLength(2);
});

test("P8: a user without a passkey can still use the OTP after the button", async ({ browser }) => {
  await setAppConfig(admin, "triggerchallenges", "0");
  const { page } = await pageWithPasskeys(browser, []); // a device without any passkey
  await countPasskeyPrompts(page);

  await loginPassword(page, "alice");
  await expect(page.locator("#passkeyButton")).toBeVisible();
  await page.click("#passkeyButton");
  // The browser has no passkey. The page goes back to the OTP mode and does not ask again by itself.
  await expect(page.locator("#otp")).toBeVisible();
  await page.waitForTimeout(1500);
  expect(await promptCount(page)).toBe(1);
  await expect(page).toHaveURL(CHALLENGE_PAGE);

  await page.fill("#otp", await freshOtp("alice"));
  await page.click("#submitButton");
  await page.waitForURL(/\/apps\/files/);
});

test("P9: the passkey checkbox of the admin page is on by default and keeps its state", async () => {
  await admin.goto("/index.php/settings/admin?sectionid=additional");
  await expect(admin.locator("#passkeybutton")).toBeChecked();

  const toggle = async () => {
    // The label toggles the box. The box of ownCloud is not clickable itself.
    await Promise.all([
      admin.waitForResponse((r) => r.url().includes("/setValue") && r.status() === 200),
      admin.locator("label[for=passkeybutton]").click(),
    ]);
  };
  await toggle();
  await admin.reload();
  await expect(admin.locator("#passkeybutton")).not.toBeChecked();
  await toggle();
  await admin.reload();
  await expect(admin.locator("#passkeybutton")).toBeChecked();
});
