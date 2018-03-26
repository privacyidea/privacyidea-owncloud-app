<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OCA\TwoFactor_privacyIDEA\Controller;
use OC;
use OCP\AppFramework\Controller;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IConfig;


class SettingsController extends Controller {
	/** @var IL10N */
	private $l10n;
        /* configuration object */
        private $config;
	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 */
	public function __construct($appName, IRequest $request, IL10N $l10n,
                IConfig $config) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
                $this->config = $config;
	}

	/**
	 * Set a configuration value in the twofactor_privacyidea app config.
	 *
	 * @param string $key configuration key
	 * @param string $value configuration value
	 */
	public function setValue($key, $value) {
		$this->config->setAppValue("twofactor_privacyidea", $key, $value);
	}

	/**
	 * Retrive a configuration from the twofactor_privacyidea app config.
	 *
	 * @param string $key configuration key
	 * @return string
	 */
	public function getValue($key) {
		return $this->config->getAppValue("twofactor_privacyidea", $key);
	}

	/**
	 * Send a authentication request to privacyIDEA to check the connection.
	 *
	 * @param string $user user to send to privacyIDEA
	 * @param string $password password to send to privacyIDEA
	 */
	public function testAuthentication($user, $pass) {
		// instantiate our very own twofactor provider
		$provider = OC::$server->query("OCA\TwoFactor_privacyIDEA\Provider\TwoFactorPrivacyIDEAProvider");
		return $provider->authenticate($user, $pass);
	}
}
