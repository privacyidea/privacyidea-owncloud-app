// Helpers for the enrollment tests: policies, tokens and containers in privacyIDEA, the seed of a new token, and the
// registration of a smartphone container in place of the privacyIDEA Authenticator app.
import crypto from "node:crypto";
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

/** The containers of a user in the realm dev. */
export async function piContainers(user) {
  const json = await api("GET", `/container/?user=${encodeURIComponent(user)}&realm=dev`);
  return json.result.value.containers;
}

/** Delete all containers of a user in the realm dev. */
export async function removeContainers(user) {
  for (const container of await piContainers(user)) await api("DELETE", `/container/${container.serial}`);
}

/**
 * Register a smartphone container like the privacyIDEA Authenticator app does after it scanned the QR code.
 * The app signs "nonce|time|serial|scope" with a new key (ECDSA, curve P-384, SHA-256). The scope is the URL of
 * /container/register/finalize on the server of the link. The request goes to the test server.
 */
export async function registerContainer(link) {
  const params = new URL(link).searchParams;
  const serial = params.get("serial");
  const base = params.get("url");
  const scope = base.includes("container/register/finalize")
    ? base
    : base.replace(/\/?$/, "/") + "container/register/finalize";
  const { publicKey, privateKey } = crypto.generateKeyPairSync("ec", { namedCurve: "secp384r1" });
  const message = `${params.get("nonce")}|${params.get("time")}|${serial}|${scope}`;
  // privacyIDEA decodes the signature with urlsafe_b64decode, which needs the padding.
  const signature = crypto.sign("sha256", Buffer.from(message), privateKey).toString("base64")
    .replace(/\+/g, "-").replace(/\//g, "_");
  const res = await fetch(PRIVACYIDEA_URL + "/container/register/finalize", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      container_serial: serial,
      signature,
      public_client_key: publicKey.export({ type: "spki", format: "pem" }),
      scope,
    }),
  });
  const json = await res.json().catch(() => ({}));
  if (res.status !== 200 || json.result?.status !== true) {
    throw new Error(`The registration of ${serial} failed with status ${res.status}: ${JSON.stringify(json)}`);
  }
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
