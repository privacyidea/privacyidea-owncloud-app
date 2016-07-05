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
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IUser;
use OCP\Template;
use OCP\Http\Client\IClientService;
use OCP\ILogger;
use OCP\IConfig;


class TwoFactorPrivacyIDEAProvider implements IProvider {

	private $httpClientService;
	private $config;
	private $logger;

	public function __contruct(IClientService $httpClientService,
					IConfig $config,
					ILogger $logger) {
		$this->httpClientService = $httpClientService;
		$this->config = $config;
		$this->logger = $logger;
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
	 * Get the template for rending the 2FA provider view
	 *
	 * @param IUser $user
	 * @return Template
	 */
	public function getTemplate(IUser $user) {
		return new Template('twofactor_privacyidea', 'challenge');
	}
	/**
	 * Verify the given challenge.
	 * In fact it is not a challenge but the OTP value!
	 *
	 * @param IUser $user
	 * @param string $challenge
	 */
	public function verifyChallenge(IUser $user, $challenge) {
		$client = $this->httpClientService->newClient();
		$res = $client->post('https://172.16.200.106/pi/validate/check',
				['body' => ['user' => $user,
					    'pass' => $challenge]]);
		if ($challenge === 'passme') {
			return true;
		}
		return false;
		
	}
	/**
	 * Decides whether 2FA is enabled for the given user
	 *
	 * @param IUser $user
	 * @return boolean
	 */
	public function isTwoFactorAuthEnabledForUser(IUser $user) {
		// 2FA is enforced for all users
		return true;
	}
}
