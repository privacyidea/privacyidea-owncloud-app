// Behavior of the app, checked in a real browser.
// Each test states the CORRECT behavior. The ids (B1, C4, S1, ...) refer to the findings in the changelog
// and in journal.md. If a defect is known but not fixed yet, declare its test with test.fail():
// it passes while the defect exists and reports "expected to fail" as soon as someone fixes it.
import { test, expect } from "@playwright/test";
import { PRIVACYIDEA_URL, adminPage, freshOtp, loginPassword, piAudit, resetAppConfig, setAppConfig } from "../helpers.mjs";

let admin;
test.beforeAll(async ({ browser }) => {
  admin = await adminPage(browser);
});

test.beforeEach(async () => {
  await resetAppConfig(admin);
});
test.afterAll(async () => {
  await resetAppConfig(admin);
});

const bodyText = async (page) => (await page.locator("body").innerText()).replace(/\s+/g, " ");

test("A: the admin settings page renders and loads the saved values", async () => {
  await admin.goto("/index.php/settings/admin?sectionid=additional");
  await expect(admin.locator("#piSettings")).toBeVisible();
  await expect(admin.locator("#piurl")).toHaveValue("http://privacyidea:8080");
});

test("T: the test buttons of the admin page report success and failure", async () => {
  await admin.goto("/index.php/apps/files/");
  const call = (route, user, pass) =>
    admin.evaluate(
      async ([r, u, p]) => {
        const res = await fetch(OC.generateUrl("/apps/twofactor_privacyidea/" + r), {
          method: "POST",
          headers: { requesttoken: OC.requestToken, "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ user: u, pass: p }),
        });
        return res.json();
      },
      [route, user, pass],
    );
  expect((await call("testServiceAccount", "admin", "admin")).status).toBe("success");
  const badService = await call("testServiceAccount", "admin", "wrong-password");
  expect(badService.status).not.toBe("success");
  expect(JSON.stringify(badService)).not.toContain("privacyidea:8080");
  expect((await call("testAuthentication", "alice", await freshOtp("alice"))).status).toBe("success");
  expect((await call("testAuthentication", "alice", "000000")).status).toBe("error");
});

test("B1: the poll-in-browser checkbox shows the saved state", async () => {
  await setAppConfig(admin, "pollInBrowser", "1");
  await admin.goto("/index.php/settings/admin?sectionid=additional");
  await expect(admin.locator("#pollInBrowser")).toBeVisible();
  await expect(admin.locator("#pollInBrowser")).toBeChecked();
  await setAppConfig(admin, "pollInBrowser", "0");
  await admin.reload();
  await expect(admin.locator("#pollInBrowser")).not.toBeChecked();
});

test("C4: poll in browser starts a worker that asks privacyIDEA", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "1");
  await setAppConfig(admin, "pollInBrowser", "1");
  await setAppConfig(admin, "pollInBrowserUrl", PRIVACYIDEA_URL);
  const polled = [];
  const failed = [];
  page.context().on("request", (r) => r.url().includes("/validate/polltransaction") && polled.push(r.url()));
  page.context().on("requestfailed", (r) => failed.push(`${r.url()} ${r.failure()?.errorText}`));
  const started = page.waitForEvent("worker", { timeout: 15000 });
  await loginPassword(page, "bob");
  const worker = await started;
  expect(worker.url()).toMatch(/\/twofactor_privacyidea\/js\/pi-poll-transaction\.worker\.js$/);
  await expect.poll(() => polled.length, { timeout: 10000 }).toBeGreaterThan(0);
  console.log("C4 worker:", worker.url(), "| poll request:", polled[0], "| failed requests:", JSON.stringify(failed));
  expect(failed.filter((f) => f.includes("BLOCKED_BY_CSP"))).toEqual([]);
});

test("B2: poll in browser does not start when the option is off", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "1");
  await setAppConfig(admin, "pollInBrowser", "0");
  await setAppConfig(admin, "pollInBrowserUrl", PRIVACYIDEA_URL);
  await loginPassword(page, "bob");
  await expect(page.locator("#otp")).toBeVisible();
  await page.waitForTimeout(2500);
  expect(page.workers()).toHaveLength(0);
});

