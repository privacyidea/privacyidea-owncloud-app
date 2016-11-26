<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OCA\TwoFactor_privacyIDEA\Controller;
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
         * enable/disable SSL checking
         *
         * @param bool $checkssl
         */
        public function setCheckSSL($checkssl) {
            $value = $checkssl ? '1' : '0';
            $this->config->setAppValue('twofactor_privacyidea', 'checkssl', $value);
        }
        
        public function getCheckSSL() {
            return $this->config->getAppValue('twofactor_privacyidea', 'checkssl');
        }

        /*
         * enable/disable no Proxy
         */
        public function setNoProxy($noproxy) {
            $value = $noproxy ? '1' : '0';
            $this->config->setAppValue('twofactor_privacyidea', 'noproxy', $value);
        }
        public function getNoProxy() {
            return $this->config->getAppValue('twofactor_privacyidea', 'noproxy');
        }

        /*
         * set the privacyIDEA URL
         */
        public function setURL($url) {
            $this->config->setAppValue('twofactor_privacyidea', 'url', $url);
        }
        
        public function getUrl() {
            return $this->config->getAppValue('twofactor_privacyidea', 'url');
        }
        
        /*
         * We can define a realm other than the default realm
         */
        public function getRealm() {
            return $this->config->getAppValue('twofactor_privacyidea', 'realm');
        }
        
        public function setRealm($realm) {
            $this->config->setAppValue('twofactor_privacyidea', 'realm', $realm);
        }
        
}