<?php

/**
 * @author Cornelius Kölbel <cornelius.koelbel@netknights.it>
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

namespace OCA\TwoFactor_privacyIDEA\Provider;

use Exception;
use OCA\TwoFactor_privacyIDEA\Util\IpMatcher;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\TwoFactorException;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\Template;

class TwoFactorPrivacyIDEAProvider implements IProvider
{
    /** @var string[] We can enter multiple strings, the version number should start with */
    private $recommendedPIVersions = array("3.");
    /** @var string[] Session keys that hold the state of one login. They are removed after a successful login. */
    private const SESSION_KEYS = [
        "pi_message", "pi_TriggerChallengeSuccess", "pi_detail", "pi_mode", "pi_autoSubmit", "pi_hideOTPField",
        "pi_u2fSignRequest", "pi_webAuthnSignRequest", "pi_pushAvailable", "pi_otpAvailable", "pi_tiqrAvailable",
        "pi_tiqrImage", "pi_imgU2F", "pi_imgWebauthn", "pi_imgPush", "pi_imgOTP", "pi_transactionId",
        "pi_pollInBrowserFailed", "pi_errorMessage", "pi_loadCounter", "pi_preferredClientMode", "pi_noAuthRequired",
        "pi_uid", "pi_passkeyChallenge", "pi_passkeyTransactionId", "pi_passkeyFetchedAt", "pi_passkeyTriggeredAt",
        "pi_passkeyFailedAt", "pi_enrollImage", "pi_enrollLink", "pi_noAutoSubmit", "pi_offerNewChallenge",
        "pi_challengeTime", "pi_pinChangeSeen", "pi_passkeyMessage", "pi_passkeyRegistration",
        "pi_passkeyRegistrationSerial", "pi_passkeyRegistrationFailed", "pi_enrollOptional", "pi_enrolling", "pi_plainInput"];
    /** @var string[] Session keys of the challenges of one answer. An answer with new challenges replaces all of them. */
    private const CHALLENGE_KEYS = [
        "pi_u2fSignRequest", "pi_imgU2F", "pi_webAuthnSignRequest", "pi_imgWebauthn", "pi_pushAvailable", "pi_imgPush",
        "pi_tiqrAvailable", "pi_tiqrImage", "pi_otpAvailable", "pi_imgOTP", "pi_enrollImage", "pi_enrollLink",
        "pi_noAutoSubmit", "pi_offerNewChallenge", "pi_passkeyRegistration", "pi_passkeyRegistrationSerial",
        "pi_passkeyRegistrationFailed", "pi_enrollOptional", "pi_enrolling", "pi_plainInput"];
    /** @var string[] Token types that ask for an OTP. Auto submit is only for a challenge of these types. */
    private const OTP_TOKEN_TYPES = ["hotp", "totp", "email", "sms", "motp", "yubikey", "yubico", "daplug", "tan", "vasco"];
    /** @var int privacyIDEA keeps a challenge for 120 seconds by default. After this time, the page stops waiting
     *           for a push or TiQR confirmation. The time in the answer (valid_until) has no time zone. */
    private const CHALLENGE_VALIDITY = 120;
    /** @var int The wait for the rollout of a push token on the phone. Scanning the QR code takes longer than a login. */
    private const ENROLLMENT_WAIT = 600;
    /** @var int A passkey challenge (from /validate/initialize or triggered) is replaced after this many seconds.
     *           privacyIDEA keeps a challenge for 120 seconds by default. */
    private const PASSKEY_CHALLENGE_MAX_AGE = 60;
    /** @var int After a failed request to /validate/initialize, the app waits this many seconds before it asks again. */
    private const PASSKEY_RETRY_AFTER_FAILURE = 60;
    /** @var IClientService */
    private $httpClientService;
    /** @var IConfig */
    private $config;
    /** @var ILogger */
    private $logger;
    /** @var IL10N */
    private $trans;
    /** @var ISession Every key needs "pi_" as a prefix to avoid collisions */
    private $session;
    /** @var IRequest */
    private $request;
    /** @var IGroupManager */
    private $groupManager;

    /**
     * @param IClientService $httpClientService
     * @param IConfig $config
     * @param ILogger $logger
     * @param IRequest $request
     * @param IGroupManager $groupManager
     * @param IL10N $trans
     * @param ISession $session
     */
    public function __construct(IClientService $httpClientService,
                                IConfig        $config,
                                ILogger        $logger,
                                IRequest       $request,
                                IGroupManager  $groupManager,
                                IL10N          $trans,
                                ISession       $session)
    {
        $this->httpClientService = $httpClientService;
        $this->config = $config;
        $this->logger = $logger;
        $this->trans = $trans;
        $this->request = $request;
        $this->groupManager = $groupManager;
        $this->session = $session;
    }

    /**
     * Get the template for rending the 2FA provider view
     *
     * @param IUser $user
     * @return Template
     */
    public function getTemplate(IUser $user): Template
    {
        $this->bindSessionToUser($user);

        // TriggerChallenge
        $triggerFailed = false;
        if ($this->getAppValue("triggerchallenges", "") === "1")
        {
            if ($this->session->get("pi_TriggerChallengeSuccess") !== true)
            {
                try
                {
                    $message = $this->triggerChallenge($user->getUID());

                    // Check if the user was actually found when triggering challenges
                    // If not and the setting "passOnNoToken" is set, the user can log in without 2FA
                    $this->session->set("pi_message", $message);
                    $this->session->set("pi_TriggerChallengeSuccess", true);
                }
                catch (\Throwable $e)
                {
                    // Show the OTP field again. Without it the user can not enter anything.
                    // \Throwable and not Exception: on PHP 8 a wrong value in the settings or an unexpected answer
                    // can raise a TypeError, and that must not end in an internal server error.
                    $this->session->set("pi_hideOTPField", false);
                    $this->log("error", "[getTemplate] " . $e->getMessage());
                    $message = $this->userMessage($e);
                    $triggerFailed = true;
                }
            }
        }

        // The passkey button. Without a triggered passkey challenge, ask privacyIDEA for one that belongs to no user.
        // If the trigger failed, privacyIDEA does not work for this user, so do not ask again.
        if (!$triggerFailed)
        {
            $this->offerPasskey();
        }

        // Set options, tokens and load counter to the template
        $template = new Template("twofactor_privacyidea", "pi-form-template");

        if ($this->session->get("pi_detail") !== null)
        {
            $template->assign("detail", $this->session->get("pi_detail"));
        }
        if (isset($message))
        {
            $template->assign("message", $message);
        }
        if ($this->session->get("pi_mode") !== null)
        {
            $template->assign("mode", $this->session->get("pi_mode"));
        }
        if ($this->session->get("pi_autoSubmit") !== null)
        {
            $template->assign("autoSubmit", $this->session->get("pi_autoSubmit"));
        }
        if ($this->session->get("pi_hideOTPField") !== null)
        {
            $template->assign("hideOTPField", $this->session->get("pi_hideOTPField"));
        }
        if ($this->session->get("pi_u2fSignRequest") !== null)
        {
            $template->assign("u2fSignRequest", json_encode($this->session->get("pi_u2fSignRequest")));
        }
        if ($this->session->get("pi_webAuthnSignRequest") !== null)
        {
            $template->assign("webAuthnSignRequest", json_encode($this->session->get("pi_webAuthnSignRequest")));
        }
        if ($this->session->get("pi_passkeyChallenge") !== null)
        {
            $template->assign("passkeyChallenge", json_encode($this->session->get("pi_passkeyChallenge")));
            // The text of the challenge of the button: the text of privacyIDEA, or a local text if it sends none.
            // A triggered challenge shows its text in the message of the page already.
            if ($this->session->get("pi_passkeyFetchedAt") !== null)
            {
                $passkeyMessage = (string)$this->session->get("pi_passkeyMessage");
                $template->assign("passkeyMessage",
                    $passkeyMessage !== "" ? $passkeyMessage : $this->trans->t("Log in with your passkey."));
            }
        }
        if ($this->session->get("pi_pushAvailable"))
        {
            $template->assign("pushAvailable", $this->session->get("pi_pushAvailable"));
        }
        if ($this->session->get("pi_otpAvailable"))
        {
            $template->assign("otpAvailable", $this->session->get("pi_otpAvailable"));
        }
        if ($this->session->get("pi_tiqrAvailable"))
        {
            $template->assign("tiqrAvailable", $this->session->get("pi_tiqrAvailable"));
        }
        if ($this->session->get("pi_tiqrImage") !== null)
        {
            $template->assign("tiqrImage", $this->session->get("pi_tiqrImage"));
        }
        if ($this->session->get("pi_imgU2F") !== null)
        {
            $template->assign("imgU2F", $this->session->get("pi_imgU2F"));
        }
        if ($this->session->get("pi_imgWebauthn") !== null)
        {
            $template->assign("imgWebauthn", $this->session->get("pi_imgWebauthn"));
        }
        if ($this->session->get("pi_imgPush") !== null)
        {
            $template->assign("imgPush", $this->session->get("pi_imgPush"));
        }
        if ($this->session->get("pi_imgOTP") !== null)
        {
            $template->assign("imgOTP", $this->session->get("pi_imgOTP"));
        }
        // The QR code and the link of a token that the user enrolls show in every mode.
        if ($this->session->get("pi_enrollImage") !== null)
        {
            $template->assign("enrollImage", $this->session->get("pi_enrollImage"));
        }
        if ($this->session->get("pi_enrollLink") !== null)
        {
            $template->assign("enrollLink", $this->session->get("pi_enrollLink"));
            $template->assign("enrollLinkLabel", $this->trans->t("Open the link in the authenticator app"));
        }
        // The registration of a passkey at the login. It starts by itself, unless privacyIDEA refused an earlier try.
        if ($this->session->get("pi_passkeyRegistration") !== null)
        {
            $template->assign("passkeyRegistration", json_encode($this->session->get("pi_passkeyRegistration")));
            $template->assign("passkeyRegistrationAutoStart",
                $this->session->get("pi_passkeyRegistrationFailed") === true ? "0" : "1");
            $template->assign("passkeyRegistrationLabel", $this->trans->t("Register passkey"));
        }
        // privacyIDEA marked the enrollment as optional. The user can skip it.
        if ($this->session->get("pi_enrollOptional") === true)
        {
            $template->assign("cancelEnrollment", $this->trans->t("Cancel enrollment"));
        }
        // The first step of an email or SMS enrollment asks for the address or the phone number.
        if ($this->session->get("pi_plainInput") === true)
        {
            $template->assign("plainInput", true);
        }
        // After a declined, expired or refused challenge, the user can ask for a new one.
        if ($this->session->get("pi_offerNewChallenge") === true)
        {
            $template->assign("newChallenge", $this->trans->t("New challenge"));
        }

        // Auto submit only for a challenge that asks for an OTP, not for a PIN, an email address or a phone number.
        $template->assign("autoSubmitOtpLength",
            $this->session->get("pi_noAutoSubmit") === true ? "" : $this->getAppValue("autoSubmitOtpLength", ""));
        $template->assign("pollInBrowser", $this->getAppValue("pollInBrowser", ""));
        $template->assign("pollInBrowserUrl", $this->getAppValue("pollInBrowserUrl", ""));

        if ($this->session->get("pi_transactionId") !== null)
        {
            $template->assign("transactionId", $this->session->get("pi_transactionId"));
        }
        if ($this->session->get("pi_pollInBrowserFailed") !== null)
        {
            $template->assign("pollInBrowserFailed", $this->session->get("pi_pollInBrowserFailed"));
        }
        else
        {
            $template->assign("pollInBrowserFailed", $this->getAppValue("pollInBrowserFailed", false));
        }
        if ($this->session->get("pi_errorMessage") !== null)
        {
            $template->assign("errorMessage", $this->session->get("pi_errorMessage"));
        }
        else
        {
            $template->assign("errorMessage", $this->getAppValue("errorMessage", ""));
        }

        $loads = 1;
        if ($this->session->get("pi_loadCounter") !== null)
        {
            $loads = $this->session->get("pi_loadCounter");
        }
        $template->assign("loadCounter", $loads);

        // Add translations
        $template->assign("verify", $this->trans->t("Verify"));
        $template->assign("alternateLoginOptions", $this->trans->t("Alternate Login Options"));

        return $template;
    }

    /**
     * Verify the given challenge.
     *
     * @param IUser $user
     * @param string $challenge
     * @return Bool True in case of success. In case of failure, this raises
     *         a TwoFactorException with an error message for the user.
     *         Every other error (privacyIDEA is not reachable, the answer is wrong) is logged
     *         and also raised as a TwoFactorException. ownCloud shows its message on the login page.
     * @throws TwoFactorException
     */
    public function verifyChallenge(IUser $user, $challenge): bool
    {
        try
        {
            $result = $this->verifyChallengeInternal($user, $challenge);
        }
        catch (TwoFactorException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            $this->log("error", "[verifyChallenge] " . $e->getMessage());
            throw new TwoFactorException($this->userMessage($e));
        }

        if ($result)
        {
            // The login is done. Remove the state, so that no old value is left in the session.
            $this->clearLoginState();
        }
        return $result;
    }

    /**
     * @param IUser $user
     * @param string $challenge
     * @return bool
     * @throws TwoFactorException|ProcessPIResponseException|Exception
     */
    private function verifyChallengeInternal(IUser $user, $challenge): bool
    {
        $this->bindSessionToUser($user);

        if ($this->session->get("pi_noAuthRequired"))
        {
            return true;
        }

        // The button "New challenge": forget the old challenges. The next page triggers new ones, or waits for the PIN.
        if ($this->request->getParam("newChallenge") === "1")
        {
            $this->startNewChallenge();
        }

        $password = $challenge;
        $username = $user->getUID();

        // Get mode and transactionID
        $transactionID = $this->session->get("pi_transactionId");
        $mode = $this->request->getParam("mode");
        $this->session->set("pi_mode", $mode);

        if ($this->request->getParam("modeChanged") === "1")
        {
            throw new TwoFactorException((string)$this->session->get("pi_message"));
        }

        $ret = 0;

        if ($this->request->getParam("cancelEnrollment") === "1")
        {
            // The button "Cancel enrollment". It shows only if privacyIDEA marked the enrollment as optional.
            $this->session->set("pi_mode", "otp");
            if ($this->session->get("pi_enrollOptional") !== true || empty($transactionID))
            {
                $this->log("error", "[verifyChallenge] Cancel of an enrollment that is not optional. The app does not send it.");
            }
            else
            {
                $ret = $this->cancelEnrollment((string)$transactionID);
            }
        }
        elseif ($mode === "passkeyRegistration")
        {
            // If privacyIDEA refuses the registration, the next page does not start it again by itself.
            // The button "Register passkey" tries again.
            $this->session->set("pi_passkeyRegistrationFailed", true);
            $registrationResponse = json_decode((string)$this->request->getParam("passkeyRegistrationResponse"), true);
            $origin = $this->checkedOrigin();
            $serial = (string)$this->session->get("pi_passkeyRegistrationSerial");
            if (!is_array($registrationResponse) || empty($transactionID) || $serial === "")
            {
                $this->log("error", "Incomplete data for the passkey registration: response, serial or transaction ID is missing!");
            }
            elseif ($origin !== "")
            {
                $ret = $this->completePasskeyRegistration($username, (string)$transactionID, $serial, $registrationResponse, $origin);
            }
        }
        elseif ($mode === "push" || $mode === "tiqr")
        {
            if ($mode === "push")
            {
                $this->log("debug", "We are processing a PUSH response.");
            }
            if ($mode === "tiqr")
            {
                $this->log("debug", "We are processing a TIQR response.");
            }

            try
            {
                $state = $this->pollTransaction((string)$transactionID);
            }
            catch (\Throwable $e)
            {
                // Stop the poll. Otherwise the page polls again and again and shows the error each time.
                $this->session->set("pi_mode", "otp");
                $this->session->set("pi_offerNewChallenge", true);
                throw $e;
            }
            if ($state === "accept")
            {
                // The challenge has been answered. Now we need to verify it.
                $ret = $this->validateCheck($username, "", $transactionID);
            }
            elseif ($state === "declined")
            {
                $this->stopWaiting($this->trans->t("The login was declined on the phone."));
            }
            elseif ($this->challengeIsOld())
            {
                $this->stopWaiting($this->trans->t("The login was not confirmed in time."));
            }
            else
            {
                $this->log("debug", "privacyIDEA: PUSH not confirmed yet");
            }

            // Increase load counter
            if ($this->request->getParam("loadCounter"))
            {
                $counter = $this->request->getParam("loadCounter");
                $this->session->set("pi_loadCounter", $counter + 1);
            }
        }
        elseif ($mode === "u2f")
        {
            $u2fSignResponse = json_decode($this->request->getParam("u2fSignResponse"), true);

            if (empty($u2fSignResponse))
            {
                $this->log("error", "Incomplete data for U2F authentication: u2fSignResponse is missing!");
            }
            else
            {
                $ret = $this->validateCheckU2F($username, $transactionID, $u2fSignResponse);
            }
        }
        elseif ($mode === "passkey")
        {
            // Whatever the result is, the next page starts in the OTP mode. A rejected passkey must not start
            // the prompt of the browser again and again. The user can use the passkey button to try again.
            $this->session->set("pi_mode", "otp");
            $passkeySignResponse = json_decode((string)$this->request->getParam("passkeySignResponse"), true);
            $origin = $this->checkedOrigin();

            // The passkey challenge has its own transaction, not the transaction of the other tokens of the user.
            $passkeyTransactionID = $this->session->get("pi_passkeyTransactionId");
            if (!empty($passkeyTransactionID))
            {
                $transactionID = $passkeyTransactionID;
            }
            // A challenge from /validate/initialize belongs to no user. The app must check the user of the answer.
            // Such a challenge is good for one answer. The next page asks for a new one.
            $unbound = $this->session->get("pi_passkeyFetchedAt") !== null;
            if ($unbound)
            {
                $this->removePasskeyChallenge();
            }

            if (!is_array($passkeySignResponse) || empty($transactionID))
            {
                $this->log("error", "Incomplete data for passkey authentication: passkeySignResponse or transaction ID is missing!");
            }
            elseif ($origin !== "")
            {
                $ret = $this->validateCheckPasskey($username, $transactionID, $passkeySignResponse, $origin, $unbound);
            }
        }
        elseif ($mode === "webauthn")
        {
            $webAuthnSignResponse = json_decode($this->request->getParam("webAuthnSignResponse"), true);
            $origin = $this->checkedOrigin();

            if (empty($webAuthnSignResponse))
            {
                $this->log("error", "Incomplete data for WebAuthn authentication: webAuthnSignResponse is missing!");
            }
            elseif ($origin !== "")
            {
                $ret = $this->validateCheckWebAuthn($username, $transactionID, $webAuthnSignResponse, $origin);
            }
        }
        else
        {
            if ($transactionID)
            {
                $this->log("debug", "transaction_id: " . $transactionID);
                $ret = $this->validateCheck($username, $password, $transactionID);
            }
            else
            {
                $ret = $this->validateCheck($username, $password);
            }
        }

        // Show error from processPIResponse
        if (is_string($ret))
        {
            $errorMessage = $ret;
        }
        elseif (!is_object($ret) || !isset($ret->result))
        {
            // No usable answer. For example, the browser did not send a U2F or WebAuthn response,
            // or the push message is not confirmed yet.
            $errorMessage = ($mode === "push" || $mode === "tiqr")
                ? (string)$this->session->get("pi_message")
                : $this->trans->t("Failed to authenticate.");
        }
        else
        {
            if ($ret->result->status === true)
            {
                if ($ret->result->value === true)
                {
                    $this->log("debug", "privacyIDEA: User authenticated successfully!");
                    return true;
                }
                else
                {
                    $errorMessage = $this->challengeText($ret->detail ?? null);
                    if ($errorMessage === "")
                    {
                        $errorMessage = $this->trans->t("Failed to authenticate.");
                    }
                    $this->log("debug", "privacyIDEA: " . $errorMessage);
                    $this->session->set("pi_message", $errorMessage);
                    // A refused answer to a challenge: maybe the challenge expired. Offer a new one.
                    if (!isset($ret->detail->multi_challenge) && !empty($transactionID) && ($mode === "otp" || empty($mode)))
                    {
                        $this->session->set("pi_offerNewChallenge", true);
                    }
                }
            }
            elseif ($mode === "push" || $mode === "tiqr")
            {
                $errorMessage = (string)$this->session->get("pi_message");
            }
            else
            {
                // status == false
                $code = $ret->result->error->code ?? "";
                $message = $ret->result->error->message ?? "";
                $this->log("error", "[authenticate] privacyIDEA error code: " . $code);
                $this->log("error", "[authenticate] privacyIDEA error message: " . $message);
                $errorMessage = $this->trans->t("Failed to authenticate.") . " " . $message;
            }
        }
        throw new TwoFactorException($errorMessage);
    }

    /**
     * The text that the user sees for an exception.
     * Messages of this app and of privacyIDEA are shown. Messages of the HTTP client and of PHP stay in the log,
     * because they can contain the internal URL of the privacyIDEA server.
     *
     * @param \Throwable $e
     * @return string
     */
    private function userMessage(\Throwable $e): string
    {
        if ($e instanceof AdminAuthException || $e instanceof ProcessPIResponseException || $e instanceof TwoFactorException)
        {
            return $e->getMessage();
        }
        return $this->trans->t("Failed to communicate with privacyIDEA.");
    }

    /**
     * Remove the state of the login from the session.
     */
    private function clearLoginState()
    {
        foreach (self::SESSION_KEYS as $key)
        {
            $this->session->remove($key);
        }
    }

    /**
     * Make sure that the state of the login in the session belongs to this user.
     * If the session holds the state of another user, remove it. The state includes "pi_noAuthRequired",
     * which skips the second factor. ownCloud blocks a second login while a challenge is open, so this is
     * an additional safety net.
     *
     * @param IUser $user
     */
    private function bindSessionToUser(IUser $user)
    {
        $uid = $user->getUID();
        if ($this->session->get("pi_uid") !== $uid)
        {
            $this->clearLoginState();
            $this->session->set("pi_uid", $uid);
        }
    }

    /**
     * Handle validateCheck using user's username, password and if challenge response - transaction ID.
     *
     * @param string $username
     * @param string $pass
     * @param string|null $transactionID
     * @return mixed|string
     * @throws ProcessPIResponseException|Exception
     */
    public function validateCheck(string $username, string $pass, ?string $transactionID = null)
    {
        $options["form_params"] = [
            "user" => $username,
            "transaction_id" => $transactionID,
            "pass" => $pass];

        $res = $this->sendRequest("validate/check", $options);
        return $this->processPIResponse($res);
    }

    /**
     * Ask privacyIDEA to trigger all challenges for a given username via
     * the /validate/triggerchallenge API request.
     * If the request was successful, return a list of messages from privacyIDEA's response.
     * If the request failed with HTTP Code 400 and passOnNoToken is activated, pi_no_auth_required is set to indicate that the
     * authentication can be skipped. Returns an info string in this case that is displayed instead of the message.
     * Otherwise, if the request failed for any reason, a TriggerChallengeException is raised.
     *
     * @param string $username user for which privacyIDEA should trigger challenges
     * @return string
     * @throws AdminAuthException
     * @throws ProcessPIResponseException
     * @throws Exception
     */
    private function triggerChallenge(string $username): string
    {
        $this->session->set("pi_hideOTPField", true);
        $url = $this->getBaseUrl() . "validate/triggerchallenge";
        $options = $this->getClientOptions();
        $adminUser = $this->getAppValue('serviceaccount_user', '');
        $adminPassword = $this->getAppValue('serviceaccount_password', '');
        $realm = $this->getAppValue('realm', '');
        $token = $this->getAuthToken($adminUser, $adminPassword);

        // Send the realm only if it is set, as sendRequest() does for /validate/check. An empty realm makes privacyIDEA
        // ignore the realm in a user name like bob@sales, and then the trigger finds another user than the check.
        $options["form_params"] = ["user" => $username];
        if ($realm !== "")
        {
            $options["form_params"]["realm"] = $realm;
        }
        $options["headers"]["PI-Authorization"] = $token;

        $client = $this->httpClientService->newClient();
        $result = $client->post($url, $options);
        $ret = $this->processPIResponse($result);

        if (is_string($ret))
        {
            return $ret;
        }
        else
        {
            return $this->challengeText($ret->detail ?? null);
        }
    }

    /**
     * The origin for the answer of a WebAuthn token or a passkey.
     * privacyIDEA compares the Origin header of the answer with the origin that the browser signed (clientDataJSON).
     * The header must therefore be the origin of ownCloud, not a value from the browser. Otherwise a page on another
     * host under the same relying party id (for example evil.example.com for example.com) can relay a login.
     * The browser posts the origin of its page. If it is not the origin of ownCloud, the app refuses the answer.
     * Behind a reverse proxy, ownCloud knows its own origin only from overwriteprotocol and overwritehost.
     *
     * @return string The origin of ownCloud, or "" if the browser sent another origin
     */
    private function checkedOrigin(): string
    {
        $server = $this->normalizeOrigin($this->request->getServerProtocol() . "://" . $this->request->getServerHost());
        $posted = $this->normalizeOrigin((string)$this->request->getParam("origin"));
        if ($server === "" || $posted !== $server)
        {
            $this->log("error", "[verifyChallenge] The browser sent the origin \"" . $posted . "\", but the origin of ownCloud is \""
                . $server . "\". The app refuses the answer. Behind a reverse proxy, set overwriteprotocol and overwritehost "
                . "in config.php.");
            return "";
        }
        return $server;
    }

    /**
     * An origin in one form: lower case, without the default port, without a path.
     *
     * @param string $origin For example "https://Cloud.Example.com:443"
     * @return string For example "https://cloud.example.com", or "" if the value is not an origin
     */
    private function normalizeOrigin(string $origin): string
    {
        $parts = parse_url(trim($origin));
        if (!is_array($parts) || empty($parts["scheme"]) || empty($parts["host"]))
        {
            return "";
        }
        $scheme = strtolower($parts["scheme"]);
        $port = $parts["port"] ?? null;
        if (($scheme === "https" && $port === 443) || ($scheme === "http" && $port === 80))
        {
            $port = null;
        }
        return $scheme . "://" . strtolower($parts["host"]) . ($port !== null ? ":" . $port : "");
    }

    /**
     * Check the answer of privacyIDEA to a challenge from /validate/initialize.
     * Such a challenge belongs to no user. privacyIDEA accepts the passkey of any user for it and names the owner
     * of the passkey in detail.username. This owner must be the ownCloud user.
     * privacyIDEA 3.11 to 3.13 do not compare the owner with the user name that the app sends,
     * so this check is on the client. The names must be equal, also in upper and lower case.
     *
     * The check covers every answer that accepted the passkey: a success (value true), and a challenge that privacyIDEA
     * chains to the passkey (for example the enrollment of a new token, enroll_via_multichallenge).
     * If the answer has the realm of the owner (policy add_resolver_in_response), the realm must be the realm in the
     * settings, if one is set. A refusal or an error needs no check. The normal error handling takes it.
     *
     * @param mixed $body The decoded answer of privacyIDEA
     * @param string $username The ownCloud user
     * @return bool False if the answer accepted the passkey of another user, or accepted it without a user name
     */
    private function passkeyAnswerIsForUser($body, string $username): bool
    {
        if (!is_object($body) || ($body->result->status ?? null) !== true)
        {
            return true;
        }
        $accepted = ($body->result->value ?? null) === true || isset($body->detail->multi_challenge);
        if (!$accepted)
        {
            return true;
        }
        $owner = $body->detail->username ?? null;
        if (!is_string($owner) || $owner === "")
        {
            // For example, the policy no_detail_on_success removes the details of a success.
            $this->log("error", "[verifyChallenge] privacyIDEA accepted the passkey, but the answer has no user name. "
                . "The app cannot check the user and refuses the login.");
            return false;
        }
        if ($owner !== $username)
        {
            $this->log("error", "[verifyChallenge] The passkey belongs to the user \"" . $owner . "\", but the user \""
                . $username . "\" logs in. The app refuses the login.");
            return false;
        }
        // privacyIDEA keeps realm names in lower case and finds them in any case.
        $ownerRealm = $body->detail->{"user-realm"} ?? null;
        $realm = $this->getAppValue("realm", "");
        if ($realm !== "" && is_string($ownerRealm) && $ownerRealm !== "" && strcasecmp($ownerRealm, $realm) !== 0)
        {
            $this->log("error", "[verifyChallenge] The passkey belongs to the realm \"" . $ownerRealm . "\", but the realm \""
                . $realm . "\" is set. The app refuses the login.");
            return false;
        }
        return true;
    }

    /**
     * Remove the passkey challenge and its transaction from the session.
     */
    private function removePasskeyChallenge(): void
    {
        $this->session->remove("pi_passkeyChallenge");
        $this->session->remove("pi_passkeyTransactionId");
        $this->session->remove("pi_passkeyFetchedAt");
        $this->session->remove("pi_passkeyTriggeredAt");
        $this->session->remove("pi_passkeyMessage");
    }

    /**
     * Make sure that the page has a passkey challenge for the passkey button.
     * privacyIDEA sends a passkey challenge only to a client that triggers it (the service account, or the policy
     * passkey_trigger_by_pin). /validate/initialize needs neither. It gives a challenge that belongs to no user.
     * privacyIDEA names the owner of the passkey in the answer, and passkeyAnswerIsForUser() compares it.
     *
     * The setting "passkeybutton" turns this off. It is on by default. A challenge stays until it is old. An old
     * triggered challenge is replaced too, because privacyIDEA drops it after its validity time.
     * If the request fails, the page has no passkey button, and the app waits before it asks again.
     * The button is an extra, so the error does not stop the login.
     */
    private function offerPasskey(): void
    {
        // Only "0" turns the button off. The settings page does the same, so an empty value shows a checked box.
        // During an enrollment, the user passed privacyIDEA already, so the page offers no passkey login. The answer
        // with the enrollment removed the challenge of the first page (storeChallenges).
        if ($this->getAppValue("passkeybutton", "1") === "0" || $this->session->get("pi_noAuthRequired")
            || $this->session->get("pi_enrolling") === true)
        {
            return;
        }

        // Keep a challenge until it is old, because the push and TiQR pages load again and again.
        if ($this->session->get("pi_passkeyChallenge") !== null)
        {
            $since = $this->session->get("pi_passkeyFetchedAt") ?? $this->session->get("pi_passkeyTriggeredAt");
            if ($since === null || time() - (int)$since < self::PASSKEY_CHALLENGE_MAX_AGE)
            {
                return;
            }
            // An old triggered challenge started the prompt by itself. Its replacement waits for the button,
            // because some browsers start the prompt only for a click.
            if ($this->session->get("pi_mode") === "passkey")
            {
                $this->session->set("pi_mode", "otp");
            }
            $this->removePasskeyChallenge();
        }

        // After a failed request, wait. Otherwise every page load asks again, also each reload of the push page.
        $failedAt = $this->session->get("pi_passkeyFailedAt");
        if ($failedAt !== null && time() - (int)$failedAt < self::PASSKEY_RETRY_AFTER_FAILURE)
        {
            return;
        }

        try
        {
            // processPIResponse() is not for this answer. It would replace the transaction of the other challenges.
            $result = $this->sendRequest("validate/initialize", ["form_params" => ["type" => "passkey"]]);
            $body = json_decode($result->getBody());
            $passkey = $body->detail->passkey ?? null;
            $challenge = is_object($passkey) ? ($passkey->challenge ?? "") : "";
            $transactionID = $body->detail->transaction_id ?? "";

            if ($result->getStatusCode() !== 200 || ($body->result->status ?? false) !== true
                || !is_string($challenge) || $challenge === "" || !is_string($transactionID) || $transactionID === "")
            {
                // For example a privacyIDEA before 3.11, or one without the policies of the relying party.
                $this->log("debug", "[offerPasskey] privacyIDEA gave no passkey challenge. HTTP status: " . $result->getStatusCode());
                $this->session->set("pi_passkeyFailedAt", time());
                return;
            }

            // The answer has the name user_verification, the triggered challenge has userVerification.
            $rpId = $passkey->rpId ?? "";
            $userVerification = $passkey->user_verification ?? "";
            $this->session->set("pi_passkeyChallenge", [
                "challenge" => $challenge,
                "rpId" => is_string($rpId) ? $rpId : "",
                "userVerification" => is_string($userVerification) ? $userVerification : ""]);
            $this->session->set("pi_passkeyTransactionId", $transactionID);
            $this->session->set("pi_passkeyFetchedAt", time());
            // The text of the challenge, for example from the policy passkey_challenge_text.
            $message = $passkey->message ?? "";
            $this->session->set("pi_passkeyMessage", is_string($message) ? $this->plainText($message) : "");
            $this->session->remove("pi_passkeyFailedAt");
        }
        catch (\Throwable $e)
        {
            $this->log("error", "[offerPasskey] " . $e->getMessage());
            $this->session->set("pi_passkeyFailedAt", time());
        }
    }

    /**
     * Sends a request to /validate/check with the data required to authenticate a U2F token.
     *
     * @param string $username
     * @param string $transactionID
     * @param array $u2fSignResponse
     * @return void
     * @throws Exception
     */
    private function validateCheckU2F(string $username, string $transactionID, array $u2fSignResponse)
    {
        $options["form_params"] = [
            "user" => $username,
            "pass" => "",
            "transaction_id" => $transactionID];

        // here we add the signatureData and the clientData in case of U2F
        $options["form_params"]["signaturedata"] = $u2fSignResponse['signatureData'];
        $options["form_params"]["clientdata"] = $u2fSignResponse['clientData'];

        $res = $this->sendRequest("validate/check", $options);
        return $this->processPIResponse($res);
    }

    /**
     * Sends a request to /validate/check with the data required to authenticate a WebAuthn token.
     *
     * @param string $username
     * @param string $transactionID
     * @param array $webAuthnSignResponse
     * @param string $origin
     * @return mixed|string
     * @throws ProcessPIResponseException|Exception
     */
    private function validateCheckWebAuthn(string $username, string $transactionID, array $webAuthnSignResponse, string $origin)
    {
        $options["form_params"] = [
            "user" => $username,
            "pass" => "",
            "transaction_id" => $transactionID,
            "signaturedata" => $webAuthnSignResponse['signaturedata'],
            "clientdata" => $webAuthnSignResponse['clientdata'],
            "credentialid" => $webAuthnSignResponse['credentialid'],
            "authenticatordata" => $webAuthnSignResponse['authenticatordata']];
        // No user handle and no client extensions. Both are optional. privacyIDEA 3.11.2 and later expect the user
        // handle in base64url, older versions as text, and they read the extensions only as assertionClientExtensions.
        $options['headers']['Origin'] = $origin;

        $res = $this->sendRequest("validate/check", $options);
        return $this->processPIResponse($res);
    }

    /**
     * Cancel an optional enrollment (enroll_via_multichallenge_optional). privacyIDEA deletes the new token and
     * answers with a success, because the user passed privacyIDEA before the enrollment started.
     * The request does not name the user. With a user name, privacyIDEA treats the success like a login of a user
     * without a token and starts a new enrollment at once. The transaction comes from the session of this login.
     *
     * @param string $transactionID The transaction of the enrollment, from the session
     * @return mixed|string
     * @throws ProcessPIResponseException|Exception
     */
    private function cancelEnrollment(string $transactionID)
    {
        $options["form_params"] = [
            "transaction_id" => $transactionID,
            "cancel_enrollment" => "true"];
        $res = $this->sendRequest("validate/check", $options);
        return $this->processPIResponse($res);
    }

    /**
     * Complete the registration of a passkey (enroll_via_multichallenge=passkey) with the answer of the browser.
     * The token (serial) and the transaction come from the session, so the registration belongs to this login.
     *
     * @param string $username
     * @param string $transactionID
     * @param string $serial The serial of the passkey token that privacyIDEA created for the registration
     * @param array $response The answer of the browser: credential_id, rawId, attestationObject, clientDataJSON and
     *                        authenticatorAttachment
     * @param string $origin The origin of ownCloud
     * @return mixed|string 0 for incomplete data
     * @throws ProcessPIResponseException|Exception
     */
    private function completePasskeyRegistration(string $username, string $transactionID, string $serial, array $response,
                                                 string $origin)
    {
        foreach (["credential_id", "rawId", "attestationObject", "clientDataJSON"] as $key)
        {
            if (empty($response[$key]) || !is_string($response[$key]))
            {
                $this->log("error", "Incomplete data for the passkey registration: " . $key . " is missing!");
                return 0;
            }
        }
        $options["form_params"] = [
            "user" => $username,
            "transaction_id" => $transactionID,
            "serial" => $serial,
            "type" => "passkey",
            "credential_id" => $response["credential_id"],
            "rawId" => $response["rawId"],
            "attestationObject" => $response["attestationObject"],
            "clientDataJSON" => $response["clientDataJSON"]];
        // privacyIDEA 3.14 requires it. A browser that does not report it gets the error of privacyIDEA.
        if (!empty($response["authenticatorAttachment"]) && is_string($response["authenticatorAttachment"]))
        {
            $options["form_params"]["authenticatorAttachment"] = $response["authenticatorAttachment"];
        }
        $options["headers"]["Origin"] = $origin;

        $res = $this->sendRequest("validate/check", $options);
        return $this->processPIResponse($res);
    }

    /**
     * Sends a request to /validate/check with the data required to authenticate a passkey.
     * The request has the user name. privacyIDEA 3.14 and later then refuse a passkey of another user.
     * Older versions ignore it for a challenge that belongs to no user (from /validate/initialize), so for such a
     * challenge the app checks the user in the answer itself, before it keeps anything of the answer.
     *
     * @param string $username
     * @param string $transactionID
     * @param array $passkeySignResponse The answer of the browser: credential_id, authenticatorData, clientDataJSON,
     *                                   signature and optionally userHandle
     * @param string $origin
     * @param bool $unbound True for a challenge from /validate/initialize
     * @return mixed|string 0 for incomplete data or for an answer of another user
     * @throws ProcessPIResponseException|Exception
     */
    private function validateCheckPasskey(string $username, string $transactionID, array $passkeySignResponse,
                                          string $origin, bool $unbound = false)
    {
        $required = ["credential_id", "authenticatorData", "clientDataJSON", "signature"];
        foreach ($required as $key)
        {
            if (empty($passkeySignResponse[$key]) || !is_string($passkeySignResponse[$key]))
            {
                $this->log("error", "Incomplete data for passkey authentication: " . $key . " is missing!");
                return 0;
            }
        }
        $options["form_params"] = [
            "user" => $username,
            "transaction_id" => $transactionID,
            "credential_id" => $passkeySignResponse["credential_id"],
            "authenticatorData" => $passkeySignResponse["authenticatorData"],
            "clientDataJSON" => $passkeySignResponse["clientDataJSON"],
            "signature" => $passkeySignResponse["signature"]];
        $realm = $this->getAppValue("realm", "");
        if ($realm !== "")
        {
            $options["form_params"]["realm"] = $realm;
        }
        if (!empty($passkeySignResponse["userHandle"]) && is_string($passkeySignResponse["userHandle"]))
        {
            $options["form_params"]["userHandle"] = $passkeySignResponse["userHandle"];
        }
        $options["headers"]["Origin"] = $origin;

        $res = $this->sendRequest("validate/check", $options);
        // The response of ownCloud gives the body only once, so read it here and hand it on.
        $bodyText = (string)$res->getBody();
        // Check before processPIResponse(). It keeps the challenges of an answer in the session, for example the
        // enrollment of a new token that privacyIDEA chains to the passkey of another user.
        if ($unbound && !$this->passkeyAnswerIsForUser(json_decode($bodyText), $username))
        {
            // The message for the user is the general one. It does not name the owner of the passkey.
            return 0;
        }
        return $this->processPIResponse($res, $bodyText);
    }

    /**
     * Call /validate/polltransaction using transaction_id
     *
     * @param string $transactionID
     * @return string "accept" when the user confirmed, "declined" when the user declined it on the phone or it was
     *                cancelled (detail.challenge_status, privacyIDEA 3.9 and later), else "pending". privacyIDEA also
     *                answers "pending" for an expired challenge, so the caller checks the age of the challenge.
     * @throws ProcessPIResponseException|Exception
     */
    private function pollTransaction(string $transactionID): string
    {
        $options["form_params"] = ["transaction_id" => $transactionID];
        $res = $this->sendRequest("validate/polltransaction", $options, true);
        $ret = $this->processPIResponse($res);
        if (is_object($ret) && ($ret->result->status ?? null) === true && ($ret->result->value ?? null) === true)
        {
            return "accept";
        }
        $challengeStatus = is_object($ret) ? ($ret->detail->challenge_status ?? "") : "";
        if ($challengeStatus === "declined" || $challengeStatus === "cancelled")
        {
            return "declined";
        }
        return "pending";
    }

    /**
     * Stop the wait for a push or TiQR confirmation: show the reason and offer a new challenge.
     *
     * @param string $message
     * @throws TwoFactorException
     */
    private function stopWaiting(string $message): void
    {
        $this->log("debug", "[verifyChallenge] Stop the poll: " . $message);
        $this->session->set("pi_mode", "otp");
        $this->session->set("pi_offerNewChallenge", true);
        $this->session->set("pi_message", $message);
        throw new TwoFactorException($message);
    }

    /**
     * @return bool True if the challenges of the session are older than the default validity time of privacyIDEA
     */
    private function challengeIsOld(): bool
    {
        $time = $this->session->get("pi_challengeTime");
        $limit = $this->session->get("pi_enrolling") === true ? self::ENROLLMENT_WAIT : self::CHALLENGE_VALIDITY;
        return $time !== null && time() - (int)$time > $limit;
    }

    /**
     * The button "New challenge": forget the old challenges of this login. With "Trigger challenges", the next page
     * triggers new ones. Without it, the user starts a new challenge with the PIN, or types an OTP.
     *
     * @throws TwoFactorException
     */
    private function startNewChallenge(): void
    {
        foreach (self::CHALLENGE_KEYS as $key)
        {
            $this->session->remove($key);
        }
        $this->removePasskeyChallenge();
        foreach (["pi_transactionId", "pi_TriggerChallengeSuccess", "pi_challengeTime", "pi_message", "pi_detail",
                     "pi_loadCounter", "pi_preferredClientMode", "pi_pinChangeSeen"] as $key)
        {
            $this->session->remove($key);
        }
        $this->session->set("pi_mode", "otp");
        $this->session->set("pi_hideOTPField", false);
        $this->log("debug", "[verifyChallenge] The user asked for a new challenge.");
        throw new TwoFactorException($this->getAppValue("triggerchallenges", "") === "1"
            ? $this->trans->t("New challenges are on the way.")
            : $this->trans->t("Enter your PIN or your OTP to start a new challenge."));
    }

    /**
     * Authenticate the service account against the privacyIDEA instance and return the generated JWT token.
     * In case authentication fails, an AdminAuthException is thrown.
     *
     * @param string $username service account username
     * @param string $password service account password
     * @return string JWT token
     * @throws AdminAuthException
     */
    public function getAuthToken(string $username, string $password): string
    {
        $errorMessage = "";
        $url = $this->getBaseUrl() . "auth";
        $options = $this->getClientOptions();
        $result = 0;
        try
        {
            $client = $this->httpClientService->newClient();
            $options["form_params"] = ["username" => $username, "password" => $password];
            $result = $client->post($url, $options);
            $body = json_decode($result->getBody());
            if ($result->getStatusCode() === 200)
            {
                if (isset($body->result->status) && $body->result->status === true)
                {
                    $piOutdated = true;
                    foreach ($this->recommendedPIVersions as $version)
                    {
                        if (strpos((string)($body->versionnumber ?? ""), $version) === 0)
                        {
                            $piOutdated = false;
                        }
                    }
                    if ($piOutdated)
                    {
                        $this->session->set("pi_outdated", true);
                        $this->log("error", "We recommend to update your privacyIDEA server");
                    }
                    if (in_array("triggerchallenge", (array)($body->result->value->rights ?? [])))
                    {
                        return $body->result->value->token;
                    }
                    else
                    {
                        $errorMessage = $this->trans->t("Check if service account has correct permissions");
                        $this->log("error", "[fetchAuthToken] privacyIDEA error message: Missing permissions for service account");
                    }
                }
                else
                {
                    $this->log("error", "[fetchAuthToken] The answer of privacyIDEA has no valid result.");
                }
            }
            else
            {
                // The HTTP client does not throw for an error status, so the body can be anything.
                $errorMessage = $this->trans->t("Failed to fetch authentication token. Wrong HTTP return code: %s",
                                                [$result->getStatusCode()]);
                $this->log("error", "[fetchAuthToken] privacyIDEA error code: " . ($body->result->error->code ?? ""));
                $this->log("error", "[fetchAuthToken] privacyIDEA error message: " . ($body->result->error->message ?? ""));
            }
        }
        catch (Exception $e)
        {
            if ($result !== 0)
            {
                $this->log("error", "[fetchAuthToken] HTTP return code: " . $result->getStatusCode());
            }
            $this->log("error", "[fetchAuthToken] " . $e->getMessage());
            // Do not log the exception object. Its trace can contain the arguments of getAuthToken(),
            // which include the password of the service account.
            $this->log("debug", "[fetchAuthToken] Exception class: " . get_class($e));
            $errorMessage = $this->trans->t("Failed to fetch authentication token.");
        }
        throw new AdminAuthException($errorMessage);
    }

    /**
     * The text of a challenge or a refusal for the user, as plain text.
     * detail.message has the text with the header and the footer of the policies challenge_text_header and
     * challenge_text_footer. These can hold HTML, which the page would show as tags, so the tags become spaces.
     * detail.messages has the single texts without header and footer. It is the fallback.
     *
     * @param mixed $detail
     * @return string
     */
    private function challengeText($detail): string
    {
        if (!is_object($detail))
        {
            return "";
        }
        $text = $detail->message ?? null;
        if (!is_string($text) || $text === "")
        {
            $messages = isset($detail->messages) && is_array($detail->messages) ? $detail->messages : [];
            $text = implode(", ", array_unique(array_filter($messages, "is_string")));
        }
        return $this->plainText($text);
    }

    /**
     * A text of privacyIDEA as plain text: HTML tags become spaces, and the spaces are joined.
     *
     * @param string $text
     * @return string
     */
    private function plainText(string $text): string
    {
        $text = html_entity_decode(strip_tags(str_replace("<", " <", $text)), ENT_QUOTES | ENT_HTML5, "UTF-8");
        // preg_replace() returns null for text that is not valid UTF-8.
        return trim(preg_replace('/\s+/u', " ", $text) ?? $text);
    }

    /**
     * A short form of an answer of privacyIDEA for the debug log.
     * An answer can hold secrets, for example the otpauth link (with the seed) and the QR code of a token that the
     * user enrolls via multichallenge. The log gets only the fields that tell what happened.
     *
     * @param mixed $body The decoded answer
     * @return array
     */
    private function answerForLog($body): array
    {
        if (!is_object($body))
        {
            return ["answer" => "not JSON"];
        }
        $short = [
            "status" => $body->result->status ?? null,
            "value" => $body->result->value ?? null,
            "authentication" => $body->result->authentication ?? null];
        if (isset($body->result->error))
        {
            $short["error"] = ["code" => $body->result->error->code ?? null, "message" => $body->result->error->message ?? null];
        }
        $detail = $body->detail ?? null;
        if (is_object($detail))
        {
            foreach (["message", "transaction_id", "preferred_client_mode", "challenge_status", "username", "serial",
                         "enroll_via_multichallenge"] as $key)
            {
                if (isset($detail->$key) && is_scalar($detail->$key))
                {
                    $short[$key] = $detail->$key;
                }
            }
            if (isset($detail->multi_challenge) && is_array($detail->multi_challenge))
            {
                $short["multi_challenge"] = [];
                foreach ($detail->multi_challenge as $entry)
                {
                    $short["multi_challenge"][] = [
                        "type" => $entry->type ?? null,
                        "client_mode" => $entry->client_mode ?? null,
                        "serial" => $entry->serial ?? null];
                }
            }
        }
        return $short;
    }

    /**
     * Keep the challenges of an answer (detail.multi_challenge) in the session, and choose the mode of the page.
     * The challenges of an earlier answer are removed first, so that no button of an old challenge stays.
     *
     * @param object $detail
     */
    private function storeChallenges($detail): void
    {
        foreach (self::CHALLENGE_KEYS as $key)
        {
            $this->session->remove($key);
        }
        $this->removePasskeyChallenge();
        $this->session->set("pi_challengeTime", time());

        $multiChallenge = is_array($detail->multi_challenge) ? $detail->multi_challenge : [];
        // An answer that enrolls a new token (enroll_via_multichallenge) asks for an email address, a phone number
        // or the OTP of the new token. Its QR code shows in every mode.
        $enroll = !empty($detail->enroll_via_multichallenge);
        // privacyIDEA marks the challenge before a PIN change with pin_change. The next challenges ask for the new PIN.
        $pinChange = $this->session->get("pi_pinChangeSeen") === true;
        if (!empty($detail->pin_change))
        {
            $this->session->set("pi_pinChangeSeen", true);
        }
        $noAutoSubmit = $enroll || $pinChange;
        if ($enroll)
        {
            $this->session->set("pi_enrolling", true);
            // privacyIDEA lets the user skip an optional enrollment (cancel_enrollment). This holds for every token type.
            if (!empty($detail->enroll_via_multichallenge_optional))
            {
                $this->session->set("pi_enrollOptional", true);
            }
        }

        $preferred = "otp";
        if (property_exists($detail, "preferred_client_mode"))
        {
            $preferred = (string)$detail->preferred_client_mode;
            $preferred = $preferred === "poll" ? "push" : ($preferred === "interactive" ? "otp" : $preferred);
        }
        if (count($multiChallenge) === 0)
        {
            $this->session->set("pi_preferredClientMode", "otp");
            $this->session->set("pi_hideOTPField", "0");
            return;
        }

        $hasWebAuthn = false;
        $webAuthnSignRequest = null;
        $entryMode = null;
        foreach ($multiChallenge as $entry)
        {
            // The registration of a passkey (enroll_via_multichallenge=passkey) has no client_mode. Its entry holds the
            // options for navigator.credentials.create().
            if (is_object($entry) && isset($entry->passkey_registration) && is_object($entry->passkey_registration))
            {
                $this->session->set("pi_passkeyRegistration", $entry->passkey_registration);
                $this->session->set("pi_passkeyRegistrationSerial", (string)($entry->serial ?? $detail->serial ?? ""));
                $noAutoSubmit = true;
                $entryMode = $entryMode ?? "passkeyRegistration";
                continue;
            }
            if (!is_object($entry) || empty($entry->client_mode))
            {
                continue;
            }
            $type = (string)($entry->type ?? "");
            $image = (isset($entry->image) && is_string($entry->image) && $entry->image !== "") ? $entry->image : null;
            switch ($entry->client_mode)
            {
                case "u2f":
                    if (isset($entry->attributes->u2fSignRequest))
                    {
                        $this->session->set("pi_u2fSignRequest", $entry->attributes->u2fSignRequest);
                    }
                    if ($image !== null)
                    {
                        $this->session->set("pi_imgU2F", $image);
                    }
                    break;
                case "webauthn":
                    if ($type === "passkey")
                    {
                        // A passkey has the client mode "webauthn", but it has no sign request.
                        // All passkey tokens of the user get the same challenge.
                        $this->session->set("pi_passkeyChallenge", [
                            "challenge" => (string)($entry->challenge ?? ""),
                            "rpId" => (string)($entry->rpId ?? ""),
                            "userVerification" => (string)($entry->userVerification ?? "")]);
                        $this->session->set("pi_passkeyTransactionId",
                            (string)($entry->transaction_id ?? $detail->transaction_id ?? ""));
                        $this->session->set("pi_passkeyTriggeredAt", time());
                        break;
                    }
                    $hasWebAuthn = true;
                    $webAuthnSignRequest = $this->mergeWebAuthnSignRequest($webAuthnSignRequest,
                        $entry->attributes->webAuthnSignRequest ?? null);
                    if ($image !== null)
                    {
                        $this->session->set("pi_imgWebauthn", $image);
                    }
                    break;
                case "poll":
                    if ($type === "push")
                    {
                        $this->session->set("pi_pushAvailable", "1");
                        if ($image !== null)
                        {
                            $this->session->set($enroll ? "pi_enrollImage" : "pi_imgPush", $image);
                        }
                        $entryMode = $entryMode ?? "push";
                    }
                    elseif ($type === "tiqr")
                    {
                        $this->session->set("pi_tiqrAvailable", "1");
                        if (isset($entry->attributes->img) && is_string($entry->attributes->img))
                        {
                            $this->session->set("pi_tiqrImage", $entry->attributes->img);
                        }
                        $entryMode = $entryMode ?? "tiqr";
                    }
                    // Other poll types, for example the registration of a smartphone container, are not supported.
                    break;
                default:
                    if ($image !== null)
                    {
                        $this->session->set($enroll ? "pi_enrollImage" : "pi_imgOTP", $image);
                    }
                    $this->session->set("pi_hideOTPField", "0");
                    $this->session->set("pi_otpAvailable", "1");
                    if (!in_array($type, self::OTP_TOKEN_TYPES, true))
                    {
                        // For example a question, an indexed secret or a PIN change. The answer is not an OTP.
                        $noAutoSubmit = true;
                    }
                    // The first step of an email or SMS enrollment asks for the address or the phone number.
                    // The user must see what they type. The next step (the OTP) has no enroll flag.
                    if ($enroll && ($type === "email" || $type === "sms"))
                    {
                        $this->session->set("pi_plainInput", true);
                    }
                    $entryMode = $entryMode ?? "otp";
            }
            // The otpauth link of a new token lets a user enroll it on the same phone.
            $link = $entry->link ?? null;
            if ($enroll && is_string($link) && strpos($link, "otpauth://") === 0)
            {
                $this->session->set("pi_enrollLink", $link);
            }
        }

        if ($webAuthnSignRequest !== null)
        {
            $this->session->set("pi_webAuthnSignRequest", $webAuthnSignRequest);
        }
        if ($noAutoSubmit)
        {
            $this->session->set("pi_noAutoSubmit", true);
        }
        // An answer that enrolls a token has no preferred_client_mode. The mode of its entry shows the QR code
        // and, for a push token, starts the poll.
        if (!property_exists($detail, "preferred_client_mode") && $enroll && $entryMode !== null)
        {
            $preferred = $entryMode;
        }
        // privacyIDEA names the mode of a TiQR token "poll" too. Without a push token, the poll mode is TiQR.
        if ($preferred === "push" && $this->session->get("pi_pushAvailable") !== "1")
        {
            $preferred = $this->session->get("pi_tiqrAvailable") === "1" ? "tiqr" : "otp";
        }
        // privacyIDEA names the client mode of a passkey "webauthn". Without a WebAuthn token, use the passkey flow.
        if ($preferred === "webauthn" && !$hasWebAuthn && $this->session->get("pi_passkeyChallenge") !== null)
        {
            $preferred = "passkey";
        }
        $this->session->set("pi_preferredClientMode", $preferred);
        $this->session->set("pi_mode", $preferred);
    }

    /**
     * One WebAuthn sign request for all WebAuthn tokens of the user.
     * privacyIDEA sends one entry per token. Each sign request lists only the credential of its token, and all of
     * them have the same challenge. The browser takes one request, so the lists of credentials are joined.
     *
     * @param object|null $merged The request so far
     * @param mixed $request The request of the next token
     * @return object|null
     */
    private function mergeWebAuthnSignRequest($merged, $request)
    {
        if (!is_object($request))
        {
            return $merged;
        }
        if ($merged === null)
        {
            return $request;
        }
        $credentials = isset($merged->allowCredentials) && is_array($merged->allowCredentials) ? $merged->allowCredentials : [];
        $known = [];
        foreach ($credentials as $credential)
        {
            $known[] = $credential->id ?? null;
        }
        foreach ((isset($request->allowCredentials) && is_array($request->allowCredentials)) ? $request->allowCredentials : [] as $credential)
        {
            if (!in_array($credential->id ?? null, $known, true))
            {
                $credentials[] = $credential;
                $known[] = $credential->id ?? null;
            }
        }
        $merged->allowCredentials = $credentials;
        return $merged;
    }

    /**
     * Process and sort the privacyIDEA API Response
     * Takes relevant info from privacyIDEAs response
     * and adds them to the session, like:
     * - which types challenges have been triggered
     * - the messages or errors if any occurred
     * And also document when an exception is thrown, when an empty string is returned
     *
     * @param IResponse $result
     * @param string|null $bodyText The body, if the caller read it already. The response of ownCloud gives the body
     *                              only once: a second getBody() returns an empty string.
     * @return mixed|string
     * @throws ProcessPIResponseException
     */
    private function processPIResponse(IResponse $result, ?string $bodyText = null)
    {
        $passOnNoToken = $this->getAppValue('passOnNoUser', false);
        $body = json_decode($bodyText ?? (string)$result->getBody());
        $this->log("debug", "Answer of privacyIDEA: " . json_encode($this->answerForLog($body)));

        // The HTTP client does not throw for an error status, so the body can be anything (for example
        // the error page of a proxy). Every path below either returns or throws. This is the same in every mode:
        // in push and TiQR mode, an error must not look like a push that is not confirmed yet.
        $status = $result->getStatusCode();
        if (!is_object($body) || !isset($body->result))
        {
            $this->log("error", "[processPIResponse] Unexpected answer from privacyIDEA. HTTP status: " . $status);
            throw new ProcessPIResponseException($this->trans->t("Unexpected answer from privacyIDEA."));
        }

        if ($status == 200)
        {
            if (isset($body->result->status) && $body->result->status === true)
            {
                // The policy no_detail_on_success removes "detail" from a successful answer.
                $detail = (isset($body->detail) && is_object($body->detail)) ? $body->detail : new \stdClass();
                $this->session->set("pi_detail", $body->detail ?? null);

                if (property_exists($detail, "transaction_id"))
                {
                    $this->session->set("pi_transactionId", $detail->transaction_id);
                }
                if (property_exists($detail, "multi_challenge"))
                {
                    $this->storeChallenges($detail);
                }
                return $body;
            }
            // HTTP 200, but result.status is not true.
            $this->log("error", "[processPIResponse] privacyIDEA answered with HTTP 200 and result.status that is not true.");
            throw new ProcessPIResponseException($this->trans->t("Unexpected answer from privacyIDEA."));
        }
        elseif ($status >= 400 && $status < 500 && $passOnNoToken && ($body->result->error->code ?? null) == 904)
        {
            // Error 904: privacyIDEA does not know the user. privacyIDEA sends it with HTTP 400, or with 401 when
            // the policy hide_auth_error_status (3.14 and later) is set.
            $this->session->set("pi_noAuthRequired", true);
            $this->session->set("pi_autoSubmit", true);
            $this->log("debug", "PassOnNoUser enabled, skipping 2FA...");
            return $this->trans->t("No token found for your user, Login is still enabled.");
        }
        else
        {
            $errorMessage = $body->result->error->message ?? $this->trans->t("Unexpected answer from privacyIDEA.");
            $this->log("error", "[processPIResponse] privacyIDEA error message: " . $errorMessage);
            throw new ProcessPIResponseException($errorMessage);
        }
    }

    /**
     * Prepare and send request to httpClientService
     *
     * @param string $endpoint
     * @param array $options
     * @param bool $isGET
     * @return IResponse
     * @throws Exception
     */
    private function sendRequest(string $endpoint, array $options, bool $isGET = false): IResponse
    {
        $clientOptions = $this->getClientOptions();
        // array_merge replaces the whole "headers" array. A request with its own header (the Origin of a WebAuthn
        // or passkey answer) would lose the user agent, and the HTTP client would send its own.
        $headers = array_merge($clientOptions["headers"] ?? [], $options["headers"] ?? []);
        $options = array_merge($clientOptions, $options);
        $options["headers"] = $headers;
        $realm = $this->getAppValue('realm', '');
        if (!empty($realm))
        {
            $options["form_params"]['realm'] = $realm;
        }

        $params = $options["form_params"];
        if ($isGET)
        {
            // A GET request has no body. Guzzle 7 (ownCloud 11) does not send "form_params" with GET,
            // and privacyIDEA then answers with "Missing parameter".
            $options["query"] = $params;
            unset($options["form_params"]);
        }

        // The log must never contain the OTP or the PIN.
        $loggedParams = $params;
        foreach (["pass", "password"] as $secret)
        {
            if (isset($loggedParams[$secret]))
            {
                $loggedParams[$secret] = "***";
            }
        }
        $this->log("debug", "Send request to " . $endpoint);
        $this->log("debug", "With options: " . http_build_query($loggedParams, "", ", "));

        $client = $this->httpClientService->newClient();

        if ($isGET)
        {
            $res = $client->get($this->getBaseUrl() . $endpoint, $options);
        }
        else
        {
            $res = $client->post($this->getBaseUrl() . $endpoint, $options);
        }
        return $res;
    }

    /**
     * Decides whether 2FA is enabled for the given user
     * This method is called after the user has successfully finished the first
     * authentication step i.e.
     * He authenticated with username and password.
     *
     * @param IUser $user
     * @return boolean
     */
    public function isTwoFactorAuthEnabledForUser(IUser $user): bool
    {
        $piActive = $this->getAppValue('piactive', '');
        $piExcludeGroups = $this->getAppValue('piexcludegroups', '');
        $piExclude = $this->getAppValue('piexclude', '1');
        $piExcludeIPs = $this->getAppValue('piexcludeips', '');
        if ($piActive === "1")
        {
            // 2FA is basically enabled
            if ($piExcludeIPs)
            {
                // We can exclude clients with specified ips from 2FA.
                // getRemoteAddress() only reads the X-Forwarded-For header when the connection comes from
                // a proxy that the admin listed in "trusted_proxies" in config.php. It then uses the FIRST address
                // of the header. The proxy must replace the header with the address of its client. A proxy that
                // appends to a header of the client lets the client choose the address. See the README.
                $clientIP = $this->request->getRemoteAddress();
                if (IpMatcher::matches($clientIP, $piExcludeIPs))
                {
                    $this->log("debug", "[isTwoFactorAuthEnabledForUser] The client IP " . $clientIP . " is excluded from 2FA.");
                    return false;
                }
            }
            if ($piExcludeGroups)
            {
                // We can exclude groups from the 2FA
                $piExcludeGroupsCSV = str_replace("|", ",", $piExcludeGroups);
                $groups = explode(",", $piExcludeGroupsCSV);
                $checkEnabled = false;
                foreach ($groups as $group)
                {
                    if ($this->groupManager->isInGroup($user->getUID(), trim($group)))
                    {
                        $this->log("debug", "[isTwoFactorEnabledForUser] The user " . $user->getUID() . " is in group " . $group . ".");
                        if ($piExclude === "1")
                        {
                            $this->log("debug", "[isTwoFactorEnabledForUser] The group " . $group . " is excluded (User does not need 2FA).");
                            return false;
                        }
                        if ($piExclude === "0")
                        {
                            $this->log("debug", "[isTwoFactorEnabledForUser] The group " . $group . " is included (User needs 2FA).");
                            return true;
                        }
                    }
                    $this->log("debug", "[isTwoFactorEnabledForUser] The user " . $user->getUID() . " is not in group " . $group . ".");
                    if ($piExclude === "1")
                    {
                        $this->log("debug", "[isTwoFactorEnabledForUser] The group " . $group . " is excluded (User may need 2FA).");
                        $checkEnabled = true;
                    }
                    if ($piExclude === "0")
                    {
                        $this->log("debug", "[isTwoFactorEnabledForUser] The group " . $group . " is included (User may not need 2FA).");
                        $checkEnabled = false;
                    }
                }
                if (!$checkEnabled)
                {
                    return false;
                }
            }
            $this->log("debug", "[isTwoFactorAuthEnabledForUser] User needs 2FA");
            return true;
        }
        $this->log("debug", "[isTwoFactorAuthEnabledForUser] privacyIDEA is not enabled.");
        return false;
    }

    /**
     * Return an associative array that contains the options that should be passed to
     * the HTTP client service when creating HTTP requests.
     * @return array
     */
    private function getClientOptions(): array
    {
        $checkSSL = $this->getAppValue('checkssl', '');
        $noProxy = $this->getAppValue('noproxy', '');
        // An empty or wrong value in the settings must not reach the HTTP client. On PHP 8 the client throws
        // a TypeError for "" * 1000. A value below 1 second uses the default of 5 seconds.
        $timeout = (int)$this->getAppValue('pitimeout', '5');
        if ($timeout < 1)
        {
            $timeout = 5;
        }
        $options = ['headers' => ['user-agent' => "ownCloud Plugin"],
            // Do not throw an exception for an HTTP error status. The code reads the status and the body.
            // (The old option name "exceptions" is ignored since Guzzle 6.)
            'http_errors' => false,
            'timeout' => $timeout];
        // NOTE: Here, we check for `=== '0'` instead of `!== '1'` in order to verify certificates
        // by default just after app installation. Without a "verify" option, the HTTP client of ownCloud
        // uses its own CA bundle. This bundle contains the certificates that the admin imported with
        // "occ security:certificates:import".
        if ($checkSSL === '0')
        {
            $options['verify'] = false;
        }
        if ($noProxy === "1")
        {
            $options["proxy"] = ["https" => "", "http" => ""];
        }
        return $options;
    }

    /**
     * Retrieve a value from the app's (twofactor_privacyidea) configuration store.
     *
     * @param string $key application config key
     * @param $default
     * @return string
     */
    private function getAppValue(string $key, $default): string
    {
        return $this->config->getAppValue('twofactor_privacyidea', $key, $default);
    }

    /**
     * Retrieve the privacyIDEA instance base URL from the app configuration.
     * In case the stored URL ends with '/validate/check', this suffix is removed.
     * The returned URL always ends with a slash.
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        $url = $this->getAppValue('url', '');

        // Remove the "/validate/check" suffix of $url if it exists
        $suffix = "/validate/check";
        if (substr($url, -strlen($suffix)) === $suffix)
        {
            $url = substr($url, 0, -strlen($suffix));
        }

        // Ensure that $url ends with a slash
        if (substr($url, -1) !== "/")
        {
            $url .= "/";
        }
        return $url;
    }

    /**
     * Let owncloud to get unique identifier of this 2FA provider
     *
     * @return string
     */
    public function getId(): string
    {
        return 'privacyidea';
    }

    /**
     * Get the display name for selecting the 2FA provider
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return 'privacyIDEA';
    }

    /**
     * Get the description for selecting the 2FA provider
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'privacyIDEA';
    }

    /**
     * Use owncloud logger to show debug info and error messages
     * @param $level
     * @param $message
     */
    private function log($level, $message)
    {
        $context = ["app" => "privacyIDEA"];
        if ($level === 'debug')
        {
            $this->logger->debug($message, $context);
        }
        if ($level === 'info')
        {
            $this->logger->info($message, $context);
        }
        if ($level === 'error')
        {
            $this->logger->error($message, $context);
        }
    }
}