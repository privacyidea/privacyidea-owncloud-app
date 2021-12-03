/*jshint sub:true*/
window.onload = function ()
{
    // Button listeners
    document.getElementById("useU2FButton").addEventListener("click", function ()
    {
        doU2F();
    });
    document.getElementById("useWebAuthnButton").addEventListener("click", function ()
    {
        doWebAuthn();
    });
    document.getElementById("usePushButton").addEventListener("click", function ()
    {
        changeMode('push');
    });
    document.getElementById("useOTPButton").addEventListener("click", function ()
    {
        changeMode('otp');
    });

    // Set alternate token button visibility
    if (document.getElementById("webAuthnSignRequest").value === "")
    {
        disable("useWebAuthnButton");
        console.log("Web Authn Button - disabled");
    }

    if (value("u2fSignRequest") === "")
    {
        disable("useU2FButton");
        console.log("U2F Button - disabled");
    }

    if (value("pushAvailable") !== "1")
    {
        disable("usePushButton");
        console.log("PUSH Button - disabled");
    }

    if (value("otpAvailable") !== "1")
    {
        disable("useOTPButton");
        console.log("OTP Button - disabled");
    }

    if (value("pushAvailable") === "0" && value("webAuthnSignRequest") === "" && value("u2fSignRequest") === "")
    {
        disable("alternateLoginOptions");
        console.log("Alternate Login Options - disabled. No other tokens available.");
    }

    if (value("mode") === "otp")
    {
        disable("useOTPButton");
        console.log("OTP Button - disabled");
    }

    if (value("mode") === "webauthn")
    {
        doWebAuthn();
        console.log("Doing WebAuthn");
    }

    if (value("mode") === "u2f")
    {
        doU2F();
        console.log("Doing U2F");
    }

    function doWebAuthn()
    {
        if(window.webauthn){
            console.log("webauthn");
        }
        if(window.u2f){
            console.log("u2f");
        }

        // If mode is push, we have to change it, otherwise the site will refresh while doing webauthn
        if (value("mode") === "push")
        {
            changeMode("webauthn");
        }

        if (!window.isSecureContext)
        {
            window.alert("Unable to proceed with Web Authn because the context is insecure!");
            console.log("Insecure context detected: Aborting Web Authn authentication!");
            changeMode("otp");
            return;
        }

        if (!window.pi_webauthn)
        {
            window.alert("Could not load WebAuthn library. Please try again or use other token!.");
            changeMode("otp");
            return;
        }

        var requestStr = value("webAuthnSignRequest");

        if (requestStr === null)
        {
            window.alert("Could not to process WebAuthn request. Please try again or use other token.");
            changeMode("otp");
            return;
        }
console.log("webauthn possible to process!!!"); //TODO rm
        // Set origin
        if (!window.location.origin)
        {
            window.location.origin = window.location.protocol
                + "//"
                + window.location.hostname
                + (window.location.port ? ':'
                    + window.location.port : '');
        }
        set("origin", window.origin);

        try
        {
            var requestjson = JSON.parse(requestStr);

            var webAuthnSignResponse = window.pi_webauthn.sign(requestjson);
            webAuthnSignResponse.then((webauthnresponse) =>
            {
                var response = JSON.stringify(webauthnresponse);
                set("webAuthnSignResponse", response);
                set("mode", "webauthn");
                document.forms["piLoginForm"].submit();
            });

        } catch (err)
        {
            console.log("Error while signing WebAuthnSignRequest: " + err);
            window.alert("Error while signing WebAuthnSignRequest: " + err);
        }
    }

    function doU2F()
    {
        // If mode is push, we have to change it, otherwise the site will refresh while doing webauthn
        if (value("mode") === "push")
        {
            changeMode("u2f");
        }

        if (!window.isSecureContext)
        {
            window.alert("Unable to proceed with U2F because the context is insecure!");
            console.log("Insecure context detected: Aborting U2F authentication!");
            changeMode("otp");
            return;
        }

        var requestStr = value("u2fSignRequest");

        if (requestStr === null)
        {
            window.alert("Could not load U2F library. Please try again or use other token.");
            changeMode("otp");
            return;
        }

        try
        {
            var requestjson = JSON.parse(requestStr);
            signU2FRequest(requestjson);
        } catch (err)
        {
            console.log("Error while signing U2FSignRequest: " + err);
            window.alert("Error while signing U2FSignRequest: " + err);
        }
    }

    function signU2FRequest(signRequest)
    {
        var appId = signRequest["appId"];
        var challenge = signRequest["challenge"];
        var registeredKeys = [];

        registeredKeys.push({
            version: "U2F_V2",
            keyHandle: signRequest["keyHandle"]
        });

        window.u2f.sign(appId, challenge, registeredKeys, function (result)
        {
            var stringResult = JSON.stringify(result);

            if (stringResult.includes("clientData") && stringResult.includes("signatureData"))
            {
                set("u2fSignResponse", stringResult);
                set("mode", "u2f");
                document.forms["piLoginForm"].submit();
            }
        });
    }

    /**
     *
     * @param id
     * @returns {string|*}
     */
    function value(id)
    {
        var element = document.getElementById(id);

        if (element === null)
        {
            console.log(id + " is null!");
            return "";
        } else
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
        var element = document.getElementById(id);
        if (element !== null)
        {
            element.value = value;
        } else
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
        var element = document.getElementById(id);
        if (element !== null)
        {
            element.style.display = "none";
        } else
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
        var element = document.getElementById(id);
        if (element !== null)
        {
            element.style.display = "initial";
        } else
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