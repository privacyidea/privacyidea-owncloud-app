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

var BASE_URL = '/apps/twofactor_privacyidea/';

$(document).ready(function () {
    /* Use the /getValue API call to retrieve a string value from the app config */
    var getValue = function (key, callback) {
        $.get(OC.generateUrl(BASE_URL + 'getValue'), {key: key}).done(
            function(result) {
                callback(result);
            }
        );
    };
    /* Use the /setValue API call to set a string value in the app config. */
    var setValue = function (key, value) {
        OC.msg.startSaving('#pi_settings_msg');
        $.post(OC.generateUrl(BASE_URL + 'setValue'), {
            key: key,
            value: value
        }, function (data) {
            OC.msg.finishedSuccess('#pi_settings_msg', "Saved");
        });
    };

    /* privacyIDEA instance URL */
    getValue("url", function (url) {
       $("#piSettings #piurl").val(url);
    });
    $("#piSettings #piurl").change(function() {
        // We simple save the value always ;-)
        console.log("pi: Saving URL");
        var value = $("#piSettings #piurl").val();
        console.log(value);
        setValue("url", value);
    });

    /* "Check SSL" checkbox */
    getValue("checkssl", function (checkssl) {
        /* NOTE: We check for `!== "0"` instead of `=== "1"` here in order to be consistent with the Provider. */
        $("#piSettings #checkssl").prop('checked', checkssl !== "0");
    });
    $("#piSettings #checkssl").change(function() {
        setValue("checkssl", $(this).is(":checked") ? "1" : "0");
    });

    /* Activate privacyIDEA */
    getValue("piactive", function(piactive) {
        $("#piSettings #piactive").prop('checked', piactive === "1");
    });
    $('#piSettings #piactive').change(function() {
       setValue("piactive", $(this).is(":checked") ? "1" : "0");
    });

    /* "Bypass Proxy" checkbox */
    getValue("noproxy", function (noproxy) {
        $("#piSettings #noproxy").prop('checked', noproxy === "1");
    });
    $("#piSettings #noproxy").change(function() {
        setValue("noproxy", $(this).is(":checked") ? "1" : "0");
    });

    /* privacyIDEA realm */
    getValue("realm", function (realm) {
        $("#piSettings #pirealm").val(realm);
    });
    $("#piSettings #pirealm").change(function() {
        // We simple save the value always ;-)
        console.log("pi: Saving Realm");
        var value = $("#piSettings #pirealm").val();
        console.log(value);
        setValue("realm", value);
    });

    /* Enable/Disable challenge triggering */
    var displayServerCredentials = function (show) {
        if(show) {
            $("#piserviceaccount_credentials").show();
        } else {
            $("#piserviceaccount_credentials").hide();
        };
    };

    getValue("triggerchallenges", function (trigger) {
        var value = (trigger === "1");
        $("#piSettings #triggerchallenges").prop('checked', value);
        displayServerCredentials(value);
    });

    $("#piSettings #triggerchallenges").change(function() {
        var checked = $(this).is(":checked");
        setValue("triggerchallenges", checked ? "1" : "0");
        displayServerCredentials(checked);
    });
    
    /* exclude owncloud user groups */
    getValue("piexcludegroups", function (excludegroups) {
       $("#piSettings #piexcludegroups").val(excludegroups);
    });

    $("#piSettings #piexcludegroups").change(function () {
       console.log("pi: Saving Excluse groups");
       var value = $("#piSettings #piexcludegroups").val();
       setValue("piexcludegroups", value);
    });

    /* privacyIDEA service account username */
    getValue("serviceaccount_user", function (user) {
        $("#piSettings #piserviceaccount_user").val(user);
    });

    $("#piSettings #piserviceaccount_user").change(function () {
        console.log("pi: Saving Service Account User");
        var value = $("#piSettings #piserviceaccount_user").val();
        setValue("serviceaccount_user", value);
    });

    /* privacyIDEA service account password */
    getValue("serviceaccount_password", function (password) {
        $("#piSettings #piserviceaccount_password").val(password);
    });

    $("#piSettings #piserviceaccount_password").change(function () {
        console.log("pi: Saving Service Account Password");
        var value = $("#piSettings #piserviceaccount_password").val();
        setValue("serviceaccount_password", value);
    });
});
