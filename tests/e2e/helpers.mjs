import { expect } from "@playwright/test";
import { totp } from "../../dev/lib/totp.mjs";
import { USERS, seedOf } from "../../dev/lib/fixtures.mjs";

export const PRIVACYIDEA_URL = process.env.PRIVACYIDEA_URL || `http://localhost:${process.env.PRIVACYIDEA_PORT || 8081}`;

/** Fill in the ownCloud login form (first factor). */
export async function loginPassword(page, user, password = USERS[user]?.ocPassword ?? "admin") {
  await page.goto("/index.php/login");
  await page.fill("#user", user);
  await page.fill("#password", password);
  await page.click("#submit");
}

// privacyIDEA rejects an OTP that was already used. Remember the last 30 s step per user
// and wait for the next step when a test needs a second OTP in the same step.
const lastStep = new Map();
export async function freshOtp(user) {
  const seed = seedOf(user).seedHex;
  const step = () => Math.floor(Date.now() / 30000);
  while (lastStep.get(user) === step()) {
    await new Promise((r) => setTimeout(r, 1000));
  }
  lastStep.set(user, step());
  return totp(seed);
}

/** Log in as ownCloud admin. The admin is excluded from 2FA by the group setting from dev/setup.sh. */
export async function adminPage(browser) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await loginPassword(page, "admin", "admin");
  await page.waitForURL(/\/apps\/files/);
  return page;
}

/** Save an app setting the same way the admin settings page does. */
export async function setAppConfig(admin, key, value) {
  const status = await admin.evaluate(
    async ([k, v]) => {
      const res = await fetch(OC.generateUrl("/apps/twofactor_privacyidea/setValue"), {
        method: "POST",
        headers: { requesttoken: OC.requestToken, "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ key: k, value: v }),
      });
      return res.status;
    },
    [key, value],
  );
  expect(status, `setValue ${key}`).toBe(200);
}

/** The app settings that the tests change, with the values that dev/setup.sh writes. */
export const DEFAULT_CONFIG = {
  url: "http://privacyidea:8080",
  triggerchallenges: "0",
  passOnNoUser: "0",
  piexcludeips: "",
  pollInBrowser: "",
  pollInBrowserUrl: "",
  serviceaccount_password: "admin",
};

/** Put the settings back, so that a test never depends on what an earlier test changed. */
export async function resetAppConfig(admin) {
  for (const [key, value] of Object.entries(DEFAULT_CONFIG)) await setAppConfig(admin, key, value);
}

/** Entries of the privacyIDEA audit log for an action pattern, newest first. */
export async function piAudit(action, pageSize = 5) {
  const auth = await (
    await fetch(PRIVACYIDEA_URL + "/auth", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ username: "admin", password: "admin" }),
    })
  ).json();
  const res = await fetch(
    `${PRIVACYIDEA_URL}/audit/?action=${encodeURIComponent(action)}&sortby=number&sortorder=desc&page_size=${pageSize}`,
    { headers: { Authorization: auth.result.value.token } },
  );
  return (await res.json()).result.value.auditdata;
}
