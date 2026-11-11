// Test users and token data. Shared by dev/setup-privacyidea.mjs, dev/otp.mjs and the e2e tests.
import { readFileSync } from "node:fs";

export const REALM = "dev";
export const RESOLVER = "devusers";
export const OC_PASSWORD = "Test1234!";

/**
 * Where dev/setup-privacyidea.mjs writes the generated token seeds (git-ignored).
 * Each stack has its own file. DEV_STATE names the stack (default: "default", see dev/env.oc10).
 */
export const SEEDS_FILE = new URL(`../.state/${process.env.DEV_STATE || "default"}/seeds.json`, import.meta.url);

export const USERS = {
  // Has one TOTP token without a PIN. Logs in with the OTP value.
  alice: { serial: "DEV-ALICE-TOTP", ocPassword: OC_PASSWORD },
  // Has one TOTP token. A challenge-response policy applies to this user (and only this user).
  bob: { serial: "DEV-BOB-TOTP", ocPassword: OC_PASSWORD },
  // Exists in privacyIDEA but has no token.
  carol: { serial: null, ocPassword: OC_PASSWORD },
  // Does not exist in privacyIDEA at all. Only exists in ownCloud.
  dave: { serial: null, ocPassword: OC_PASSWORD },
};

/**
 * Seed (hex) of the TOTP token of a test user.
 * privacyIDEA ignores a client-supplied key unless a policy allows it, so the setup job
 * lets the server generate the key and stores the seed in SEEDS_FILE.
 */
export function seedOf(user) {
  let seeds;
  try {
    seeds = JSON.parse(readFileSync(SEEDS_FILE, "utf8"));
  } catch {
    throw new Error("The seeds file is missing. Run ./dev/setup.sh first (and set DEV_STATE for a second stack).");
  }
  if (!seeds[user]) throw new Error(`No token seed for ${user}. Run ./dev/setup.sh again.`);
  return seeds[user];
}
