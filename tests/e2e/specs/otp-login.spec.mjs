import { test, expect } from "@playwright/test";
import { adminPage, freshOtp, loginPassword, resetAppConfig } from "../helpers.mjs";

let admin;
test.beforeAll(async ({ browser }) => {
  admin = await adminPage(browser);
  await resetAppConfig(admin);
});

test("alice logs in with a valid OTP", async ({ page }) => {
  await loginPassword(page, "alice");
  await expect(page.locator("#otp")).toBeVisible();
  await page.fill("#otp", await freshOtp("alice"));
  await page.click("#submitButton");
  await page.waitForURL(/\/apps\/files/);
});

test("alice stays on the challenge page with a wrong OTP", async ({ page }) => {
  await loginPassword(page, "alice");
  await page.fill("#otp", "000000");
  await page.click("#submitButton");
  await expect(page.locator("#otp")).toBeVisible();
  await expect(page).not.toHaveURL(/\/apps\/files/);
  await expect(page.locator("span.warning")).toBeVisible();
});
