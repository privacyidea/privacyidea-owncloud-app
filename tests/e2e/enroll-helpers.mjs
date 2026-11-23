// Helpers for the enrollment tests: policies and tokens in privacyIDEA, and the seed of a new token.
import { PRIVACYIDEA_URL } from "./helpers.mjs";

async function api(method, path, body) {
  const auth = await (
    await fetch(PRIVACYIDEA_URL + "/auth", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ username: "admin", password: "admin" }),
    })
  ).json();
  // A GET with a JSON content type and no body gets "400 Bad Request" from privacyIDEA.
  const res = await fetch(PRIVACYIDEA_URL + path, {
    method,
    headers: { ...(method === "GET" ? {} : { "Content-Type": "application/json" }), Authorization: auth.result.value.token },
    body: method === "GET" ? undefined : JSON.stringify(body ?? {}),
  });
  const json = await res.json().catch(() => ({}));
  if (res.status !== 200) throw new Error(`${method} ${path} failed with status ${res.status}: ${JSON.stringify(json)}`);
  return json;
}

/** Create or replace a policy, for example {scope, action, user, realm, active}. */
export async function setPolicy(name, policy) {
  await api("POST", `/policy/${name}`, policy);
}

/** Delete a policy. A missing policy is no error. */
export async function deletePolicy(name) {
  await api("DELETE", `/policy/${name}`).catch(() => {});
}

/** The tokens of a user in the realm dev. */
export async function piTokens(user) {
  const json = await api("GET", `/token/?user=${encodeURIComponent(user)}&realm=dev`);
  return json.result.value.tokens;
}

/** Delete all tokens of a user in the realm dev. */
export async function removeTokens(user) {
  for (const token of await piTokens(user)) await api("DELETE", `/token/${token.serial}`);
}

/** The seed (hex) from an otpauth link: the parameter "secret" in base32. */
export function hexSecretFromLink(link) {
  const secret = new URL(link).searchParams.get("secret").toUpperCase().replace(/=+$/, "");
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  let bits = "";
  for (const c of secret) {
    const value = alphabet.indexOf(c);
    if (value < 0) throw new Error(`Not base32: ${c}`);
    bits += value.toString(2).padStart(5, "0");
  }
  let hex = "";
  for (let i = 0; i + 8 <= bits.length; i += 8) hex += parseInt(bits.slice(i, i + 8), 2).toString(16).padStart(2, "0");
  return hex;
}
