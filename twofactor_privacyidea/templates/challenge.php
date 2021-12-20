<?php
script('twofactor_privacyidea', 'challenge');
script('twofactor_privacyidea', 'webauthn');
?>

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
        if (isset($_['tiqrImage']))
        {
            ?>
            <img id="tiqrImage" width="250" src="<?php p($_['tiqrImage']); ?>" alt="TiQR Image"><br>
            <?php
        }
        ?>

        <?php if (!isset($_['hideOTPField']) || !$_['hideOTPField']): ?>
            <label>
                <input id="otp" type="password" name="challenge" placeholder="OTP" autocomplete="off" autocorrect="off"
                       required
                       autofocus>
            </label>
            <input id="submitButton" type="submit" class="button" value="<?php if (isset($_['verify']))
            {
                p($_['verify']);
            } ?>" style="width: 100%">
        <?php endif; ?>

        <!-- Hidden input which store the info about changes -->
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

        <input id="tiqrAvailable" type="hidden" name="tiqrAvailable"
               value="<?php if (isset($_['tiqrAvailable']))
               {
                   p($_['tiqrAvailable']);
               } ?>"/>

        <input id="otpAvailable" type="hidden" name="otpAvailable"
               value="<?php if (isset($_['otpAvailable']))
               {
                   p($_['otpAvailable']);
               } ?>"/>

        <input id="loadCounter" type="hidden" name="loadCounter"
               value="<?php if (isset($_['loadCounter']))
               {
                   p($_['loadCounter']);
               } ?>"/>

        <input id="mode" type="hidden" name="mode" value="<?php if (isset($_['mode']))
        {
            p($_['mode']);
        }
        else
        {
            p("otp");
        } ?>"/>

        <!-- Alternate Login Options -->
        <div id="alternateLoginOptions" style="margin-top:35px">
            <label><strong><?php if (isset($_['alternateLoginOptions']))
                    {
                        p($_['alternateLoginOptions']);
                    } ?></strong></label>
            <br>
            <input id="useWebAuthnButton" name="useWebAuthnButton" type="button" value="WebAuthn"
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

        <?php
        if (isset($_['autoSubmit']) && $_['autoSubmit']): ?>
            <input type="submit" class="button" value="Login">
            <br>
        <?php endif; ?>
    </form> <!-- End of form -->

<?php if (isset($_['autoSubmit']) && $_['autoSubmit']):
    script('twofactor_privacyidea', 'auto-submit');
endif;
?>