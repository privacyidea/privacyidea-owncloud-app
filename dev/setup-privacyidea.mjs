// Prepare the privacyIDEA test server: resolver, realm, tokens and policies.
// Safe to run more than once. Runs inside the `setup-pi` compose service (Node 22, no packages).
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { REALM, RESOLVER, SEEDS_FILE, USERS } from "./lib/fixtures.mjs";

const BASE = (process.env.PI_URL || "http://privacyidea:8080").replace(/\/$/, "");
const ADMIN = process.env.PI_ADMIN || "admin";
const ADMIN_PASS = process.env.PI_ADMIN_PASS || "admin";
let token = "";

async function api(method, path, body, { allowStatus = [] } = {}) {
  const res = await fetch(BASE + path, {
    method,
    headers: { "Content-Type": "application/json", ...(token ? { Authorization: token } : {}) },
    // privacyIDEA answers HTTP 500 to a JSON request with an empty body, so always send an object.
    body: method === "GET" ? undefined : JSON.stringify(body ?? {}),
  });
  const json = await res.json().catch(() => ({}));
  const ok = res.ok && json?.result?.status !== false;
  if (!ok && !allowStatus.includes(res.status)) {
    throw new Error(`${method} ${path} -> HTTP ${res.status}: ${JSON.stringify(json?.result?.error ?? json)}`);
  }
  return json;
}

// privacyIDEA may still run migrations right after the container reports healthy.
for (let i = 0; ; i++) {
  try {
    const r = await api("POST", "/auth", { username: ADMIN, password: ADMIN_PASS });
    token = r.result.value.token;
    break;
  } catch (e) {
    if (i >= 30) throw e;
    await new Promise((r) => setTimeout(r, 2000));
  }
}
console.log(`privacyIDEA ${BASE}: admin login ok`);

await api("POST", `/resolver/${RESOLVER}`, {
  type: "passwdresolver",
  fileName: "/privacyidea/etc/users.passwd",
});
await api("POST", `/realm/${REALM}`, { resolvers: RESOLVER });
await api("POST", `/defaultrealm/${REALM}`);
console.log(`resolver "${RESOLVER}" and default realm "${REALM}" ok`);

const seeds = {};
for (const [name, u] of Object.entries(USERS)) {
  if (!u.serial) continue;
  await api("DELETE", `/token/${u.serial}`, undefined, { allowStatus: [404, 400] });
  // genkey: privacyIDEA ignores a client-supplied "otpkey" when no admin policy exists
  // (it then treats "totp_force_server_generate" as allowed). Read the seed from the answer.
  const r = await api("POST", "/token/init", {
    type: "totp",
    serial: u.serial,
    user: name,
    realm: REALM,
    genkey: 1,
    description: "ownCloud app test token",
  });
  const raw = r.detail?.otpkey?.value ?? "";
  const seedHex = raw.startsWith("seed://") ? raw.slice("seed://".length) : "";
  if (!seedHex) throw new Error(`No seed in the answer for ${u.serial}: ${JSON.stringify(r.detail?.otpkey)}`);
  seeds[name] = { serial: u.serial, seedHex };
  console.log(`token ${u.serial} for ${name} ok`);
}
mkdirSync(dirname(fileURLToPath(SEEDS_FILE)), { recursive: true });
writeFileSync(SEEDS_FILE, JSON.stringify(seeds, null, 2));
console.log(`seeds written to dev/.state/${process.env.DEV_STATE || "default"}/seeds.json`);

// Challenge-response only for bob, so that alice keeps the plain "enter the OTP" login.
await api("POST", "/policy/dev_challenge_response_bob", {
  scope: "authentication",
  action: "challenge_response=totp",
  user: "bob",
  active: true,
});
console.log("policy dev_challenge_response_bob ok");
