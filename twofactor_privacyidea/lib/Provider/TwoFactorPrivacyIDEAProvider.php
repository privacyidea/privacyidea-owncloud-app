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
use OCP\Template;
use OCP\Http\Client\IClientService;
use OCP\ILogger;
use OCP\IConfig;
use Exception;
// For OC < 9.2 the TwoFactorException does not exist. So we need to handle this in the method verifyChallenge
use OCP\Authentication\TwoFactorAuth\TwoFactorException;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IL10N;

use GuzzleHttp;

class AdminAuthException extends Exception {

}

class TriggerChallengesException extends Exception {

}

class TwoFactorPrivacyIDEAProvider implements IProvider {

	private $httpClientService;
	private $config;
	private $logger;
    private $trans;

	public function __construct(IClientService $httpClientService,
					IConfig $config,
					ILogger $logger,
                    IL10N $trans) {
		$this->httpClientService = $httpClientService;
		$this->config = $config;
		$this->logger = $logger;
        $this->trans = $trans;
	}

	/**
	 * Get unique identifier of this 2FA provider
	 *
	 * @return string
	 */
	public function getId() {
		return 'privacyidea';
	}
	/**
	 * Get the display name for selecting the 2FA provider
	 *
	 * @return string
	 */
	public function getDisplayName() {
		return 'privacyIDEA';
	}
	/**
	 * Get the description for selecting the 2FA provider
	 *
	 * @return string
	 */
	public function getDescription() {
		return 'privacyIDEA';
	}

	/**
	 * Retrieve a value from the app's (twofactor_privacyidea) configuration store.
	 *
	 * @param string $key application config key
	 * @return string
	 */
	private function getAppValue($key) {
		return $this->config->getAppValue('twofactor_privacyidea', $key);
	}

	/**
	 * Retrieve the privacyIDEA instance base URL from the app configuration.
	 * In case the stored URL ends with '/validate/check', this suffix is removed.
	 * The returned URL always ends with a slash.
	 *
	 * @return string
	 */
	private function getBaseUrl() {
		$url = $this->getAppValue('url');
		// Remove the "/validate/check" suffix of $url if it exists
		$suffix = "/validate/check";
		if(substr($url, -strlen($suffix)) === $suffix) {
			$url = substr($url, 0, -strlen($suffix));
		}
		// Ensure that $url ends with a slash
		if(substr($url, -1) !== "/") {
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
	private function triggerChallenges($username) {
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
			if($result->getStatusCode() == 200) {
				$body = json_decode($result->getBody());
				if ($body->result->status === true) {
					return $body->detail->messages;
				} else {
					$error_message = $this->trans->t("Failed to trigger challenges. privacyIDEA error.");
				}
			} else {
				$error_message = $this->trans->t("Failed to trigger challenges. Wrong HTTP return code.");
			}
		} catch(AdminAuthException $e) {
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
	public function getTemplate(IUser $user) {
		$messages = [];
		if($this->getAppValue('triggerchallenges') === '1') {
			try {
				$messages = $this->triggerChallenges($user->getUID());
			} catch(TriggerChallengesException $e) {
				$messages = [$e->getMessage()];
			}
		}
		$template = new Template('twofactor_privacyidea', 'challenge');
		$template->assign("messages", array_unique($messages));
		return $template;
	}

	/**
	 * Return an associative array that contains the options that should be passed to
	 * the HTTP client service when creating HTTP requests.
	 * @return array
	 */
	private function getClientOptions() {
		$checkssl = $this->getAppValue('checkssl');
		$noproxy = $this->getAppValue('noproxy');
		$options = ['headers' => ['user-agent' => "ownCloud Plugin" ],
					'verify' => $checkssl !== '0',
					'debug' => false];
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
	public function verifyChallenge(IUser $user, $challenge) {
            // Read config
            $url = $this->getBaseUrl() . "validate/check";
            $realm = $this->getAppValue('realm');
            $error_message = "";
            $options = $this->getClientOptions();
            $options['body'] = ['user' => $user->getUID(),
                                'pass' => $challenge,
                                'realm' => $realm];
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
            if (class_exists('TwoFactorException')) {
                // This is the behaviour for OC >= 9.2
                throw new TwoFactorException($error_message);
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
	public function isTwoFactorAuthEnabledForUser(IUser $user) {
		// TODO: The app could configure users, who do not do 2FA
		// 2FA is enforced for all users
		return true;
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
	private function fetchAuthToken($username, $password) {
		$error_message = "";
		$url = $this->getBaseUrl() . "auth";
		$options = $this->getClientOptions();
		try {
			$client = $this->httpClientService->newClient();
			$options["body"] = ["username" => $username, "password" => $password];
			$result = $client->post($url, $options);
			if($result->getStatusCode() === 200) {
				$body = json_decode($result->getBody());
				if($body->result->status === true) {
					return $body->result->value->token;
				} else {
					$error_message = $this->trans->t("Failed to fetch authentication token. privacyIDEA error.");
				}
			} else {
				$error_message = $this->trans->t("Failed to fetch authentication token. Wrong HTTP return code.");
			}
		} catch(Exception $e) {
			$this->logger->logException($e, ["message", $e->getMessage()]);
			if($e instanceof GuzzleHttp\Exception\ClientException && $e->getCode() == 401) {
				$error_message = $this->trans->t("Failed to fetch authentication token. Unauthorized.");
			} else {
				$error_message = $this->trans->t("Failed to fetch authentication token.");
			}
		}
		throw new AdminAuthException($error_message);
	}
}