test("S1: X-Forwarded-For cannot skip 2FA for an excluded IP", async ({ browser }) => {
  await setAppConfig(admin, "piexcludeips", "10.9.9.9");
  const context = await browser.newContext({ extraHTTPHeaders: { "X-Forwarded-For": "10.9.9.9" } });
  const page = await context.newPage();
  await loginPassword(page, "alice");
  await page.waitForLoadState("networkidle");
  await expect(page.locator("#otp")).toBeVisible();
  await context.close();
});

test("S1: a client in an excluded IP range skips 2FA", async ({ page }) => {
  // The range covers every IPv4 and IPv6 address, so it covers the address that ownCloud sees.
  await setAppConfig(admin, "piexcludeips", "0.0.0.0-255.255.255.255, ::-ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff");
  await loginPassword(page, "alice");
  await page.waitForURL(/\/apps\/files/);
});

test("E: PassOnNoUser lets a user who is not in privacyIDEA log in", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "1");
  await setAppConfig(admin, "passOnNoUser", "1");
  await loginPassword(page, "dave");
  await page.waitForURL(/\/apps\/files/, { timeout: 15000 });
});

test("E: without PassOnNoUser a user who is not in privacyIDEA gets a clean message", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "1");
  await loginPassword(page, "dave");
  await page.waitForLoadState("networkidle");
  const text = await bodyText(page);
  expect(text).toContain("ERR904");
  // S6: the text of the HTTP client contains the internal URL of privacyIDEA
  expect(text).not.toContain("privacyidea:8080");
  expect(text).not.toContain("Client error");
});

test("F: bob logs in with challenge-response", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "1");
  await loginPassword(page, "bob");
  await expect(page.locator("#otp")).toBeVisible();
  await page.fill("#otp", await freshOtp("bob"));
  await page.click("#submitButton");
  await page.waitForURL(/\/apps\/files/);
});

test("I: alice has no challenge-response token and still logs in with her OTP", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "1");
  await loginPassword(page, "alice");
  await expect(page.locator("#otp")).toBeVisible();
  await page.fill("#otp", await freshOtp("alice"));
  await page.click("#submitButton");
  await page.waitForURL(/\/apps\/files/);
});

test("P: push mode polls privacyIDEA with the transaction id", async ({ page }) => {
  // Guzzle 7 (ownCloud 11) sends no body with a GET request, so the id must be in the query string.
  // There is no push token in the test stack. privacyIDEA answers "pending" and writes the id to its audit log.
  await setAppConfig(admin, "triggerchallenges", "1");
  await loginPassword(page, "bob");
  await expect(page.locator("#otp")).toBeVisible();
  const transactionId = await page.locator("#transactionId").inputValue();
  expect(transactionId).not.toBe("");
  const before = (await piAudit("*polltransaction*"))[0]?.number ?? 0;
  const res = await page.request.post("/index.php/login/challenge/privacyidea", {
    form: { mode: "push", challenge: "", loadCounter: "1" },
    maxRedirects: 0,
  });
  expect(res.status()).toBe(303);
  const entries = (await piAudit("*polltransaction*")).filter((a) => a.number > before);
  expect(entries.map((a) => a.action_detail)).toContain(`transaction_id: ${transactionId}`);
  expect(entries[0].info).toContain("pending");
});

test("B6: the OTP field stays visible when triggering the challenge fails", async ({ page }) => {
  await setAppConfig(admin, "triggerchallenges", "1");
  await setAppConfig(admin, "serviceaccount_password", "wrong-password");
  await loginPassword(page, "alice");
  await page.waitForLoadState("networkidle");
  await expect(page.locator("#otp")).toBeVisible();
  expect(await bodyText(page)).toContain("Failed to fetch authentication token");
});

test("C6: an unreachable privacyIDEA server gives a clean message and no internal URL", async ({ page }) => {
  await setAppConfig(admin, "url", "http://127.0.0.1:1/");
  await loginPassword(page, "alice");
  await page.fill("#otp", "123456");
  await page.click("#submitButton");
  await expect(page.locator("span.warning")).toBeVisible();
  const text = await bodyText(page);
  expect(text).toContain("Failed to communicate with privacyIDEA");
  expect(text).not.toContain("127.0.0.1");
  expect(text).not.toContain("cURL");
});
