<?php
script('twofactor_privacyidea', 'settings-admin');
?>

<div class="section" id="privacyIDEA">
    <h2 style="display: inline-block">
    <span style="float: left"><?php p($l->t('privacyIDEA 2FA')); ?></span>
    <a target="_blank" rel="noreferrer" class="icon-info svg"
       title="<?php p($l->t('Open documentation')); ?>"
       href="http://privacyidea.readthedocs.io"></a></h2>
    <span id="pi_settings_msg" class="msg"></span>
    <hr>
    <h3>
        <?php p($l->t('Configuration')); ?>
    </h3>
    <p>
        <em>
            <?php p($l->t('
                In a second step of authentication the user is asked to provide a one
                time password. The users devices are managed in privacyIDEA. The
                authentication request is forwarded to privacyIDEA.
            ')); ?>
        </em>
    </p>
    <div id="piSettings">
        <p>
            <input id="piactive" type="checkbox" class="checkbox">
            <label for="piactive"><?php p($l->t('Activate two factor authentication with privacyIDEA.')); ?></label>
            <em>
                <?php p($l->t('
                    Before activating two factor authentication with privacyIDEA, please asure, that the connection to
                    your privacyIDEA-server is configured correctly.
                ')); ?>
            </em>
        </p>
        <p>
            <label for="pitimeout"><?php p($l->t('Timeout')); ?></label>
            <input id="pitimeout" type="number" min="1" placeholder="<?php p($l->t('default is 5')); ?>">
            <em>
                <?php p($l->t('
                    Sets timeout to privacyIDEA for login in seconds.
                ')); ?>
            </em>
        </p>
        <p>
            <input type="radio" name="inexclude" id="piinclude"><?php p($l->t('Include or ')); ?>
            <input type="radio" name="inexclude" id="piexclude"><?php p($l->t('Exclude')); ?>
            <label for="piexcludegroups"><?php p($l->t(' these groups from two factor authentication')); ?></label>
            <input type="text" id="piexcludegroups" width="300px"   />
            <em>
                <?php p($l->t('
                    If Include is selected, just the groups in this field need to do 2FA.
                    If you select Exclude, these groups can use 1FA (all others need 2FA).
                ')); ?>
            </em>
        <p>
            <label for="piurl"><?php p($l->t('URL of the privacyIDEA Server')); ?></label>
            <input type="text" id="piurl" width="300px"/>
            <em>
                <?php p($l->t('
                    Please use the base URL of your privacyIDEA instance.
                    For compatibility reasons, you may also specify the URL of the /validate/check endpoint.
                ')); ?>
            </em>
        </p>

        <p>
            <input id="checkssl" type="checkbox" class="checkbox">
            <label for="checkssl">
                <?php p($l->t('
                    Verify the SSL certificate.
                    Do not uncheck this in productive environments!
                ')); ?>
            </label>
        </p>

        <p>
            <input id="noproxy" type="checkbox" class="checkbox">
            <label for="noproxy">
                <?php p($l->t('
                    Ignore the system wide proxy settings and send authentication
                    requests to privacyIDEA directly.
                ')); ?>
            </label>
        </p>

        <p>
            <label for="pirealm"><?php p($l->t('User Realm in privacyIDEA (other than default)')); ?></label>
            <input type="text" id="pirealm" size="40"/>
        </p>
        <hr>
        <h3>
            <?php p($l->t('Test')); ?>
        </h3>
        <p>
            <?php p($l->t('Test Authentication by supplying username and password that are checked against privacyIDEA:')); ?>
            <table>
            <tr>
                <td>
                    <label for="pitestauthentication_user"><?php p($l->t('User')); ?></label> &nbsp;
                </td>
                <td>
                    <input type="text" id="pitestauthentication_user" size="40" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="pitestauthentication_password"><?php p($l->t('Password')); ?></label> &nbsp;
                </td>
                <td>
                    <input type="password" id="pitestauthentication_password" size="40" />
                </td>
            </tr>
            </table>
            <input id="pitestauthentication" type="button" value="<?php p($l->t('Test')); ?>" />
            <span id="pitestauthentication_msg" class="msg"></span>
        </p>
        <hr>
        <h3>
            <?php p($l->t('Challenge Response')); ?>
        </h3>
        <p>
            <input id="triggerchallenges" type="checkbox" class="checkbox">
            <label for="triggerchallenges">
                <?php p($l->t('Trigger challenges for challenge-response tokens. Check this if you employ, e.g., SMS or E-Mail tokens.')); ?>
            </label>
        </p>
        <div id="piserviceaccount_credentials">

            <table>
                <tr>
                    <td>
                        <label for="piserviceaccount_user"><?php p($l->t('Username of privacyIDEA service account')); ?></label> &nbsp;
                    </td>
                    <td>
                        <input id="piserviceaccount_user" type="text" size="40"/>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="piserviceaccount_password"><?php p($l->t('Password of privacyIDEA service account')); ?></label> &nbsp;
                    </td>
                    <td>
                        <input id="piserviceaccount_password" type="password" size="40" autocomplete="new-password" />
                    </td>
                </tr>
            </table>
            <input id="piserviceaccount_test" type="button" value="<?php p($l->t('Check Credentials')); ?>" />
            <span id="piserviceaccount_msg" class="msg"></span>
        </div>
    </div>
</div>
