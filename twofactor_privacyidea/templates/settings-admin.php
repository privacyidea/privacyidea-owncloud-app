<?php
script('twofactor_privacyidea', 'settings-admin');
?>

<div class="section">
    <h2><?php p($l->t('privacyIDEA 2FA')); ?></h2>
    <p>
        <em>In a second step of authentication the user is asked to provide a one 
            time password. The users devices are managed in privacyIDEA. The 
            authentication request is forwarded to privacyIDEA.
        </em>
    </p>    
    <div id="piSettings">
        <p>
        <label for="piurl">URL of the privacyIDEA Server</label>
        <input id="piurl" size="60"/>
        </p>
        
        <p>
        <input id="checkssl" type="checkbox" class="checkbox">
        <label for="checkssl">
            Verify the SSL certificate. 
            Do not uncheck this in productive environments!
        </label>
        </p>
        
        <p>
            <label for="pirealm">User Realm in privacyIDEA (other than default)</label>
            <input id="pirealm" size="40"/>
        </p>
        
    </div>
</div>
