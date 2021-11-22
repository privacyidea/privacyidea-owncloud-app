<?php

if (isset($_['response']) && $_['response'])
{
    script('twofactor_privacyidea', 'response-status');
}
?>

<!--TODO Add otpFieldHint-->

<?php if (isset($_['message']) && $_['message']): ?>
    <fieldset class="warning">
        <?php p($_['message']); ?>
    </fieldset>
<?php endif; ?>

    <form method="POST" id="piLoginForm" name="piLoginForm">
        <?php
        if (isset($_['redirect_url']))
        {
            ?>
            <input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
            <?php
        }
        if (isset($_['tiqrImage']) && $_['tiqrImage'])
        {
            ?>
            <img width="250" src="<?php p($_['tiqrImage']); ?>" alt="tiqrImage">
            <?php
        }
        ?>

        <?php
        if (isset($_["responseStatus"]) && !$_["responseStatus"] === true)
        {
            ?>
            <input type="hidden" id="ResponseStatus" value="false">
            <?php
        }
        ?>

        <!-- only necessary for U2F. These hidden parameters are used in the script u2f.js -->
<!--        --><?php //if ($u2fSignRequest): ?>
<!--            <input type="hidden" id="u2f_challenge" value="--><?php //p($u2fSignRequest->challenge); ?><!--">-->
<!--            <input type="hidden" id="u2f_keyHandle" value="--><?php //p($u2fSignRequest->keyHandle); ?><!--">-->
<!--            <input type="hidden" id="u2f_appId" value="--><?php //p($u2fSignRequest->appId); ?><!--">-->
<!--            <input type="hidden" id="signatureData" name="signatureData">-->
<!--            <input type="hidden" id="clientData" name="clientData">-->
<!--        --><?php //endif; ?>

        <?php if (isset($_['hideOTPField']) && !$_['hideOTPField']): ?>
            <label>
                <input type="password" name="challenge" placeholder="OTP" autocomplete="off" autocorrect="off" required
                       autofocus>
            </label>
            <input type="submit" class="button" value="Verify">
        <?php endif; ?>

        <!-- Hidden input which store the info about changes -->
        <input id="mode" type="hidden" name="mode" value="<?php echo $this->data['mode'] ?>"/>
        <input id="u2fSignRequest" type="hidden" name="u2fSignRequest"
               value='<?php echo $_[''] ?>'/>

        <div id="AlternateLoginOptions" style="margin-top:35px" class="groupMargin">
            <label><strong>Alternate login options:</strong></label>
            <br>
            <!-- Alternate Login Options-->
            <input id="useWebAuthnButton" name="useWebAuthnButton" type="button" value="WebAuthn"
                   onclick="doWebAuthn()" style="width:140px; margin:15px 10px 7px"/>
            <input id="usePushButton" name="usePushButton" type="button" value="Push"
                   onclick="changeMode('push')" style="width:140px; margin:15px 10px 7px"/>
            <input id="useOTPButton" name="useOTPButton" style="width:140px; margin:15px 15px 7px" type="button"
                   value="OTP" onclick="changeMode('otp')"/>
            <input id="useU2FButton" name="useU2FButton" type="button" value="U2F" onclick="doU2F()"
                   style="width:140px; margin:15px 10px 7px"/>
        </div>

        <script>
        function doU2F()
        {
        // If mode is push, we have to change it, otherwise the site will refresh while doing webauthn
        // if (value("mode") === "push")
        // {
        // changeMode("u2f");
        // }

        if (!window.isSecureContext)
        {
        alert("Unable to proceed with U2F because the context is insecure!");
        console.log("Insecure context detected: Aborting U2F authentication!")
        // changeMode("otp");
        return;
        }

        const requestStr = value("u2fSignRequest");

        if (requestStr === null)
        {
        alert("Could not load U2F library. Please try again or use other token.");
        changeMode("otp");
        return;
        }

        try
        {
        const requestjson = JSON.parse(requestStr);
        sign_u2f_request(requestjson);
        } catch (err)
        {
        console.log("Error while signing U2FSignRequest: " + err);
        alert("Error while signing U2FSignRequest: " + err);
        }
        }

        function sign_u2f_request(signRequest)
        {

        let appId = signRequest["appId"];
        let challenge = signRequest["challenge"];
        let registeredKeys = [];

        registeredKeys.push({
        version: "U2F_V2",
        keyHandle: signRequest["keyHandle"]
        });

        u2f.sign(appId, challenge, registeredKeys, function (result)
        {
        const stringResult = JSON.stringify(result);
        if (stringResult.includes("clientData") && stringResult.includes("signatureData"))
        {
        set("u2fSignResponse", stringResult);
        set("mode", "u2f");
        document.forms["piLoginForm"].submit();
        }
        })
        }
        </script>

        <?php
        if (isset($_['autoSubmit']) && $_['autoSubmit']): ?>
            <input type="submit" class="button" value="Login">
        <?php endif; ?>
    </form>

<?php if (isset($_['autoSubmit']) && $_['autoSubmit']):
    script('twofactor_privacyidea', 'auto-submit');
endif; ?>