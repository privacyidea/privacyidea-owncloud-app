/**
 * @author Lukas Matusiewicz <lukas.matusiewicz@netknights.it>
 *
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

/*jshint sub:true*/

window.onload = function ()
{
    if (piGetValue("webAuthnSignRequest") === "")
    {
        piDisableElement("webAuthnButton");
    }
    if (piGetValue("u2fSignRequest") === "")
    {
        piDisableElement("u2fButton");
    }
    if (piGetValue("pushAvailable") !== "1")
    {
        piDisableElement("pushButton");
    }
    if (piGetValue("tiqrAvailable") !== "1")
    {
        piDisableElement("tiqrButton");
    }
    if (piGetValue("otpAvailable") !== "1")
    {
        piDisableElement("otpButton");
    }
    if (piGetValue("mode") === "otp" || piGetValue("mode").length < 1)
    {
        piDisableElement("otpButton");
    }
    if (piGetValue("mode") === "push" || piGetValue("mode") === "tiqr")
    {
        piDisableElement("otp");
        piDisableElement("submitButton");
        if (piGetValue("mode") === "push")
        {
            piDisableElement("pushButton");
        }
        if (piGetValue("mode") === "tiqr")
        {
            piDisableElement("tiqrButton");
            piEnableElement("tiqrImage");
        }
        piEnableElement("otpButton");
    }
    if (piGetValue("mode") === "webauthn")
    {
        piDisableElement("otp");
        piDisableElement("submitButton");
        doWebAuthn();
    }
    if (piGetValue("mode") === "u2f")
    {
        piDisableElement("otp");
        piDisableElement("submitButton");
        doU2F();
    }
    if (piGetValue("pushAvailable") !== "1"
        && piGetValue("tiqrAvailable") !== "1"
        && piGetValue("webAuthnSignRequest").length < 1
        && piGetValue("u2fSignRequest").length < 1)
    {
        piDisableElement("alternateLoginOptions");
    }

    /**
     * @param mode
     */
    function ensureSecureContextAndMode(mode)
    {
        // If mode is push, we have to change it, otherwise the site will refresh while doing webauthn
        if (piGetValue("mode") === "push" || piGetValue("mode") === "tiqr")
        {
            piChangeMode(mode);
        }

        if (!window.isSecureContext)
        {
            window.alert("Unable to proceed with WebAuthn / U2F because the context is insecure!");
            console.log("Insecure context detected: Aborting WebAuthn / U2F authentication!");
            piChangeMode("otp");
        }

        if (mode === "webauthn")
        {
            if (!window.pi_webauthn)
            {
                window.alert("Could not load WebAuthn library. Please try again or use other token!");
                piChangeMode("otp");
            }
        }
    }

    function doWebAuthn()
    {
        ensureSecureContextAndMode("webauthn");

        const requestStr = piGetValue("webAuthnSignRequest");
        if (requestStr === null)
        {
            window.alert("Could not to process WebAuthn request. Please try again or use other token.");
            piChangeMode("otp");
        }

        // Set origin
        if (!window.location.origin)
        {
            window.location.origin = window.location.protocol + "//"
                + window.location.hostname
                + (window.location.port ? ':' + window.location.port : '');
        }
        piSetValue("origin", window.origin);

        try
        {
            const requestjson = JSON.parse(requestStr);

            const webAuthnSignResponse = window.pi_webauthn.sign(requestjson);
            webAuthnSignResponse.then(function (webauthnresponse)
            {
                const response = JSON.stringify(webauthnresponse);
                piSetValue("webAuthnSignResponse", response);
                piSetValue("mode", "webauthn");
                document.forms["piLoginForm"].submit();
            });
        }
        catch (err)
        {
            console.log("Error while signing WebAuthnSignRequest: " + err);
            window.alert("Error while signing WebAuthnSignRequest: " + err);
        }
    }

    function doU2F()
    {
        ensureSecureContextAndMode(u2f);

        const requestStr = piGetValue("u2fSignRequest");
        if (requestStr === null)
        {
            window.alert("Could not load U2F library. Please try again or use other token.");
            piChangeMode("otp");
        }

        try
        {
            const requestjson = JSON.parse(requestStr);
            signU2FRequest(requestjson);
        }
        catch (err)
        {
            console.log("Error while signing U2FSignRequest: " + err);
            window.alert("Error while signing U2FSignRequest: " + err);
        }
    }

    /**
     * @param signRequest
     */
    function signU2FRequest(signRequest)
    {
        const appId = signRequest["appId"];
        const challenge = signRequest["challenge"];
        const registeredKeys = [];

        registeredKeys.push({
            version: "U2F_V2",
            keyHandle: signRequest["keyHandle"]
        });

        window.u2f.sign(appId, challenge, registeredKeys, function (result)
        {
            const stringResult = JSON.stringify(result);

            if (stringResult.includes("clientData") && stringResult.includes("signatureData"))
            {
                piSetValue("u2fSignResponse", stringResult);
                piSetValue("mode", "u2f");
                document.forms["piLoginForm"].submit();
            }
        });
    }
};