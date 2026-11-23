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

function autoSubmitByLength()
{
    if (piGetValue('otp').length === parseInt(piGetValue("autoSubmitOtpLength")))
    {
        document.forms["piLoginForm"].submit();
    }
}

function piEventListeners()
{
    // The template does not render the OTP field when the user has no OTP token. Then the buttons below
    // must still work, so do not let a missing field stop this function.
    const otpField = document.getElementById("otp");
    if (otpField)
    {
        otpField.addEventListener("keyup", autoSubmitByLength);
    }

    // BUTTON LISTENERS
    // "New challenge" shows after a declined, expired or refused challenge. The server forgets the old challenges.
    const newChallengeButton = document.getElementById("newChallengeButton");
    if (newChallengeButton)
    {
        newChallengeButton.addEventListener("click", function ()
        {
            piSetValue("newChallenge", "1");
            document.forms["piLoginForm"].submit();
        });
    }
    // "Register passkey" tries the registration of a passkey again. Some browsers want a click for it.
    const passkeyRegistrationButton = document.getElementById("passkeyRegistrationButton");
    if (passkeyRegistrationButton)
    {
        passkeyRegistrationButton.addEventListener("click", function ()
        {
            window.piRegisterPasskey();
        });
    }
    // "Cancel enrollment" skips an enrollment that privacyIDEA marked as optional.
    const cancelEnrollmentButton = document.getElementById("cancelEnrollmentButton");
    if (cancelEnrollmentButton)
    {
        cancelEnrollmentButton.addEventListener("click", function ()
        {
            piSetValue("cancelEnrollment", "1");
            document.forms["piLoginForm"].submit();
        });
    }
    document.getElementById("u2fButton").addEventListener("click", function ()
    {
        piChangeMode("u2f");
    });
    document.getElementById("webAuthnButton").addEventListener("click", function ()
    {
        piChangeMode("webauthn");
    });
    document.getElementById("passkeyButton").addEventListener("click", function ()
    {
        // Some browsers start the passkey prompt only for a click on the page. Do not reload the page first.
        // The push and TiQR pages reload by themselves, so they need the reload to stop it.
        if (piGetValue("mode") === "push" || piGetValue("mode") === "tiqr")
        {
            piChangeMode("passkey");
        }
        else
        {
            window.piDoPasskey();
        }
    });
    document.getElementById("pushButton").addEventListener("click", function ()
    {
        piChangeMode("push");
    });
    document.getElementById("tiqrButton").addEventListener("click", function ()
    {
        piChangeMode("tiqr");
    });
    document.getElementById("otpButton").addEventListener("click", function ()
    {
        piChangeMode("otp");
    });
}

// Wait until the document is ready
document.addEventListener("DOMContentLoaded", function ()
{
    piEventListeners();
});