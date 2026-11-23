// Enrollment at the login (enroll_via_multichallenge) against a real privacyIDEA.
// carol has no token. The policy passOnNoToken lets her pass the first step with any value, and then privacyIDEA
// asks her to enroll a token. The policy is only for carol and only during a test.
import { test, expect } from "@playwright/test";
import { totp } from "../../../dev/lib/totp.mjs";
import { adminPage, loginPassword, resetAppConfig } from "../helpers.mjs";
import { addAuthenticator } from "../passkey-helpers.mjs";
import {
  deletePolicy, hexSecretFromLink, piContainers, piTokens, registerContainer, removeContainers, removeTokens, setPolicy,
} from "../enroll-helpers.mjs";

const POLICY = "e2e_enroll_carol";
const CONTAINER_POLICY = "e2e_container_carol";

let admin;
test.beforeAll(async ({ browser }) => {
  admin = await adminPage(browser);
});
test.beforeEach(async () => {
  await resetAppConfig(admin);
  await removeTokens("carol");
  await removeContainers("carol");
});
test.afterEach(async () => {
  await deletePolicy(POLICY);
  await deletePolicy(CONTAINER_POLICY);
  await removeTokens("carol");
  await removeContainers("carol");
});

/** privacyIDEA lets carol pass and then asks her to enroll a token of this type. */
async function enrollmentPolicy(type, optional = false) {
  await setPolicy(POLICY, {
    scope: "authentication",
    action: `passOnNoToken, enroll_via_multichallenge=${type}${optional ? ", enroll_via_multichallenge_optional" : ""}`,
    user: "carol",
    realm: "dev",
    active: true,
  });
}

/** The registration of a container needs the URL that the app contacts. The test registers through this URL. */
async function containerPolicy() {
  await setPolicy(CONTAINER_POLICY, {
    scope: "container",
    action: "privacyIDEA_server_url=http://localhost:8081/",
    user: "carol",
    realm: "dev",
    active: true,
  });
}

/** The password, and then any value for the first step of privacyIDEA (passOnNoToken). */
async function startLogin(page) {
  await loginPassword(page, "carol");
  await expect(page.locator("#otp")).toBeVisible();
  await page.fill("#otp", "anything");
  await page.click("#submitButton");
}

test("N1: a user without a token enrolls a TOTP token at the login", async ({ page }) => {
  await enrollmentPolicy("totp");
  await startLogin(page);

  await expect(page.locator("#enrollImage")).toBeVisible();
  const link = await page.locator("#enrollLink").getAttribute("href");
  expect(link).toMatch(/^otpauth:\/\/totp\//);
  await expect(page.locator("#cancelEnrollmentButton")).toHaveCount(0);
  await expect(page.locator("#passkeyButton"), "No passkey login during an enrollment.").toBeHidden();

  // The authenticator app would scan the QR code. The test reads the seed from the link.
  await page.fill("#otp", totp(hexSecretFromLink(link)));
  await page.click("#submitButton");
  await page.waitForURL(/\/apps\/files/);
  expect((await piTokens("carol")).map((t) => t.tokentype)).toEqual(["totp"]);
});

test("N2: the user skips an optional enrollment", async ({ page }) => {
  await enrollmentPolicy("totp", true);
  await startLogin(page);

  await expect(page.locator("#enrollImage")).toBeVisible();
  await page.click("#cancelEnrollmentButton");
  await page.waitForURL(/\/apps\/files/);
  expect(await piTokens("carol"), "privacyIDEA deleted the new token.").toEqual([]);
});

test("N3: a user without a token registers a passkey at the login", async ({ browser }) => {
  await enrollmentPolicy("passkey");
  const page = await (await browser.newContext()).newPage();
  await addAuthenticator(page);
  await startLogin(page);

  // The page starts the registration by itself. The virtual authenticator answers.
  await page.waitForURL(/\/apps\/files/);
  const tokens = await piTokens("carol");
  expect(tokens.map((t) => t.tokentype)).toEqual(["passkey"]);
  expect(tokens[0].rollout_state, "The registration is complete.").not.toBe("clientwait");
});

test("N4: without an authenticator, the user skips an optional passkey registration", async ({ page }) => {
  await enrollmentPolicy("passkey", true);
  // No virtual authenticator: Chromium refuses navigator.credentials.create(). The page stays.
  await startLogin(page);

  await expect(page.locator("#passkeyRegistrationButton")).toBeVisible();
  await expect(page.locator("#otp")).toBeHidden();
  await page.click("#cancelEnrollmentButton");
  await page.waitForURL(/\/apps\/files/);
  expect(await piTokens("carol")).toEqual([]);
});

test("N5: a user without a token registers a smartphone container at the login", async ({ page }) => {
  await enrollmentPolicy("smartphone");
  await containerPolicy();
  await startLogin(page);

  await expect(page.locator("#enrollImage")).toBeVisible();
  const link = await page.locator("#enrollLink").getAttribute("href");
  expect(link).toMatch(/^pia:\/\/container\//);

  // The app would scan the QR code. The test registers the container with the data of the link.
  await registerContainer(link);
  // The page polls privacyIDEA and continues the login after the registration.
  await page.waitForURL(/\/apps\/files/, { timeout: 60000 });
  expect((await piContainers("carol")).map((c) => c.type)).toEqual(["smartphone"]);
});

test("N6: the user skips an optional container registration", async ({ page }) => {
  await enrollmentPolicy("smartphone", true);
  await containerPolicy();
  await startLogin(page);

  await expect(page.locator("#enrollImage")).toBeVisible();
  await page.click("#cancelEnrollmentButton");
  await page.waitForURL(/\/apps\/files/);
  expect(await piContainers("carol"), "privacyIDEA deleted the new container.").toEqual([]);
});
