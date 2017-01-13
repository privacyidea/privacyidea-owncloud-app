<?php if($_['messages']): ?>
<fieldset class="warning">
<ul>
<?php foreach($_['messages'] as $message): ?>
    <li><?php p($message); ?></li>
<?php endforeach; ?>
</ul>
</fieldset>
<?php endif; ?>
<form method="POST">
	<input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
	<input type="password" name="challenge" placeholder="OTP" autocomplete="off"
		   autocorrect="off" required autofocus>
	<input type="submit" class="button" value="Verify">
</form>
