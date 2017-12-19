<?php
if ($_["u2fSignRequest"]) {
    $u2fSignRequest = $_["u2fSignRequest"];
    script('twofactor_privacyidea', 'u2f-api');
    script('twofactor_privacyidea', 'u2f');
}
?>

<?php if ($_['messages']): ?>
    <fieldset class="warning">
        <ul>
            <?php foreach ($_['messages'] as $message): ?>
                <li><?php p($message); ?></li>
            <?php endforeach; ?>
        </ul>
    </fieldset>
<?php endif; ?>

<form method="POST" id="piLoginForm" name="piLoginForm">
    <input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
    <input type="hidden" name="transaction_id" value="<?php p($_['transactionId']); ?>">

    <!-- only necessary for U2F. These hidden parameters are used in the script u2f.js -->
    <?php if ($u2fSignRequest): ?>
        <input type="hidden" id="u2f_challenge" value="<?php p($u2fSignRequest->challenge);?>">
        <input type="hidden" id="u2f_keyHandle" value="<?php p($u2fSignRequest->keyHandle);?>">
        <input type="hidden" id="u2f_appId" value="<?php p($u2fSignRequest->appId);?>">
        <input type="hidden" id="signatureData" name="signatureData">
        <input type="hidden" id="clientData" name="clientData">
    <?php endif; ?>

    <?php if (!$_['hideOTPField']): ?>
        <input type="password" name="challenge" placeholder="OTP" autocomplete="off"
               autocorrect="off" required autofocus>
        <input type="submit" class="button" value="Verify">
    <?php endif; ?>
</form>
