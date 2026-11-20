// Prepare the privacyIDEA test server: resolver, realm, tokens and policies.
// Safe to run more than once. Runs inside the `setup-pi` compose service (Node 22, no packages).
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { REALM, RESOLVER, SEEDS_FILE, USERS } from "./lib/fixtures.mjs";

const BASE = (process.env.PI_URL || "http://privacyidea:8080").replace(/\/$/, "");
const ADMIN = process.env.PI_ADMIN || "admin";
const ADMIN_PASS = process.env.PI_ADMIN_PASS || "admin";
// The relying party id of the passkeys: the host name that the browser uses, or a parent domain of it.
const RP_ID = process.env.PI_RP_ID || "localhost";
const LDAP_RESOLVER = "devldap";
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
  fileName: "/etc/privacyidea/users.passwd",
});
// Optional: the users of an OpenLDAP as a second resolver of the realm (see dev/env.tls.example).
// The ownCloud users need the same names, for example local users with `DEV_EXTRA_USERS`.
let resolvers = RESOLVER;
if (process.env.PI_LDAP_URI) {
  await api("POST", `/resolver/${LDAP_RESOLVER}`, {
    type: "ldapresolver",
    LDAPURI: process.env.PI_LDAP_URI,
    LDAPBASE: process.env.PI_LDAP_BASE || "ou=people,dc=example,dc=org",
    BINDDN: process.env.PI_LDAP_BIND_DN || "cn=admin,dc=example,dc=org",
    BINDPW: process.env.PI_LDAP_BIND_PW || "admin",
    LOGINNAMEATTRIBUTE: "uid",
    LDAPSEARCHFILTER: "(uid=*)(objectClass=inetOrgPerson)",
    USERINFO: JSON.stringify({
      username: "uid",
      phone: "telephoneNumber",
      mobile: "mobile",
      email: "mail",
      surname: "sn",
      givenname: "givenName",
    }),
    UIDTYPE: "DN",
  });
  resolvers = `${RESOLVER},${LDAP_RESOLVER}`;
  console.log(`resolver "${LDAP_RESOLVER}" (${process.env.PI_LDAP_URI}) ok`);
}
await api("POST", `/realm/${REALM}`, { resolvers });
await api("POST", `/defaultrealm/${REALM}`);
console.log(`realm "${REALM}" with the resolver(s) "${resolvers}" ok`);

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

// Passkeys. The relying party id is the host name of the browser (default localhost). A passkey token needs it to enroll.
// "passkey_trigger_by_pin" lets /validate/triggerchallenge and /validate/check send a passkey challenge for a user.
await api("POST", "/policy/dev_passkey_rp", {
  scope: "enrollment",
  action: `webauthn_relying_party_id=${RP_ID},webauthn_relying_party_name=ownCloud dev`,
  active: true,
});
await api("POST", "/policy/dev_passkey_trigger", {
  scope: "authentication",
  action: "passkey_trigger_by_pin",
  active: true,
});
console.log(`policies dev_passkey_rp (relying party id "${RP_ID}") and dev_passkey_trigger ok`);
