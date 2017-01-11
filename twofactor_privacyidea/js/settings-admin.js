 /**
 * @author Cornelius Kölbel <cornelius.koelbel@netknights.it>
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

var APP_NAME = "twofactor_privacyidea";

$(document).ready(function () {
    OC.AppConfig.getValue(APP_NAME, "url", function (url) {
       $("#piSettings #piurl").val(url);
    });
    $("#piSettings #piurl").keyup(function() {
        // We simple save the value always ;-)
        console.log("pi: Saving URL");
        var value = $("#piSettings #piurl").val();
        console.log(value);
        OC.AppConfig.setValue(APP_NAME, "url", value);
    });

    OC.AppConfig.getValue(APP_NAME, "checkssl", function (checkssl) {
        $("#piSettings #checkssl").prop('checked', checkssl === "1");
    });
    $("#piSettings #checkssl").change(function() {
        OC.AppConfig.setValue(APP_NAME, "checkssl", $(this).is(":checked") ? "1" : "0");
    });

    OC.AppConfig.getValue(APP_NAME, "noproxy", function (noproxy) {
        $("#piSettings #noproxy").prop('checked', noproxy === "1");
    });
    $("#piSettings #noproxy").change(function() {
        OC.AppConfig.setValue(APP_NAME, "noproxy", $(this).is(":checked") ? "1" : "0");
    });

    OC.AppConfig.getValue(APP_NAME, "realm", function (realm) {
        $("#piSettings #pirealm").val(realm);
    });
    $("#piSettings #pirealm").keyup(function() {
        // We simple save the value always ;-)
        console.log("pi: Saving Realm");
        var value = $("#piSettings #pirealm").val();
        console.log(value);
        OC.AppConfig.setValue(APP_NAME, "realm", value);
    });

    var displayServerCredentials = function (show) {
        if(show) {
            $("#piserveradmin_credentials").show();
        } else {
            $("#piserveradmin_credentials").hide();
        };
    };

    OC.AppConfig.getValue(APP_NAME, "triggerchallenges", function (trigger) {
        var value = (trigger === "1");
        $("#piSettings #triggerchallenges").prop('checked', value);
        displayServerCredentials(value);
    });

    $("#piSettings #triggerchallenges").change(function() {
        var checked = $(this).is(":checked");
        OC.AppConfig.setValue(APP_NAME, "triggerchallenges", checked ? "1" : "0");
        displayServerCredentials(checked);
    });

    OC.AppConfig.getValue(APP_NAME, "serveradmin_user", function (user) {
        $("#piSettings #piserveradmin_user").val(user);
    });

    $("#piSettings #piserveradmin_user").keyup(function () {
        console.log("pi: Saving Server Admin User");
        var value = $("#piSettings #piserveradmin_user").val();
        OC.AppConfig.setValue(APP_NAME, "serveradmin_user", value);
    });

    OC.AppConfig.getValue(APP_NAME, "serveradmin_password", function (password) {
        $("#piSettings #piserveradmin_password").val(password);
    });

    $("#piSettings #piserveradmin_password").keyup(function () {
        console.log("pi: Saving Server Admin Password");
        var value = $("#piSettings #piserveradmin_password").val();
        OC.AppConfig.setValue(APP_NAME, "serveradmin_password", value);
    });
});
