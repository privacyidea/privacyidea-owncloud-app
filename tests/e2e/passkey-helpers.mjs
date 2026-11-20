// Helpers for the passkey tests: a virtual authenticator in the browser and passkey tokens in privacyIDEA.
// Chromium emulates the authenticator through the DevTools protocol, so the tests need no hardware.
import { PRIVACYIDEA_URL } from "./helpers.mjs";

async function pi(method, path, token, body, headers = {}) {
  const res = await fetch(PRIVACYIDEA_URL + path, {
    method,
    // A GET with a JSON content type and no body gets "400 Bad Request" from privacyIDEA, so only a request with a body sets it.
    headers: {
      ...(method === "GET" ? {} : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: token } : {}),
      ...headers,
    },
    body: method === "GET" ? undefined : JSON.stringify(body ?? {}),
  });
  const json = await res.json().catch(() => ({}));
  return { status: res.status, json };
}

async function adminToken() {
  const r = await pi("POST", "/auth", null, { username: "admin", password: "admin" });
  return r.json.result.value.token;
}

/** Delete the passkey tokens of a user in privacyIDEA. */
export async function removePasskeys(user) {
  const token = await adminToken();
  const list = await pi("GET", `/token/?user=${encodeURIComponent(user)}&realm=dev`, token);
  // Fail loudly. A cleanup that finds nothing because the request failed hides leftover tokens.
  if (list.status !== 200) throw new Error(`token list of ${user} failed with status ${list.status}`);
  for (const t of list.json.result?.value?.tokens ?? []) {
    if (t.tokentype !== "passkey") continue;
    const res = await pi("DELETE", `/token/${t.serial}`, token);
    if (res.status !== 200) throw new Error(`delete of ${t.serial} failed with status ${res.status}`);
  }
}

/** Add a virtual authenticator to the page. It holds discoverable credentials and confirms every request by itself. */
export async function addAuthenticator(page) {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send("WebAuthn.enable");
  const { authenticatorId } = await cdp.send("WebAuthn.addVirtualAuthenticator", {
    options: {
      protocol: "ctap2",
      transport: "internal",
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });
  return {
    credentials: async () => (await cdp.send("WebAuthn.getCredentials", { authenticatorId })).credentials,
    // credentialId is the id as the browser shows it (base64url). The DevTools protocol wants standard base64.
    removeCredential: (credentialId) => {
      const b64 = credentialId.replace(/-/g, "+").replace(/_/g, "/");
      return cdp.send("WebAuthn.removeCredential", { authenticatorId, credentialId: b64.padEnd(Math.ceil(b64.length / 4) * 4, "=") });
    },
    addCredential: (credential) => cdp.send("WebAuthn.addCredential", { authenticatorId, credential }),
  };
}

/**
 * Register a passkey for a user in privacyIDEA and in the virtual authenticator of the page.
 * The page must show a page of ownCloud, because the origin of the page is the origin of the registration.
 * Returns the id of the credential.
 */
export async function enrollPasskey(page, user) {
  const origin = new URL(page.url()).origin;
  const token = await adminToken();
  const init = await pi("POST", "/token/init", token, { type: "passkey", user, realm: "dev" });
  const registration = init.json.detail.passkey_registration;
  const created = await page.evaluate(async (data) => {
    const toBuffer = (s) => {
      const b64 = s.replace(/-/g, "+").replace(/_/g, "/");
      const padding = (4 - (b64.length % 4)) % 4;
      return Uint8Array.from(atob(b64.padEnd(b64.length + padding, "=")), (c) => c.charCodeAt(0)).buffer;
    };
    const toBase64 = (buffer) => btoa(String.fromCodePoint(...new Uint8Array(buffer)));
    const credential = await navigator.credentials.create({
      publicKey: {
        rp: data.rp,
        user: { id: toBuffer(data.user.id), name: data.user.name, displayName: data.user.displayName },
        challenge: Uint8Array.from(data.challenge, (c) => c.charCodeAt(0)),
        pubKeyCredParams: data.pubKeyCredParams,
        excludeCredentials: [],
        authenticatorSelection: data.authenticatorSelection,
        timeout: data.timeout,
        attestation: data.attestation,
      },
    });
    return {
      credential_id: credential.id,
      rawId: toBase64(credential.rawId),
      authenticatorAttachment: credential.authenticatorAttachment,
      attestationObject: toBase64(credential.response.attestationObject),
      clientDataJSON: toBase64(credential.response.clientDataJSON),
    };
  }, registration);
  const done = await pi(
    "POST",
    "/token/init",
    token,
    { type: "passkey", transaction_id: init.json.detail.transaction_id, serial: init.json.detail.serial, ...created },
    { Origin: origin },
  );
  if (done.json.result?.value !== true) throw new Error("Passkey enrollment failed: " + JSON.stringify(done.json));
  return created.credential_id;
}
