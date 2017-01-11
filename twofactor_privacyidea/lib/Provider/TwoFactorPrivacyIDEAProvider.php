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

	public function getBaseUrl() {
		$url = $this->config->getAppValue('twofactor_privacyidea', 'url');
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
	 * Get the template for rending the 2FA provider view
	 *
	 * @param IUser $user
	 * @return Template
	 */
	public function getTemplate(IUser $user) {
		if($this->config->getAppValue('twofactor_privacyidea', 'triggerchallenges') === '1') {
			$url = $this->getBaseUrl() . "validate/triggerchallenge";
			$options = $this->getClientOptions();
			$realm = $this->config->getAppValue('twofactor_privacyidea', 'realm');
			$token = $this->fetchAuthToken("admin", "test");
			try {
				$client = $this->httpClientService->newClient();
				$options["body"] = ["user" => $user->getUID(), "realm" => $realm];
				$options["headers"] = ["PI-Authorization" => $token];
				$result = $client->post($url, $options);
			} catch (Exception $e) {
				$this->logger->logException($e, ["message", $e->getMessage()]);
			}
		}
		return new Template('twofactor_privacyidea', 'challenge');
	}

	private function getClientOptions() {
		$checkssl = $this->config->getAppValue('twofactor_privacyidea', 'checkssl');
		$noproxy = $this->config->getAppValue('twofactor_privacyidea', 'noproxy');
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
            $checkssl = $this->config->getAppValue('twofactor_privacyidea', 'checkssl');
            $realm = $this->config->getAppValue('twofactor_privacyidea', 'realm');
            $noproxy = $this->config->getAppValue('twofactor_privacyidea', 'noproxy');
            $error_message = "";
            $options = ['body' => ['user' => $user->getUID(),
                                    'pass' => $challenge,
                                    'realm' => $realm],
                        'headers' => ['user-agent' => "ownCloud Plugin" ],
                        'verify' => $checkssl !== '0',
                        'debug' => false
            ];
            if ($noproxy === "1") {
                $options["proxy"] = ["https" => "", "http" => ""];
            }
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

	public function fetchAuthToken($username, $password) {
		$url = $this->getBaseUrl() . "auth";
		$options = $this->getClientOptions();
		try {
			$client = $this->httpClientService->newClient();
			$options["body"] = ["username" => $username, "password" => "$password"];
			$result = $client->post($url, $options);
			if($result->getStatusCode() === 200) {
				$body = json_decode($result->getBody());
				if($body->result->status === true) {
					return $body->result->value->token;
				} else {
					/* TODO */
				}
			} else {
				/* TODO */
			}
		} catch(ClientException $e) {
			if($e->getCode() === 401) {
				$this->logger->error("Could not authenticate " . $username . " against privacyIDEA: 401 Unauthorized");
			} else {
				$this->logger->logException($e, ["message", $e->getMessage()]);
			}
		} catch(Exception $e) {
			$this->logger->logException($e, ["message", $e->getMessage()]);
		}
		return null;
	}
}
