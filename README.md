# privacyIDEA ownCloud plugin for two-factor authentication with privacyIDEA

## Requirements

* ownCloud 10.11 or later, including ownCloud 11 (PHP 7.4 to 8.3)
* privacyIDEA server 3.1+
* privacyIDEA server 3.2+ for Push Token support
* privacyIDEA server 3.11.1+ for passkeys

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

ownCloud uses the first address in the header. The proxy must replace the header with the address of its client.
If the proxy appends the address of its client to a header that the client sent, the client can choose the first
address and skip 2FA. For nginx, use `proxy_set_header X-Forwarded-For $remote_addr;`.

## Let users log in who are not in privacyIDEA (PassOnNoUser)

If you turn this on, a user whom privacyIDEA does not know logs in without a second factor.
This works with and without "Trigger challenges". Without it, the user sends the OTP form once.
The login then continues without a second factor.
privacyIDEA also answers "user not found" for every user when the realm in the settings is wrong.
Then all users log in without a second factor.
Make sure that the realm is correct before you turn this on. Use the test button on the settings page.

## Passkeys

A user can answer the second factor with a passkey. The user logs in with the password first.
Then the browser asks for the passkey. If the user cancels, the login page shows a "Passkey" button to try again.

To set this up:

1. In privacyIDEA, set the policies `webauthn_relying_party_id` and `webauthn_relying_party_name` (scope "enrollment").
   The relying party id is the host name of ownCloud, for example `cloud.example.com`.
2. In privacyIDEA, set the policy `passkey_trigger_by_pin` (scope "authentication").
   Without it, privacyIDEA sends no passkey challenge to the app.
3. In the settings of the app, turn on "Trigger challenges".
4. Enroll a passkey for the user in privacyIDEA.

The browser allows passkeys only on HTTPS or on `http://localhost`.
The relying party id must be the host name that the user opens in the browser, or its parent domain.
The app sends the user name together with the passkey answer. privacyIDEA then refuses a passkey of another user.

Limits:

* The login page cannot enroll a passkey (enroll via multichallenge).
* Do not give one user a passkey and a WebAuthn token while `passkey_trigger_by_pin` is set.
  privacyIDEA sends a separate challenge for each type, and a client can answer only one of them.

## Poll in browser

The browser of the user sends the poll requests to the URL that you set in the settings of the app.
If this URL is not on the same origin as ownCloud, privacyIDEA must allow it with CORS headers.
A reverse proxy that serves ownCloud and privacyIDEA under one origin also works.

## Development

See ``dev/README.md`` for the Docker test stack, the unit tests and the end-to-end tests.
