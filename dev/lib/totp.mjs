// RFC 6238 TOTP (SHA-1, 6 digits, 30 s step) without dependencies.
// Used by the setup script and by the end-to-end tests to answer the OTP prompt.
import { createHmac } from "node:crypto";

export function totp(secretHex, { time = Date.now(), step = 30, digits = 6 } = {}) {
  const counter = BigInt(Math.floor(time / 1000 / step));
  const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(counter);
  const h = createHmac("sha1", Buffer.from(secretHex, "hex")).update(msg).digest();
  const off = h[h.length - 1] & 0x0f;
  const bin = h.readUInt32BE(off) & 0x7fffffff;
  return String(bin % 10 ** digits).padStart(digits, "0");
}
