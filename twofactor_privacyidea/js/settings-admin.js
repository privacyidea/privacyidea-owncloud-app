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
    $.get(OC.generateUrl(BASE_URL + 'url')).done(
            function(result) {
                $("#piSettings #piurl").val(result);
            }
            );    
    $.get(OC.generateUrl(BASE_URL + 'checkssl')).done(
            function(result) {
                $("#piSettings #checkssl").prop('checked', result === "1");
            }
            );    
    
    $.get(OC.generateUrl(BASE_URL + 'realm')).done(
            function(result) {
                $("#piSettings #pirealm").val(result);
            }
            );    
    
        $("#piSettings #checkssl").change(function() {
                $.post(
                        OC.generateUrl(BASE_URL + 'checkssl'),
                        {
                            checkssl: $(this).is(":checked")
                        });
        });
        $("#piSettings #noproxy").change(function() {
            $.post(OC.generateUrl(BASE_URL + 'noproxy'),{
                noproxy: $(this).is(":checked")
            });
        });
        $("#piSettings #piurl").keyup(function() {
            // We simple save the value always ;-)
            console.log("pi: Saving URL");
            var value = $("#piSettings #piurl").val();
            console.log(value);
            $.post(OC.generateUrl(BASE_URL + 'url'),
            {
                url: value
            });                            
        });
        
        $("#piSettings #pirealm").keyup(function() {
            // We simple save the value always ;-)
            console.log("pi: Saving Realm");
            var value = $("#piSettings #pirealm").val();
            console.log(value);
            $.post(OC.generateUrl(BASE_URL + 'realm'),
            {
                realm: value
            });                            
        });

});
