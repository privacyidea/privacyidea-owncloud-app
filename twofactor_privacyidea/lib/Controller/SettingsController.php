<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OCA\TwoFactor_privacyIDEA\Controller;
use Exception;
use OC;
use OCP\AppFramework\Controller;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IConfig;
use OCP\AppFramework\Http\DataResponse;
use OCP\Authentication\TwoFactorAuth\TwoFactorException;

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
		$status = "error";
		try {
			$result = $provider->authenticate($user, $pass);
			if($result) {
				$message = $this->l10n->t("Communication to the privacyIDEA server succeeded. The user was successfully authenticated.");
				$status = "success";
			} else {
				// only happens for OC==9 and NC. In this case, we cannot know why authentication failed.
				$message = $this->l10n->t("Failed to authenticate.");
			}
		} catch (Exception $e) {
			if (class_exists('OCP\Authentication\TwoFactorAuth\TwoFactorException')
			    && $e instanceof TwoFactorException
			    && $e->getCode() == 1) {
				// in this case, privacyIDEA worked correctly, but the password was wrong
				$message = $this->l10n->t("Communication to the privacyIDEA server succeeded. However, the user failed to authenticate.");
				$status = "success";
			} else {
				$message = $e->getMessage();
			}
		}
		return new DataResponse(['status' => $status, 'data' => [ 'message' => $message ]]);
	}

	/**
	 * Check if the configured service account credentials are correct.
	 */
	public function testServiceAccount($user, $pass) {
		$provider = OC::$server->query("OCA\TwoFactor_privacyIDEA\Provider\TwoFactorPrivacyIDEAProvider");
		$status = "error";
		try {
			$token = $provider->fetchAuthToken($user, $pass);
			$message = $this->l10n->t("The service account credentials are correct!");
			$status = "success";
		} catch (Exception $e) {
			$message = $e->getMessage();
		}
		return new DataResponse(['status' => $status, 'data' => [ 'message' => $message ]]);
	}
}
