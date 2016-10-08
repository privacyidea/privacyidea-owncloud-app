<form method="POST">
	<input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
	<input type="password" name="challenge" placeholder="OTP" autocomplete="off"
		   autocorrect="off" required autofocus>
	<input type="submit" class="button" value="Verify">
</form>
