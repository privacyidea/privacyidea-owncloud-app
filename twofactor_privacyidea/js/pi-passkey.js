/**
 * @license AGPL-3.0
 *
 * This code is a free software: You can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

/*
 * Passkey login. The browser signs the challenge of privacyIDEA with a passkey and the form sends the answer to the
 * server. The challenge has the form {"challenge": "...", "rpId": "...", "userVerification": "..."}.
 */

/**
 * Convert a byte buffer to a base64 string.
 *
 * @param {ArrayBuffer} buffer
 * @returns {string}
 */
function piPasskeyToBase64(buffer)
{
    const bytes = new Uint8Array(buffer);
    let binary = "";
    for (let i = 0; i < bytes.length; i++)
    {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

/**
 * Ask the browser for a passkey and send the answer with the login form.
 * If the user cancels or the browser refuses, go back to the OTP mode.
 */
window.piDoPasskey = function doPasskey()
{
    if (!window.isSecureContext || !navigator.credentials)
    {
        window.alert("Unable to proceed with the passkey because the context is insecure!");
        console.log("Insecure context detected: Aborting passkey authentication!");
        piChangeMode("otp");
        return;
    }

    let challenge;
    try
    {
        challenge = JSON.parse(piGetValue("passkeyChallenge"));
    }
    catch (err)
    {
        console.log("Could not read the passkey challenge: " + err);
        piChangeMode("otp");
        return;
    }
    if (!challenge || !challenge.challenge)
    {
        console.log("Passkey authentication: The challenge is empty!");
        piChangeMode("otp");
        return;
    }

    // privacyIDEA verifies the challenge as the bytes of the text that it sent.
    const options = {
        challenge: Uint8Array.from(challenge.challenge, function (c)
        {
            return c.charCodeAt(0);
        }),
        userVerification: ["required", "preferred", "discouraged"].includes(challenge.userVerification)
            ? challenge.userVerification : "preferred"
    };
    if (challenge.rpId)
    {
        options.rpId = challenge.rpId;
    }

    navigator.credentials.get({publicKey: options}).then(function (credential)
    {
        const response = {
            credential_id: credential.id,
            authenticatorData: piPasskeyToBase64(credential.response.authenticatorData),
            clientDataJSON: piPasskeyToBase64(credential.response.clientDataJSON),
            signature: piPasskeyToBase64(credential.response.signature)
        };
        if (credential.response.userHandle)
        {
            response.userHandle = piPasskeyToBase64(credential.response.userHandle);
        }
        piSetValue("passkeySignResponse", JSON.stringify(response));
        piSetValue("origin", window.location.origin);
        piSetValue("mode", "passkey");
        document.forms["piLoginForm"].submit();
    }, function (error)
    {
        // The user canceled the prompt, has no passkey for this site, or the browser refused.
        console.log("Error during passkey authentication: " + error);
        piChangeMode("otp");
    });
};
