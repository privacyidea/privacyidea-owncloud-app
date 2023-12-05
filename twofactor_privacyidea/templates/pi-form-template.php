<?php
script('twofactor_privacyidea', ['event-listeners', 'pi-form-template']);
style('twofactor_privacyidea', 'pi-form-template');
?>

<!-- MESSAGES -->
<?php if (!empty($_['message'])): ?>
    <fieldset class="warning">
        <?php p($_['message']); ?>
    </fieldset>
<?php endif; ?>

<!-- IMAGES -->
<?php if (!empty($_['imgU2F']) && $_['mode'] === "u2f") : ?>
    <img class="pi_images" src="<?php p($_['imgU2F']); ?>" alt="U2F image"><br><br>
<?php endif;
if (!empty($_['imgWebauthn']) && $_['mode'] === "webauthn") : ?>
    <img class="pi_images" src="<?php p($_['imgWebauthn']); ?>" alt="WebAuthn image"><br><br>
<?php endif;
if (!empty($_['imgPush']) && $_['mode'] === "push") : ?>
    <img class="pi_images" src="<?php p($_['imgPush']); ?>" alt="Push image"><br><br>
<?php endif;
if (!empty($_['imgOTP']) && $_['mode'] === "otp") : ?>
    <img class="pi_images" id="imgOtp" src="<?php p($_['imgOTP']); ?>" alt="OTP image"><br><br>
<?php endif;
if (!empty($_['tiqrImage']) && $_['mode'] === "tiqr") : ?>
    <img class="pi_images" id="tiqrImage" width="250" src="<?php p($_['tiqrImage']); ?>" alt="TiQR Image"><br>
<?php endif; ?>

<!-- FORM -->
<form method="POST" id="piLoginForm" name="piLoginForm">
    <?php if (!empty($_['redirect_url'])) : ?>
        <input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
    <?php endif; ?>

    <?php if (!isset($_['hideOTPField']) || !$_['hideOTPField']): ?>
        <label>
            <input id="otp" type="password" name="password"
                   placeholder="OTP" autocomplete="off" required autofocus>
        </label>
        <br>
        <input id="submitButton" type="submit" class="button"
               value="<?php if (isset($_['verify'])) : p($_['verify']); endif; ?>">
    <?php endif; ?>

    <!-- Hidden input that saves the changes -->
    <input id="mode" type="hidden" name="mode"
           value="<?php if (isset($_['mode']))
           {
               p($_['mode']);
           }
           else
           {
               p("otp");
           } ?>"/>
    <input id="modeChanged" type="hidden" name="modeChanged" value="0"/>
    <input id="autoSubmitOtpLength" type="hidden" name="autoSubmitOtpLength"
           value="<?php if (!empty($_['autoSubmitOtpLength'])) : p($_['autoSubmitOtpLength']); endif; ?>"/>
    <input id="u2fSignRequest" type="hidden" name="u2fSignRequest"
           value="<?php if (isset($_['u2fSignRequest'])) : p($_['u2fSignRequest']); endif; ?>"/>
    <input id="u2fSignResponse" type="hidden" name="u2fSignResponse" value=""/>
    <input id="webAuthnSignRequest" type="hidden" name="webAuthnSignRequest"
           value='<?php if (isset($_['webAuthnSignRequest'])) : p($_['webAuthnSignRequest']); endif; ?>'/>
    <input id="webAuthnSignResponse" type="hidden" name="webAuthnSignResponse" value=""/>
    <input id="origin" type="hidden" name="origin" value=""/>
    <input id="pushAvailable" type="hidden" name="pushAvailable"
           value="<?php if (isset($_['pushAvailable'])) : p($_['pushAvailable']); endif; ?>"/>
    <input id="tiqrAvailable" type="hidden" name="tiqrAvailable"
           value="<?php if (isset($_['tiqrAvailable'])) : p($_['tiqrAvailable']); endif; ?>"/>
    <input id="otpAvailable" type="hidden" name="otpAvailable"
           value="<?php if (isset($_['otpAvailable'])) : p($_['otpAvailable']); endif; ?>"/>
    <input id="loadCounter" type="hidden" name="loadCounter"
           value="<?php if (isset($_['loadCounter'])) : p($_['loadCounter']); endif; ?>"/>

    <!-- ALTERNATE LOGIN OPTIONS -->
    <div id="alternateLoginOptions">
        <label>
            <strong>
                <?php if (isset($_['alternateLoginOptions'])) : p($_['alternateLoginOptions']); endif; ?>
            </strong>
        </label>
        <br>
        <input class="alternateTokenButtons" id="useWebAuthnButton" name="useWebAuthnButton"
               type="button" value="WebAuthn"/>
        <input class="alternateTokenButtons" id="usePushButton" name="usePushButton" type="button" value="Push"/>
        <input class="alternateTokenButtons" id="useTiQRButton" name="useTiQRButton" type="button" value="TiQR"/>
        <input id="useOTPButton" name="useOTPButton" type="button" value="OTP"/>
        <input class="alternateTokenButtons" id="useU2FButton" name="useU2FButton" type="button" value="U2F"/>
    </div>

    <?php if (isset($_['autoSubmit']) && $_['autoSubmit']): ?>
        <input type="submit" class="button" value="Login">
        <br>
    <?php endif; ?>
</form>

<!-- Submit the form automatically if set -->
<?php if (isset($_['autoSubmit']) && $_['autoSubmit']) :
    script('twofactor_privacyidea', 'auto-submit');
endif; ?>