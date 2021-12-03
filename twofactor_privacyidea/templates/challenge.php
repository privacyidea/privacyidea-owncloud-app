<?php
script('twofactor_privacyidea', 'challenge');

if (isset($_['response']) && $_['response'])
{
    script('twofactor_privacyidea', 'response-status');
} ?>

    <!--TODO Add otpFieldHint in settings-->

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

        <?php if (!isset($_['hideOTPField']) || !$_['hideOTPField']): ?>
            <label>
                <input type="password" name="challenge" placeholder="OTP" autocomplete="off" autocorrect="off" required
                       autofocus>
            </label>
        <?php endif; ?>
        <input type="submit" class="button" value="Verify" style="width: 100%">

        <!-- Hidden input which store the info about changes -->
        <input id="mode" type="hidden" name="mode" value="<?php if (isset($_['mode']))
        {
            p($_['mode']);
        } ?>"/>
        <input id="modeChanged" type="hidden" name="modeChanged" value="0"/>

        <input id="u2fSignRequest" type="hidden" name="u2fSignRequest"
               value="<?php if (isset($_['u2fSignRequest']))
               {
                   p($_['u2fSignRequest']);
               } ?>"/>
        <input id="u2fSignResponse" type="hidden" name="u2fSignResponse" value=""/>

        <input id="webAuthnSignRequest" type="hidden" name="webAuthnSignRequest"
               value='<?php if (isset($_['webAuthnSignRequest']))
               {
                   p($_['webAuthnSignRequest']);
               } ?>'/>
        <input id="webAuthnSignResponse" type="hidden" name="webAuthnSignResponse" value=""/>
        <input id="origin" type="hidden" name="origin" value=""/>

        <input id="pushAvailable" type="hidden" name="pushAvailable"
               value="<?php if (isset($_['pushAvailable']))
               {
                   p($_['pushAvailable']);
               } ?>"/>

        <input id="otpAvailable" type="hidden" name="otpAvailable"
               value="<?php if (isset($_['otpAvailable']))
               {
                   p($_['otpAvailable']);
               } ?>"/>

        <!-- Alternate Login Options -->
        <div id="AlternateLoginOptions" style="margin-top:35px">
            <label><strong>Alternate login options:</strong></label>
            <br><br>
            <input id="useWebAuthnButton" name="useWebAuthnButton" type="button" value="WebAuthn"
                   style="width:140px; margin:15px 10px 7px"/>
            <input id="usePushButton" name="usePushButton" type="button" value="Push"
                   style="width:140px; margin:15px 10px 7px"/>
            <input id="useOTPButton" name="useOTPButton" type="button" value="OTP"
                   style="width:140px; margin:15px 15px 7px"/>
            <input id="useU2FButton" name="useU2FButton" type="button" value="U2F"
                   style="width:140px; margin:15px 10px 7px"/>
        </div>

        <?php
        if (isset($_['autoSubmit']) && $_['autoSubmit']): ?>
            <input type="submit" class="button" value="Login">
            <br>
        <?php endif; ?>
    </form> <!-- End of form -->

<?php if (isset($_['autoSubmit']) && $_['autoSubmit']):
    script('twofactor_privacyidea', 'auto-submit');
    echo "autosubmit!";
endif;
?>