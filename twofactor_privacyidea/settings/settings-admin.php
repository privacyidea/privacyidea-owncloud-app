<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use OCP\Template;

$tmpl = new Template('twofactor_privacyidea', 'settings-admin');
return $tmpl->fetchPage();