/**
 * @author Cornelius Kölbel <cornelius.koelbel@netknights.it>
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
    // Button listeners
    document.getElementById("useU2FButton").addEventListener("click", function ()
    {
        changeMode("u2f");
    });
    document.getElementById("useWebAuthnButton").addEventListener("click", function ()
    {
        changeMode("webauthn");
    });
    document.getElementById("usePushButton").addEventListener("click", function ()
    {
        changeMode("push");
    });
    document.getElementById("useTiQRButton").addEventListener("click", function ()
    {
        changeMode("tiqr");
    });
    document.getElementById("useOTPButton").addEventListener("click", function ()
    {
        changeMode("otp");
    });

    // Set alternate token button visibility

    if (value("webAuthnSignRequest") === "")
    {
        disable("useWebAuthnButton");
    }

    if (value("u2fSignRequest") === "")
    {
        disable("useU2FButton");
    }

    if (value("pushAvailable") !== "1")
    {
        disable("usePushButton");
    }

    if (value("tiqrAvailable") !== "1")
    {
        disable("useTiQRButton");
        disable("tiqrImage");
    }

    if (value("otpAvailable") !== "1")
    {
        disable("useOTPButton");
    }

    if (value("pushAvailable") !== "1"
        && value("tiqrAvailable") !== "1"
        && value("webAuthnSignRequest").length < 1
        && value("u2fSignRequest").length < 1)
    {
        disable("alternateLoginOptions");
    }

    if (value("mode") === "otp" || value("mode").length < 1)
    {
        disable("useOTPButton");
    }

    if (value("mode") === "webauthn")
    {
        disable("otp");
        disable("submitButton");
        doWebAuthn();
    }

    if (value("mode") === "u2f")
    {
        disable("otp");
        disable("submitButton");
        doU2F();
    }

    function ensureSecureContextAndMode(mode)
    {
        // If mode is push, we have to change it, otherwise the site will refresh while doing webauthn
        if (value("mode") === "push" || value("mode") === "tiqr")
        {
            changeMode(mode);
        }

        if (!window.isSecureContext)
        {
            window.alert("Unable to proceed with WebAuthn / U2F because the context is insecure!");
            console.log("Insecure context detected: Aborting WebAuthn / U2F authentication!");
            changeMode("otp");
        }

        if (mode === "webauthn")
        {
            if (!window.pi_webauthn)
            {
                window.alert("Could not load WebAuthn library. Please try again or use other token!.");
                changeMode("otp");
            }
        }
    }

    function doWebAuthn()
    {
        ensureSecureContextAndMode("webauthn");

        const requestStr = value("webAuthnSignRequest");
        if (requestStr === null)
        {
            window.alert("Could not to process WebAuthn request. Please try again or use other token.");
            changeMode("otp");
        }

        // Set origin
        if (!window.location.origin)
        {
            window.location.origin = window.location.protocol + "//"
                + window.location.hostname
                + (window.location.port ? ':' + window.location.port : '');
        }
        set("origin", window.origin);

        try
        {
            const requestjson = JSON.parse(requestStr);

            const webAuthnSignResponse = window.pi_webauthn.sign(requestjson);
            webAuthnSignResponse.then(function (webauthnresponse)
            {
                const response = JSON.stringify(webauthnresponse);
                set("webAuthnSignResponse", response);
                set("mode", "webauthn");
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

        const requestStr = value("u2fSignRequest");
        if (requestStr === null)
        {
            window.alert("Could not load U2F library. Please try again or use other token.");
            changeMode("otp");
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
                set("u2fSignResponse", stringResult);
                set("mode", "u2f");
                document.forms["piLoginForm"].submit();
            }
        });
    }

    if (value("mode") === "push" || value("mode") === "tiqr")
    {
        const pollingIntervals = [4, 3, 2, 1];

        disable("otp");
        disable("submitButton");
        if (value("mode") === "push")
        {
            disable("usePushButton");
        }
        if (value("mode") === "tiqr")
        {
            disable("useTiQRButton");
            enable("tiqrImage");
        }
        enable("useOTPButton");

        let refreshTime;

        if (value("loadCounter") > (pollingIntervals.length - 1))
        {
            refreshTime = pollingIntervals[(pollingIntervals.length - 1)];
        }
        else
        {
            refreshTime = pollingIntervals[Number(value("loadCounter") - 1)];
        }
        refreshTime *= 1000;
        window.setTimeout(function ()
        {
            document.forms["piLoginForm"].submit();
        }, refreshTime);
    }

    /**
     *
     * @param id
     * @returns {string|*}
     */
    function value(id)
    {
        const element = document.getElementById(id);
        if (element === null)
        {
            console.log(id + " is null!");
            return "";
        }
        else
        {
            return element.value;
        }
    }

    /**
     *
     * @param id
     * @param value
     */
    function set(id, value)
    {
        const element = document.getElementById(id);
        if (element !== null)
        {
            element.value = value;
        }
        else
        {
            console.log(id + " is null!");
        }
    }

    /**
     *
     * @param id
     */
    function disable(id)
    {
        const element = document.getElementById(id);
        if (element !== null)
        {
            element.style.display = "none";
        }
        else
        {
            console.log(id + " is null!");
        }
    }

    /**
     *
     * @param id
     */
    function enable(id)
    {
        const element = document.getElementById(id);
        if (element !== null)
        {
            element.style.display = "initial";
        }
        else
        {
            console.log(id + " is null!");
        }
    }

    /**
     *
     * @param newMode
     */
    function changeMode(newMode)
    {
        document.getElementById("mode").value = newMode;
        document.getElementById("modeChanged").value = "1";
        document.forms["piLoginForm"].submit();
    }
};