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
use OCP\ISession;
use OCP\AppFramework\Http\DataResponse;
use OCP\Authentication\TwoFactorAuth\TwoFactorException;

class SettingsController extends Controller
{
    /** @var IL10N */
    private $trans;
    /* configuration object */
    private $config;
    /**
     * @var ISession
     */
    private $session;

    /**
     * @param string $appName
     * @param IRequest $request
     * @param IL10N $trans
     */
    public function __construct($appName, IRequest $request, IL10N $trans,
                                IConfig $config, ISession $session)
    {
        parent::__construct($appName, $request);
        $this->trans = $trans;
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Set a configuration value in the twofactor_privacyidea app config.
     *
     * @param string $key configuration key
     * @param string $value configuration value
     */
    public function setValue($key, $value)
    {
        $this->config->setAppValue("twofactor_privacyidea", $key, $value);
    }

    /**
     * Retrive a configuration from the twofactor_privacyidea app config.
     *
     * @param string $key configuration key
     * @return string
     */
    public function getValue($key)
    {
        return $this->config->getAppValue("twofactor_privacyidea", $key);
    }

    /**
     * Send a authentication request to privacyIDEA to check the connection.
     *
     * @param string $user user to send to privacyIDEA
     * @param string $password password to send to privacyIDEA
     */
    public function testAuthentication($user, $pass)
    {
        // instantiate our very own twofactor provider
        $provider = OC::$server->query("OCA\TwoFactor_privacyIDEA\Provider\TwoFactorPrivacyIDEAProvider");
        $status = "error";
        try {
            $result = $provider->authenticate($user, $pass);
            if ($result) {
                $message = $this->trans->t("Communication to the privacyIDEA server succeeded. The user was successfully authenticated.");
                $status = "success";
            } else {
                // only happens for OC==9 and NC. In this case, we cannot know why authentication failed.
                $message = $this->trans->t("Failed to authenticate.");
            }
        } catch (Exception $e) {
            if (class_exists('OCP\Authentication\TwoFactorAuth\TwoFactorException')
                && $e instanceof TwoFactorException
                && $e->getCode() == 1) {
                // in this case, privacyIDEA worked correctly, but the password was wrong
                $message = $this->trans->t("Communication to the privacyIDEA server succeeded. However, the user failed to authenticate.");
                $status = "success";
            } else {
                $message = $e->getMessage();
            }
        }
        return new DataResponse(['status' => $status, 'data' => ['message' => $message]]);
    }

    /**
     * Check if the configured service account credentials are correct.
     */
    public function testServiceAccount($user, $pass)
    {
        $provider = OC::$server->query("OCA\TwoFactor_privacyIDEA\Provider\TwoFactorPrivacyIDEAProvider");
        $status = "error";
        try {
            $token = $provider->fetchAuthToken($user, $pass);
            $message = $this->trans->t("The service account credentials are correct!");
            if ($this->session->get("pi_outdated")) {
                $updateMessage = $this->trans->t("But we recommend to update your privacyIDEA server.");
                $message = $message . " " . $updateMessage;
            }
            $status = "success";
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        return new DataResponse(['status' => $status, 'data' => ['message' => $message]]);
    }
}
