<form method="POST">
	<input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
	<input type="password" name="challenge" placeholder="OTP" autocomplete="off" autocapitalize="off" autocorrect="off" required>
	<input type="submit" class="button" value="Verify">
</form>
