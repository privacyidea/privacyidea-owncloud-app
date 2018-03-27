<?php

/**
 * @author Cornelius Kölbel <cornelius.koelbel@netknights.it>
 *
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

$application = new \OCA\TwoFactor_privacyIDEA\AppInfo\Application();

$application->registerRoutes(
        $this,
        [
                'routes' => [
					[
						'name' => 'Settings#setValue',
						'url' => '/setValue',
						'verb' => 'POST'
					],
					[
						'name' => 'Settings#getValue',
						'url' => '/getValue',
						'verb' => 'GET'
					],
					[
						'name' => 'Settings#testAuthentication',
						'url' => '/testAuthentication',
						'verb' => 'POST'
					],
					[
						'name' => 'Settings#testServiceAccount',
						'url' => '/testServiceAccount',
						'verb' => 'POST'
					],
				]
		]
);


