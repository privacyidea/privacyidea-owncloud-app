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
                                'name' => 'Settings#setURL',
                                'url' => '/url',
                                'verb' => 'POST'
                        ],
                    [
                                'name' => 'Settings#getURL',
                                'url' => '/url',
                                'verb' => 'GET'
                        ],
                        [
                                'name' => 'Settings#setCheckSSL',
                                'url' => '/checkssl',
                                'verb' => 'POST'
                        ],
                        [
                                'name' => 'Settings#getCheckSSL',
                                'url' => '/checkssl',
                                'verb' => 'GET'
                        ],
                        [
                                'name' => 'Settings#setRealm',
                                'url' => '/realm',
                                'verb' => 'POST'
                        ],
                        [
                                'name' => 'Settings#getRealm',
                                'url' => '/realm',
                                'verb' => 'GET'
                        ],
                        [
                            'name' => 'Settings#setNoProxy',
                            'url' => '/noproxy',
                            'verb' => 'POST'
                        ],
                        [
                            'name' => 'Settings#getNoProxy',
                            'url' => '/noproxy',
                            'verb' => 'GET'
                        ],
						[
							'name' => 'Settings#setTriggerChallenges',
							'url' => '/triggerchallenges',
							'verb' => 'POST'
						],
						[
							'name' => 'Settings#getTriggerChallenges',
							'url' => '/triggerchallenges',
							'verb' => 'GET'
						]
				]
        ]
);


