/**
 * @author Lukas Matusiewicz <lukas.matusiewicz@netknights.it>
 *
 * @license AGPL-3.0
 *
 * This code is a free software: You can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

function autoSubmit()
{
    if (piGetValue('otp').length === parseInt(piGetValue("autoSubmitOtpLength")))
    {
        document.forms["piLoginForm"].submit();
    }
}

function piEventListeners()
{
    document.getElementById("otp").addEventListener("keyup", autoSubmit);

    // BUTTON LISTENERS
    document.getElementById("useU2FButton").addEventListener("click", function ()
    {
        piChangeMode("u2f");
    });
    document.getElementById("useWebAuthnButton").addEventListener("click", function ()
    {
        piChangeMode("webauthn");
    });
    document.getElementById("usePushButton").addEventListener("click", function ()
    {
        piChangeMode("push");
    });
    document.getElementById("useTiQRButton").addEventListener("click", function ()
    {
        piChangeMode("tiqr");
    });
    document.getElementById("useOTPButton").addEventListener("click", function ()
    {
        piChangeMode("otp");
    });
}

// Wait until the document is ready
document.addEventListener("DOMContentLoaded", function ()
{
    piEventListeners();
});