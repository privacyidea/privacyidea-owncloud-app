<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OCA\TwoFactor_privacyIDEA\Controller;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;


class SettingsController extends Controller {
	/** @var IL10N */
	private $l10n;
	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 */
	public function __construct($appName, IRequest $request, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
	}

        /**
	 * @return Http\TemplateResponse
	 */
	public function displayAdminPanel() {
            // example params from user_saml
            // TODO: Change this to our needs
		$serviceProviderFields = [
			'x509cert' => $this->l10n->t('X.509 certificate of the Service Provider'),
			'privateKey' => $this->l10n->t('Private key of the Service Provider'),
		];
		$securityOfferFields = [
			'nameIdEncrypted' => $this->l10n->t('Indicates that the nameID of the <samlp:logoutRequest> sent by this SP will be encrypted.'),
			'authnRequestsSigned' => $this->l10n->t('Indicates whether the <samlp:AuthnRequest> messages sent by this SP will be signed. [Metadata of the SP will offer this info]'),
			'logoutRequestSigned' => $this->l10n->t('Indicates whether the  <samlp:logoutRequest> messages sent by this SP will be signed.'),
			'logoutResponseSigned' => $this->l10n->t('Indicates whether the  <samlp:logoutResponse> messages sent by this SP will be signed.'),
			'signMetadata' => $this->l10n->t('Whether the metadata should be signed.'),
		];
		$securityRequiredFields = [
			'wantMessagesSigned' => $this->l10n->t('Indicates a requirement for the <samlp:Response>, <samlp:LogoutRequest> and <samlp:LogoutResponse> elements received by this SP to be signed.'),
			'wantAssertionsSigned' => $this->l10n->t('Indicates a requirement for the <saml:Assertion> elements received by this SP to be signed. [Metadata of the SP will offer this info]'),
			'wantAssertionsEncrypted' => $this->l10n->t('Indicates a requirement for the <saml:Assertion> elements received by this SP to be encrypted.'),
			'wantNameId' => $this->l10n->t(' Indicates a requirement for the NameID element on the SAMLResponse received by this SP to be present.'),
			'wantNameIdEncrypted' => $this->l10n->t('Indicates a requirement for the NameID received by this SP to be encrypted.'),
			'wantXMLValidation' => $this->l10n->t('Indicates if the SP will validate all received XMLs.'),
		];
		$generalSettings = [
			'uid_mapping' => [
				'text' => $this->l10n->t('Attribute to map the UID to.'),
				'type' => 'line',
				'required' => true,
			],
			'require_provisioned_account' => [
				'text' => $this->l10n->t('Only allow authentication if an account is existent on some other backend. (e.g. LDAP)'),
				'type' => 'checkbox',
			],
		];
		$params = [
			'sp' => $serviceProviderFields,
			'security-offer' => $securityOfferFields,
			'security-required' => $securityRequiredFields,
			'general' => $generalSettings,
		];
		return new Http\TemplateResponse($this->appName, 'admin', $params, 'blank');
	}
        
}