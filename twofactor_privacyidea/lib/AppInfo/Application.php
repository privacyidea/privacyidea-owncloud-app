<?php
/**
 * @author Cornelis Kölbel <cornelius.koelbel@netknights.it>
 *
 * privacyIDEA two factor authentication
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
namespace OCA\TwoFactor_privacyIDEA\AppInfo;

use OCP\TwoFactor_privacyIDEA\Controller\SettingsController;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;


class Application extends App {
	/**
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = array()) {
		parent::__construct('twofactor_privacyidea', $urlParams);
		$container = $this->getContainer();
                /**
                * Controllers
                */               
                $container->registerService('SettingsController',
                        function(IAppContainer $c) {
                            $server = $c->query('ServerContainer');
                            return new SettingsController(
                                    $c->getAppName(),
                                    $server->getRequest(),
                                    $server->getL10N('twofactor_privacyidea')
                            );
                        });
	}
}
