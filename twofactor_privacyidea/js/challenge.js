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
    document.getElementById("useTiQRButton").addEventListener("click", function ()
    {
        changeMode('tiqr');
    });
    document.getElementById("useOTPButton").addEventListener("click", function ()
    {
        changeMode('otp');
    });

    // Set alternate token button visibility

    if (value("webAuthnSignRequest") === "")
    {
        disable("useWebAuthnButton");
        console.log("Web Authn Button - disabled");
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
        console.log("Alternate Login Options - disabled. No other tokens available.");
    }

    if (value("mode") === "otp" || value("mode").length < 1)
    {
        disable("useOTPButton");
    }

    if(value("mode") !== "tiqr") {
        disable("tiqrImage");
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

        // If mode is push, we have to change it, otherwise the site will refresh while doing webauthn
        if (value("mode") === "push" || value("mode") === "tiqr")
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

        if (!window.webauthn)
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

            var webAuthnSignResponse = window.webauthn.sign(requestjson);
            webAuthnSignResponse.then(function (webauthnresponse)
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
        // If mode is push, we have to change it, otherwise the site will refresh while doing U2F
        if (value("mode") === "push" || value("mode") === "tiqr")
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

    if (value("mode") === "push" || value("mode") === "tiqr")
    {

        var pollingIntervals = [4, 3, 2, 1];

        disable("otp");
        disable("submitButton");
        if(value("mode") === "push") {
            disable("usePushButton");
        }
        if(value("mode") === "tiqr") {
            disable("useTiQRButton");
            enable("tiqrImage");
        }
        enable("useOTPButton");

        var refreshTime;

        if (value("loadCounter") > (pollingIntervals.length - 1))
        {
            refreshTime = pollingIntervals[(pollingIntervals.length - 1)];
        } else
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