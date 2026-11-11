# privacyIDEA ownCloud plugin for two-factor authentication with privacyIDEA

## Requirements

* ownCloud 10.11 or later, including ownCloud 11 (PHP 7.4 to 8.3)
* privacyIDEA server 3.1+
* privacyIDEA server 3.2+ for Push Token support

Nextcloud is not supported.

## Subscription

The privacyIDEA ownCloud App needs a subscription file from NetKnights GmbH to run productively.
See https://netknights.it/en/produkte/privacyidea-owncloud-app/

Nevertheless, you can test the privacyIDEA ownCloud App in your test environment.

## Install

Install the privacyIDEA app via the [ownCloud marketplace](https://marketplace.owncloud.com/apps/twofactor_privacyidea).

To install by hand, copy the directory ``twofactor_privacyidea`` to an app directory of your ownCloud.
The app directories are listed in ``apps_paths`` in ``config/config.php``.
In the official Docker image of ownCloud 11, the directory is ``/var/www/owncloud/custom``.
The image stores it in the volume ``/mnt/data/apps``.
Then run ``occ app:enable twofactor_privacyidea``.

## Excluded IP addresses

You can exclude client IP addresses from 2FA in the settings of the app.
The app uses the address that ownCloud sees for the client.
ownCloud only reads the header ``X-Forwarded-For`` when the connection comes from a proxy
that is listed in ``trusted_proxies`` in ``config/config.php``.
If ownCloud runs behind a reverse proxy, set ``trusted_proxies`` to the address of the proxy.
Without it, ownCloud sees the address of the proxy for every client.

## Let users log in who are not in privacyIDEA (PassOnNoUser)

If you turn this on, a user whom privacyIDEA does not know logs in without a second factor.
This only works together with "Trigger challenges".
privacyIDEA also answers "user not found" for every user when the realm in the settings is wrong.
Then all users log in without a second factor.
Make sure that the realm is correct before you turn this on. Use the test button on the settings page.

## Poll in browser

The browser of the user sends the poll requests to the URL that you set in the settings of the app.
If this URL is not on the same origin as ownCloud, privacyIDEA must allow it with CORS headers.
A reverse proxy that serves ownCloud and privacyIDEA under one origin also works.

## Development

See ``dev/README.md`` for the Docker test stack, the unit tests and the end-to-end tests.
