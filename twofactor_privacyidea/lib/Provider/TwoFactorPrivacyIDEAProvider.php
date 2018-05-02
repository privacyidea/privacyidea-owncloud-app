<?php
/**
 * @author Cornelius Kölbel <cornelius.koelbel@netknights.it>
 *
 * @copyright Copyright (c) 2016, Cornelius Kölbel
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
namespace OCA\TwoFactor_privacyIDEA\Provider;

use OCP\IUser;
use OCP\IGroupManager;
use OCP\Template;
use OCP\Http\Client\IClientService;
use OCP\ILogger;
use OCP\IConfig;
use OCP\IRequest;
use Exception;
// For OC < 9.2 the TwoFactorException does not exist. So we need to handle this in the method verifyChallenge
use OCP\Authentication\TwoFactorAuth\TwoFactorException;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IL10N;

class AdminAuthException extends Exception
{

}

class TriggerChallengesException extends Exception
{

}

class TwoFactorPrivacyIDEAProvider implements IProvider
{

    private $httpClientService;
    private $config;
    private $logger;
    private $trans;

    public function __construct(IClientService $httpClientService,
                                IConfig $config,
                                ILogger $logger, IRequest $request,
                                IGroupManager $groupManager,
                                IL10N $trans)
    {
        $this->httpClientService = $httpClientService;
        $this->config = $config;
        $this->logger = $logger;
        $this->trans = $trans;
        $this->request = $request;
        $this->hideOTPField = null;
        $this->detail = array();
        $this->transactionId = null;
        $this->u2fSignRequest = null;
        $this->groupManager = $groupManager;
    }

    /**
     * Get unique identifier of this 2FA provider
     *
     * @return string
     */
    public function getId()
    {
        return 'privacyidea';
    }

    /**
     * Get the display name for selecting the 2FA provider
     *
     * @return string
     */
    public function getDisplayName()
    {
        return 'privacyIDEA';
    }

    /**
     * Get the description for selecting the 2FA provider
     *
     * @return string
     */
    public function getDescription()
    {
        return 'privacyIDEA';
    }

    /**
     * Retrieve a value from the app's (twofactor_privacyidea) configuration store.
     *
     * @param string $key application config key
     * @return string
     */
    private function getAppValue($key)
    {
        return $this->config->getAppValue('twofactor_privacyidea', $key);
    }

    /**
     * Retrieve the privacyIDEA instance base URL from the app configuration.
     * In case the stored URL ends with '/validate/check', this suffix is removed.
     * The returned URL always ends with a slash.
     *
     * @return string
     */
    private function getBaseUrl()
    {
        $url = $this->getAppValue('url');
        // Remove the "/validate/check" suffix of $url if it exists
        $suffix = "/validate/check";
        if (substr($url, -strlen($suffix)) === $suffix) {
            $url = substr($url, 0, -strlen($suffix));
        }
        // Ensure that $url ends with a slash
        if (substr($url, -1) !== "/") {
            $url .= "/";
        }
        return $url;
    }

    /**
     * Ask privacyIDEA to trigger all challenges for a given username via
     * the /validate/triggerchallenge API request.
     * If the request was successful, return a list of messages from privacyIDEA's response.
     * If the request failed for any reason, a TriggerChallengesException is raised.
     *
     * @param string $username user for which privacyIDEA should trigger challenges
     * @return string[]
     * @throws TriggerChallengesException
     */
    private function triggerChallenges($username)
    {
        $error_message = "";
        $url = $this->getBaseUrl() . "validate/triggerchallenge";
        $options = $this->getClientOptions();
        $adminUser = $this->getAppValue('serviceaccount_user');
        $adminPassword = $this->getAppValue('serviceaccount_password');
        $realm = $this->getAppValue('realm');
        try {
            $token = $this->fetchAuthToken($adminUser, $adminPassword);
            $client = $this->httpClientService->newClient();
            $options["body"] = ["user" => $username, "realm" => $realm];
            $options["headers"] = ["PI-Authorization" => $token];
            $result = $client->post($url, $options);
            if ($result->getStatusCode() == 200) {
                $body = json_decode($result->getBody());
                if ($body->result->status === true) {
                    $detail = $body->detail;
                    $this->detail = $detail;
                    if (property_exists($detail, "transaction_ids")) {
                        // TODO: What should we do, if there was more than one transaction ID?
                        $this->transactionId = $detail->transaction_ids[0];
                    }
                    if (property_exists($detail, "attributes")) {
                        $attributes = $detail->attributes;
                        if (property_exists($attributes, "hideResponseInput")) {
                            $this->hideOTPField = $attributes->hideResponseInput;
                        }
                        // check if this is a U2F Token
                        if (property_exists($attributes, "u2fSignRequest")) {
                            $this->u2fSignRequest = $attributes->u2fSignRequest;
                        }
                    } else {
                        $this->hideOTPField = null;
                        $this->u2fSignRequest = null;
                    }
                    return $detail->messages;
                } else {
                    $error_message = $this->trans->t("Failed to trigger challenges. privacyIDEA error.");
                }
            } else {
                $error_message = $this->trans->t("Failed to trigger challenges. Wrong HTTP return code.");
            }
        } catch (AdminAuthException $e) {
            $error_message = $e->getMessage();
        } catch (Exception $e) {
            $this->logger->logException($e, ["message", $e->getMessage()]);
            $error_message = $this->trans->t("Failed to trigger challenges.");
        }
        throw new TriggerChallengesException($error_message);
    }

    /**
     * Get the template for rending the 2FA provider view
     *
     * @param IUser $user
     * @return Template
     */
    public function getTemplate(IUser $user)
    {
        $messages = [];
        if ($this->getAppValue('triggerchallenges') === '1') {
            try {
                $messages = $this->triggerChallenges($user->getUID());
            } catch (TriggerChallengesException $e) {
                $messages = [$e->getMessage()];
            }
        }
        $template = new Template('twofactor_privacyidea', 'challenge');
        $template->assign("messages", array_unique($messages));
        $template->assign("hideOTPField", $this->hideOTPField);
        $template->assign("u2fSignRequest", $this->u2fSignRequest);
        $template->assign("detail", $this->detail);
        $template->assign("transactionId", $this->transactionId);
        return $template;
    }

    /**
     * Return an associative array that contains the options that should be passed to
     * the HTTP client service when creating HTTP requests.
     * @return array
     */
    private function getClientOptions()
    {
        $checkssl = $this->getAppValue('checkssl');
        $noproxy = $this->getAppValue('noproxy');
        $options = ['headers' => ['user-agent' => "ownCloud Plugin"],
            // NOTE: Here, we check for `!== '0'` instead of `=== '1'` in order to verify certificates
            // by default just after app installation.
            'verify' => $checkssl !== '0',
            'debug' => false,
            'exceptions' => false,
            'timeout' => 5];
        if ($noproxy === "1") {
            $options["proxy"] = ["https" => "", "http" => ""];
        }
        return $options;
    }

    /**
     * Verify the given challenge.
     * In fact it is not a challenge but the OTP value!
     *
     * @param IUser $user
     * @param string $challenge
     * @return Boolean, True in case of success
     */
    public function verifyChallenge(IUser $user, $challenge)
    {
        return $this->authenticate($user->getUID(), $challenge);
    }

    /**
     * Try to authenticate the given username with the given password.
     *
     * @param string $username
     * @param string $password
     * @return Boolean, True in case of success. In case of failure, this raises
     *         a TwoFactorException (for OC >= 9.2) with a descriptive error message.
     *         If privacyIDEA returned a HTTP 200 and result->status=true, but
     *         result->value=false, the exception has a code 1 (i.e. if the user
     *         could be found, but the password was incorrect).
     */
    public function authenticate($username, $password) {
        // Read config
        $url = $this->getBaseUrl() . "validate/check";
        $realm = $this->getAppValue('realm');
        $error_message = "";
        $options = $this->getClientOptions();
        $options['body'] = ['user' => $username,
            'pass' => $password,
            'realm' => $realm];
        // The verifyChallenge is called with additional parameters in case of challenge response:
        $transaction_id = $this->request->getParam("transaction_id");
        $signatureData = $this->request->getParam("signatureData");
        $clientData = $this->request->getParam("clientData");
        $this->logger->debug("transaction_id: " . $transaction_id);
        $this->logger->debug("signatureData: " . $signatureData);
        $this->logger->debug("clientData: " . $clientData);

        if ($transaction_id) {
            // add transaction ID in case of challenge response
            $options['body']["transaction_id"] = $transaction_id;
        }

        if ($signatureData) {
            $this->logger->debug('We are doing a U2F response.');
            // here we add the signatureData and the clientData in case of U2F
            $options['body']["signaturedata"] = $signatureData;
            $options['body']["clientdata"] = $clientData;
        }

        $errorCode = 0;
        try {
            $client = $this->httpClientService->newClient();
            $res = $client->post($url, $options);
            if ($res->getStatusCode() === 200) {
                $body = $res->getBody();
                $body = json_decode($body);
                if ($body->result->status === true) {
                    if ($body->result->value === true) {
                        return true;
                    } else {
                        $error_message = $this->trans->t("Failed to authenticate.");
                        $errorCode = 1;
                    }
                } else {
                    $error_message = $this->trans->t("Failed to authenticate. privacyIDEA error.");
                }
            } else {
                $error_message = $this->trans->t("Failed to authenticate. Wrong HTTP return code.");
            }
        } catch (Exception $e) {
            $this->logger->logException($e,
                ["message", $e->getMessage()]);
            $error_message = $this->trans->t("Failed to authenticate.") . " " . $e->getMessage();
        }
        if (class_exists('OCP\Authentication\TwoFactorAuth\TwoFactorException')) {
            // This is the behaviour for OC >= 9.2
            throw new TwoFactorException($error_message, $errorCode);
        } else {
            // This is the behaviour for OC == 9.1 and NC.
            return false;
        }
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
    public function isTwoFactorAuthEnabledForUser(IUser $user)
    {        
        $piactive = $this->getAppValue('piactive');
        $piexcludegroups = $this->getAppValue('piexcludegroups');
        if ($piactive === "1") {
            // 2FA is basically enabled
            if ($piexcludegroups) {
                $this->logger->debug("excluded Groups: " . $piexcludegroups);
                // We can exclude groups from the 2FA
                $groups = explode("|", $piexcludegroups);
                foreach($groups as $group) {
                    if ($this->groupManager->isInGroup($user->getUID(), trim($group))) {
                        // The user is a group, that is allowed to pass with 1FA
                        return false;
                    };
                };
            };
            return true;
        }
        return false;
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
    public function fetchAuthToken($username, $password)
    {
        $error_message = "";
        $url = $this->getBaseUrl() . "auth";
        $options = $this->getClientOptions();
        try {
            $client = $this->httpClientService->newClient();
            $options["body"] = ["username" => $username, "password" => $password];
            $result = $client->post($url, $options);
            if ($result->getStatusCode() === 200) {
                $body = json_decode($result->getBody());
                if ($body->result->status === true) {
                    return $body->result->value->token;
                } else {
                    $error_message = $this->trans->t("Failed to fetch authentication token. privacyIDEA error.");
                }
            } else if ($result->getStatusCode() === 401) {
                $error_message = $this->trans->t("Failed to fetch authentication token. Unauthorized.");
            } else {
                $error_message = $this->trans->t("Failed to fetch authentication token. Wrong HTTP return code.");
            }
        } catch (Exception $e) {
            $this->logger->logException($e, ["message", $e->getMessage()]);
            $error_message = $this->trans->t("Failed to fetch authentication token.");
        }
        throw new AdminAuthException($error_message);
    }
}
