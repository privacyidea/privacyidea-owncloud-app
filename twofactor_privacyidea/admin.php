<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$app = new \OCA\TwoFactor_privacyIDEA\AppInfo\Application();
$controller = $app->getContainer()->query('SettingsController');
return $controller->displayAdminPanel()->render();