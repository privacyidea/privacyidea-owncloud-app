<?php
script('twofactor_privacyidea', 'settings-admin');
?>

<div class="section" id="privacyIDEA">
    <h2 style="display: inline-block">
        <span style="float: left"><?php p($l->t('privacyIDEA 2FA')); ?></span>
        <a target="_blank" rel="noreferrer" class="icon-info svg"
           title="<?php p($l->t('Open documentation')); ?>"
           href="https://privacyidea.readthedocs.io"></a></h2>
    <span id="pi_settings_msg" class="msg"></span>
    <p>
        <em>
            <?php p($l->t('
                In a second step of authentication the user is asked to provide a one
                time password. The users devices are managed in privacyIDEA. The
                authentication request is forwarded to privacyIDEA.
            ')); ?>
        </em>
    </p>
    <hr>
    <h3>
        <?php p($l->t('Configuration')); ?>
    </h3>
    <div id="piSettings">
        <table>
            <tr>
                <td colspan="2">
                    <input id="piactive" type="checkbox" class="checkbox">
                    <label for="piactive"><?php p($l->t('Activate two factor authentication with privacyIDEA.')); ?></label>
                </td>
                <td>
                    <em>
                        <?php p($l->t('
                            Before activating two factor authentication with privacyIDEA, please asure, that the connection to
                            your privacyIDEA-server is configured correctly.
                        ')); ?>
                    </em>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="piurl"><?php p($l->t('URL of the privacyIDEA Server')); ?></label>
                </td>
                <td>
                    <input type="text" id="piurl" width="300px"/>
                </td>
                <td>
                    <em>
                        <?php p($l->t('
                            Please use the base URL of your privacyIDEA instance.
                            For compatibility reasons, you may also specify the URL of the /validate/check endpoint.
                        ')); ?>
                    </em>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="pitimeout"><?php p($l->t('Timeout')); ?></label>
                </td>
                <td>
                    <input id="pitimeout" type="number" min="1" placeholder="<?php p($l->t('default is 5')); ?>">
                </td>
                <td>
                    <em>
                        <?php p($l->t('
                            Sets timeout to privacyIDEA for login in seconds.
                        ')); ?>
                    </em>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="radio" name="inexclude" id="piinclude">
                    <label for="piinclude"><?php p($l->t('Include groups')); ?></label>
                    <br>
                    <input type="radio" name="inexclude" id="piexclude">
                    <label for="piexclude"><?php p($l->t('Exclude groups')); ?></label>
                </td>
                <td>
                    <label for="piexcludegroups"></label><input type="text" id="piexcludegroups" width="300px"/>
                </td>
                <td>
                    <em>
                        <?php p($l->t('
		                    If include is selected, just the groups in this field need to do 2FA.
		                ')); ?>
                        <br>
                        <?php p($l->t('
		                    If you select exclude, these groups can use 1FA (all others need 2FA).
		                ')); ?>
                    </em>
                </td>
            </tr>
            <tr>
                <td>
                    <?php p($l->t('
		                    Exclude ip addresses
		                ')); ?>
                </td>
                <td>
                    <label for="piexcludeips"></label><input type="text" id="piexcludeips" width="300px"/>
                </td>
                <td>
                    <em>
                        <?php p($l->t('
		                    You can either add single IPs like 10.0.1.12,10.0.1.13, a range like 10.0.1.12-10.0.1.113 or combinations like 10.0.1.12-10.0.1.113,192.168.0.15
		                ')); ?>
                    </em>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="pirealm"><?php p($l->t('User Realm')); ?></label>
                </td>
                <td>
                    <input type="text" id="pirealm" size="40"/>
                </td>
                <td>
                    <em>
                        <?php p($l->t('
                    Select the user realm, if it is not the default one.
                ')); ?>
                    </em>
                </td>
            </tr>
        </table>
        <input id="checkssl" type="checkbox" class="checkbox" checked>
        <label for="checkssl">
            <?php p($l->t('
                    Verify the SSL certificate.
                ')); ?>
            <em>
                <?php p($l->t('
                        Do not uncheck this in productive environments!
                    ')); ?>
            </em>
        </label>
        <br>
        <input id="noproxy" type="checkbox" class="checkbox">
        <label for="noproxy">
            <?php p($l->t('Ignore the system wide proxy settings and send authentication requests to privacyIDEA directly.')); ?>
        </label>
        <hr>
        <p>
            <?php p($l->t('Test authentication by supplying username and password that are checked against privacyIDEA:')); ?>
        </p>
        <table>
            <tr>
                <td>
                    <label for="pitestauthentication_user"><?php p($l->t('User')); ?></label> &nbsp;
                </td>
                <td>
                    <input type="text" id="pitestauthentication_user" size="40"/>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="pitestauthentication_password"><?php p($l->t('Password')); ?></label> &nbsp;
                </td>
                <td>
                    <input type="password" id="pitestauthentication_password" size="40"/>
                </td>
            </tr>
        </table>
        <input id="pitestauthentication" type="button" value="<?php p($l->t('Test')); ?>"/>
        <span id="pitestauthentication_msg" class="msg"></span>
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

        <p>
            <input id="passOnNoUser" type="checkbox" class="checkbox">
            <label for="passOnNoUser">
                <?php p($l->t('Let the user log in if the user is not found in privacyIDEA.')); ?>
            </label>
        </p>

        <p>
            <input id="autoSubmitOTPLength" type="text" size="20"/>
            <label for="autoSubmitOTPLength">
                <?php p($l->t('If you want to turn on the form-auto-submit function after x number of characters 
                are entered into the OTP input field, set the expected OTP length here. 
                Note: Only digits as the parameter\'s value allowed here.')); ?>
            </label>
        </p>

        <div id="piserviceaccount_credentials">
            <table>
                <tr>
                    <td>
                        <label for="piserviceaccount_user"><?php p($l->t('Username of privacyIDEA service account')); ?> </label>&nbsp;
                    </td>
                    <td>
                        <input id="piserviceaccount_user" type="text" size="40"/>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="piserviceaccount_password"><?php p($l->t('Password of privacyIDEA service account')); ?></label>
                        &nbsp;
                    </td>
                    <td>
                        <input id="piserviceaccount_password" type="password" size="40" autocomplete="new-password"/>
                    </td>
                </tr>
            </table>
            <input id="piserviceaccount_test" type="button" value="<?php p($l->t('Check Credentials')); ?>"/>
            <span id="piserviceaccount_msg" class="msg"></span>
        </div>
        <hr>
        <hr>
    </div>
</div>
