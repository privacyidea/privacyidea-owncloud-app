#!/bin/bash

echo "scp files to owncloud - server 10.0.5.11 "
cd privacyidea-owncloud-app/twofactor_privacyidea
scp -r * root@10.0.5.11:/var/www/owncloud/apps-external/twofactor_privacyidea

#scp -r * root@10.0.5.81:/usr/share/simplesamlphp/modules/privacyidea
# scp -r * root@10.0.5.21:/var/simplesamlphp/modules/privacyidea