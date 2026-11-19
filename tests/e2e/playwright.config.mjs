import { defineConfig } from "@playwright/test";

// Runs against the stack from compose-dev.yml (see dev/README.md).
// One worker only: the tests share users and privacyIDEA rejects a reused OTP.
export default defineConfig({
  testDir: "./specs",
  workers: 1,
  fullyParallel: false,
  timeout: 90_000,
  // On CI a test that fails once and passes at the retry is reported as "flaky".
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [["list"], ["github"]] : [["list"]],
  use: {
    baseURL: process.env.OWNCLOUD_URL || `http://localhost:${process.env.OWNCLOUD_PORT || 8080}`,
    trace: "retain-on-failure",
  },
});
