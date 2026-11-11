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
        "pi_pollInBrowserFailed", "pi_errorMessage", "pi_loadCounter", "pi_preferredClientMode", "pi_noAuthRequired"];
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
        // TriggerChallenge
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
                catch (Exception $e)
                {
                    // Show the OTP field again. Without it the user can not enter anything.
                    $this->session->set("pi_hideOTPField", false);
                    $this->log("error", "[getTemplate] " . $e->getMessage());
                    $message = $this->userMessage($e);
                }
            }
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

        $template->assign("autoSubmitOtpLength", $this->getAppValue("autoSubmitOtpLength", ""));
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
        if ($this->session->get("pi_noAuthRequired"))
        {
            return true;
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

        if ($mode === "push" || $mode === "tiqr")
        {
            if ($mode === "push")
            {
                $this->log("debug", "We are processing a PUSH response.");
            }
            if ($mode === "tiqr")
            {
                $this->log("debug", "We are processing a TIQR response.");
            }

            if ($this->pollTransaction($transactionID))
            {
                // The challenge has been answered. Now we need to verify it.
                $ret = $this->validateCheck($username, "", $transactionID);
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
        elseif ($mode === "webauthn")
        {
            $webAuthnSignResponse = json_decode($this->request->getParam("webAuthnSignResponse"), true);
            $origin = $this->request->getParam("origin");

            if (empty($webAuthnSignResponse))
            {
                $this->log("error", "Incomplete data for WebAuthn authentication: webAuthnSignResponse is missing!");
            }
            else
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
                    if (isset($ret->detail->messages))
                    {
                        $errorMessage = implode(", ", array_unique($ret->detail->messages));
                    }
                    else
                    {
                        $errorMessage = $ret->detail->message ?? $this->trans->t("Failed to authenticate.");
                        $this->log("debug", "privacyIDEA:" . $errorMessage);
                    }
                    $this->session->set("pi_message", $errorMessage);
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

        $options["form_params"] = ["user" => $username, "realm" => $realm];
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
            return (string)($ret->detail->message ?? "");
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
        if (!empty($webAuthnSignResponse['userhandle']))
        {
            $options["form_params"]['userhandle'] = $webAuthnSignResponse['userhandle'];
        }
        if (!empty($webAuthnSignResponse['assertionclientextensions']))
        {
            $options["form_params"]['assertionclientextensions'] = $webAuthnSignResponse['assertionclientextensions'];
        }
        $options['headers']['Origin'] = $origin;

        $res = $this->sendRequest("validate/check", $options);
        return $this->processPIResponse($res);
    }

    /**
     * Call /validate/polltransaction using transaction_id
     *
     * @param string $transactionID
     * @return bool
     * @throws ProcessPIResponseException|Exception
     */
    private function pollTransaction(string $transactionID): bool
    {
        $options["form_params"] = ["transaction_id" => $transactionID];
        $res = $this->sendRequest("validate/polltransaction", $options, true);
        $ret = $this->processPIResponse($res);
        // In push and TiQR mode processPIResponse() returns the decoded body without a check.
        return is_object($ret)
            && isset($ret->result->status, $ret->result->value)
            && $ret->result->status === true
            && $ret->result->value === true;
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
            $this->log("debug", $e);
            $errorMessage = $this->trans->t("Failed to fetch authentication token.");
        }
        throw new AdminAuthException($errorMessage);
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
     * @return mixed|string
     * @throws ProcessPIResponseException
     */
    private function processPIResponse(IResponse $result)
    {
        $passOnNoToken = $this->getAppValue('passOnNoUser', false);
        $errorMessage = "";
        $mode = $this->request->getParam("mode", "otp");
        $body = json_decode($result->getBody());
        $this->log("debug", print_r(json_encode($body), true));

        if ($mode === "push" || $mode === "tiqr")
        {
            return $body;
        }
        else
        {
            // The HTTP client does not throw for an error status, so the body can be anything (for example
            // the error page of a proxy). Every path below either returns or throws.
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
                    $detail = $body->detail;
                    $this->session->set("pi_detail", $detail);

                    if (property_exists($detail, "transaction_id"))
                    {
                        $this->session->set("pi_transactionId", $detail->transaction_id);
                    }

                    if (property_exists($detail, "multi_challenge"))
                    {
                        // Reset the mode for each new challenge
                        $this->session->set("pi_preferredClientMode", "otp");
                        if (property_exists($detail, "preferred_client_mode"))
                        {
                            $pref = $detail->preferred_client_mode;
                            if ($pref === "poll")
                            {
                                $this->session->set("pi_preferredClientMode", "push");
                            }
                            elseif ($pref === "interactive")
                            {
                                $this->session->set("pi_preferredClientMode", "otp");
                            }
                            else
                            {
                                $this->session->set("pi_preferredClientMode", $pref);
                            }
                        }

                        $multiChallenge = $detail->multi_challenge;
                        if (count($multiChallenge) === 0)
                        {
                            $this->session->set("pi_hideOTPField", "0");
                        }
                        else
                        {
                            for ($i = 0; $i < count($multiChallenge); $i++)
                            {
                                if (!empty($multiChallenge[$i]->client_mode))
                                {
                                    switch ($multiChallenge[$i]->client_mode)
                                    {
                                        case "u2f":
                                            $this->session->set("pi_u2fSignRequest", $multiChallenge[$i]->attributes->u2fSignRequest);
                                            if (!empty($multiChallenge[$i]->image))
                                            {
                                                $this->session->set("pi_imgU2F", $multiChallenge[$i]->image);
                                            }
                                            break;
                                        case "webauthn":
                                            $this->session->set("pi_webAuthnSignRequest", $multiChallenge[$i]->attributes->webAuthnSignRequest);
                                            if (!empty($multiChallenge[$i]->image))
                                            {
                                                $this->session->set("pi_imgWebauthn", $multiChallenge[$i]->image);
                                            }
                                            break;
                                        case "poll":
                                            if ($multiChallenge[$i]->type === "push")
                                            {
                                                $this->session->set("pi_pushAvailable", "1");
                                                if (!empty($multiChallenge[$i]->image))
                                                {
                                                    $this->session->set("pi_imgPush", $multiChallenge[$i]->image);
                                                }
                                            }
                                            elseif ($multiChallenge[$i]->type === "tiqr")
                                            {
                                                $this->session->set("pi_tiqrAvailable", "1");
                                                $this->session->set("pi_tiqrImage", $multiChallenge[$i]->attributes->img);
                                            }
                                            break;
                                        default:
                                            if (!empty($multiChallenge[$i]->image))
                                            {
                                                $this->session->set("pi_imgOTP", $multiChallenge[$i]->image);
                                            }
                                            $this->session->set("pi_hideOTPField", "0");
                                            $this->session->set("pi_otpAvailable", "1");
                                    }
                                }
                            }
                            // Set the mode to preferred if possible
                            if (!empty($this->session->get("pi_preferredClientMode")))
                            {
                                $this->session->set("pi_mode", $this->session->get("pi_preferredClientMode"));
                            }
                        }
                    }
                    return $body;
                }
                // HTTP 200, but result.status is not true.
                $this->log("error", "[processPIResponse] privacyIDEA answered with HTTP 200 and result.status that is not true.");
                throw new ProcessPIResponseException($this->trans->t("Unexpected answer from privacyIDEA."));
            }
            elseif ($status == 400 && $passOnNoToken && ($body->result->error->code ?? null) == 904)
            {
                // Error 904: privacyIDEA does not know the user.
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
        $options = array_merge($clientOptions, $options);
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
                // getRemoteAddress() only believes the X-Forwarded-For header when the connection comes from
                // a proxy that the admin listed in "trusted_proxies" in config.php. A client can not fake the address.
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
        $timeout = $this->getAppValue('pitimeout', '5');
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