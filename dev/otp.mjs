// Print the current OTP of a test user:   node dev/otp.mjs alice
import { seedOf } from "./lib/fixtures.mjs";
import { totp } from "./lib/totp.mjs";

const user = process.argv[2];
if (!user) {
  console.error("usage: node dev/otp.mjs <alice|bob>");
  process.exit(1);
}
console.log(totp(seedOf(user).seedHex));
