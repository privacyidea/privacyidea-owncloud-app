<?php
script('twofactor_privacyidea', 'challenge');
script('twofactor_privacyidea', 'event-listeners');
?>

<?php if (!empty($_['message'])): ?>
    <fieldset class="warning">
        <?php p($_['message']); ?>
    </fieldset>
<?php endif; ?>

<?php if (!empty($_['imgU2F']) && $_['mode'] === "u2f"): ?>
    <img src="<?php p($_['imgU2F']); ?>" style="text-align:center !important;" alt="U2F image"><br><br>
<?php endif;
if (!empty($_['imgWebauthn']) && $_['mode'] === "webauthn"): ?>
    <img src="<?php p($_['imgWebauthn']); ?>" style="text-align:center" alt="WebAuthn image"><br><br>
<?php endif;
if (!empty($_['imgPush']) && $_['mode'] === "push"): ?>
    <img src="<?php p($_['imgPush']); ?>" style="text-align:center" alt="Push image"><br><br>
<?php endif;
if (!empty($_['imgOTP']) && $_['mode'] === "otp"): ?>
    <img src="<?php p($_['imgOTP']); ?>" style="text-align:center; vertical-align:unset;" alt="OTP image"><br><br>
<?php endif; ?>

<form method="POST" id="piLoginForm" name="piLoginForm">
    <?php
    if (!empty($_['redirect_url']))
    {
        ?>
        <input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
        <?php
    }
    if (!empty($_['tiqrImage']) && $_['mode'] === "tiqr")
    {
        ?>
        <img id="tiqrImage" width="250" src="<?php p($_['tiqrImage']); ?>" alt="TiQR Image"><br>
        <?php
    }
    ?>

    <?php if (!isset($_['hideOTPField']) || !$_['hideOTPField']): ?>
    <label>
        <input id="otp" type="password" name="challenge" placeholder="OTP" autocomplete="off"
               required autofocus style="width:230px; text-align:center; margin:0 0 5px">
    </label>
    <br>
    <input id="submitButton" type="submit" class="button"
           style="width:251px; display: inline !important; text-align:center !important;"
           value="<?php if (isset($_['verify'])) :
           p($_['verify']); endif;?>">
<?php endif; ?>

    <!-- Hidden input which store the info about changes -->
    <input id="modeChanged" type="hidden" name="modeChanged" value="0"/>

    <input id="autoSubmitOtpLength" type="hidden" name="autoSubmitOtpLength"
           value="<?php if (isset($_['autoSubmitOtpLength'])) :
           p($_['autoSubmitOtpLength']); endif;?>"/>
    <input id="u2fSignRequest" type="hidden" name="u2fSignRequest"
           value="<?php if (isset($_['u2fSignRequest'])) :
           p($_['u2fSignRequest']); endif;?>"/>
    <input id="u2fSignResponse" type="hidden" name="u2fSignResponse" value=""/>

    <input id="webAuthnSignRequest" type="hidden" name="webAuthnSignRequest"
           value='<?php if (isset($_['webAuthnSignRequest'])) :
           p($_['webAuthnSignRequest']); endif;?>'/>
    <input id="webAuthnSignResponse" type="hidden" name="webAuthnSignResponse" value=""/>
    <input id="origin" type="hidden" name="origin" value=""/>

    <input id="pushAvailable" type="hidden" name="pushAvailable"
           value="<?php if (isset($_['pushAvailable'])) :
           p($_['pushAvailable']); endif;?>"/>

    <input id="tiqrAvailable" type="hidden" name="tiqrAvailable"
           value="<?php if (isset($_['tiqrAvailable'])) :
           p($_['tiqrAvailable']); endif;?>"/>

    <input id="otpAvailable" type="hidden" name="otpAvailable"
           value="<?php if (isset($_['otpAvailable'])) :
           p($_['otpAvailable']); endif;?>"/>

    <input id="loadCounter" type="hidden" name="loadCounter"
           value="<?php if (isset($_['loadCounter'])) :
           p($_['loadCounter']); endif;?>"/>

    <input id="mode" type="hidden" name="mode"
           value="<?php
           if (isset($_['mode']))
           {
               p($_['mode']);
           }
           else
           {
               p("otp");
           } ?>"/>

    <!-- Alternate Login Options -->
    <div id="alternateLoginOptions" style="margin-top:35px">
        <label>
            <strong><?php if (isset($_['alternateLoginOptions'])) :
                p($_['alternateLoginOptions']); endif;?></strong>
        </label>
        <br>
        <input id="useWebAuthnButton" name="useWebAuthnButton" type="button"
               value="WebAuthn"
               style="width:140px; margin:15px 10px 7px"/>
        <input id="usePushButton" name="usePushButton" type="button" value="Push"
               style="width:140px; margin:15px 10px 7px"/>
        <input id="useTiQRButton" name="useTiQRButton" type="button" value="TiQR"
               style="width:140px; margin:15px 10px 7px"/>
        <input id="useOTPButton" name="useOTPButton" type="button" value="OTP"
               style="width:140px; margin:15px 15px 7px"/>
        <input id="useU2FButton" name="useU2FButton" type="button" value="U2F"
               style="width:140px; margin:15px 10px 7px"/>
    </div>

    <?php if (isset($_['autoSubmit']) && $_['autoSubmit']): ?>
        <input type="submit" class="button" value="Login">
        <br>
    <?php endif; ?>
</form> <!-- End of form -->

<?php if (isset($_['autoSubmit']) && $_['autoSubmit'])
{
    script('twofactor_privacyidea', 'auto-submit');
} ?>