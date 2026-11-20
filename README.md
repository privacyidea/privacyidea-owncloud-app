# privacyIDEA for ownCloud

Add a second factor to the ownCloud login. The app sends the second factor to your own privacyIDEA server.
The user gets in when privacyIDEA agrees.

## What you get

* **Many second factors, one app.** privacyIDEA manages the tokens. The login page shows the right input for each token.
* **Your server, your data.** privacyIDEA can run on your own servers.
* **Control for admins.** You choose which groups and which IP addresses need a second factor.
* **Passkeys.** A user can log in with the fingerprint, the face or the PIN of a device.

| Second factor | What the user does | Needs |
| --- | --- | --- |
| One-time password (OTP), for example Google Authenticator, privacyIDEA Authenticator, YubiKey, key fob or OTP card | Types the code | privacyIDEA 3.1 |
| Challenge-response, for example SMS or email | Receives a code and types it | The setting "Trigger challenges" |
| Push | Confirms the login on the phone | privacyIDEA 3.2 |
| TiQR | Scans the QR code with the TiQR app | privacyIDEA 3.1 |
| WebAuthn and U2F | Touches a security key | HTTPS |
| Passkey | Confirms with the device | privacyIDEA 3.11.1, HTTPS |

## Requirements

* ownCloud 10.11 or later (ownCloud 11 works too)
* PHP 7.4 to 8.3
* privacyIDEA 3.1 or later. Push needs 3.2. Passkeys need 3.11.1.

The app needs a subscription file from NetKnights GmbH to run in production.
See the [product page](https://netknights.it/en/produkte/privacyidea-owncloud-app/).
You can test the app in a test environment without it.

This app is for ownCloud. For Nextcloud, use the [privacyIDEA Nextcloud app](https://github.com/privacyidea/privacyidea-nextcloud-app).

## Install

From the marketplace: Install the app from the [ownCloud marketplace](https://marketplace.owncloud.com/apps/twofactor_privacyidea).

By hand:

1. Copy the directory `twofactor_privacyidea` to an app directory of your ownCloud.
   The entry `apps_paths` in `config/config.php` lists the app directories.
   In the official Docker image of ownCloud 11, the directory is `/var/www/owncloud/custom`.
   The image stores it in the volume `/mnt/data/apps`.
2. Run `occ app:enable twofactor_privacyidea`.

## Quick start

From install to the first login:

1. Log in as admin. Open "Settings", then "Admin", then "Additional". The section is "privacyIDEA 2FA".
2. Enter the base URL of your privacyIDEA server in "URL of the privacyIDEA Server", for example `https://pi.example.com`.
3. If your users are not in the default realm of privacyIDEA, enter the realm in "User Realm".
4. Under "Test authentication", enter a user and the OTP of that user in the field "Password". Click "Test".
   The message shows if privacyIDEA accepts them.
5. Turn on "Activate two factor authentication with privacyIDEA".
6. Open a private browser window and log in as a test user.

Keep one admin session open while you test. A wrong setting can lock users out.
If it happens, run `occ config:app:set twofactor_privacyidea piactive --value=0` to turn the second factor off.

## How the login works

1. The user enters the ownCloud password. ownCloud checks it.
2. ownCloud asks the app for the second factor. The app skips the users that you excluded.
3. The app shows its page. With "Trigger challenges", the app first asks privacyIDEA to send the challenges of the user.
4. The user answers on the page. The app sends the answer to privacyIDEA.
5. If privacyIDEA accepts the answer, ownCloud opens the files.

The page offers the second factors that the user has. The buttons under "Alternate Login Options" switch between them.
Without "Trigger challenges", the user types the OTP or the PIN in the OTP field.
privacyIDEA can answer with a challenge, and the page shows it.

## Settings

The settings are in "Settings", "Admin", "Additional", section "privacyIDEA 2FA".

| Setting | What it does | Default |
| --- | --- | --- |
| Activate two factor authentication with privacyIDEA | Turns the second factor on for all users that you did not exclude. | Off |
| URL of the privacyIDEA Server | The base URL of privacyIDEA. The URL of `/validate/check` also works. | Empty |
| Timeout | The seconds that the app waits for privacyIDEA. A value below 1 uses the default. | 5 |
| Include groups or Exclude groups | Include: Only the listed groups need 2FA. Exclude: The listed groups skip 2FA. | Exclude, no group |
| Exclude ip addresses | Clients at these addresses skip 2FA. Use single addresses, ranges like `10.0.1.12-10.0.1.113`, or a list with commas. IPv6 works too. See "Excluded IP addresses". | Empty |
| User Realm | The privacyIDEA realm of the users, if it is not the default realm. | Empty |
| Auto Submit OTP Length | The page sends the form by itself after this many characters. Digits only. | Off |
| Verify the SSL certificate | Checks the certificate of privacyIDEA. Do not turn this off in production. | On |
| Ignore the system wide proxy settings | Sends the requests to privacyIDEA directly, without the proxy of ownCloud. | Off |
| Poll in browser, Poll in browser URL | Lets the browser ask privacyIDEA for the confirmation of a push token. See "Push tokens". | Off |
| Test authentication | Sends a user and a password to privacyIDEA and shows the answer. | |
| Trigger challenges | Asks privacyIDEA to send the challenges of the user. Needs the service account. | Off |
| Let the user log in if the user is not found in privacyIDEA | See "Users who are not in privacyIDEA". | Off |
| Service account | The account that triggers the challenges. | Empty |
| Show the passkey button | Shows the "Passkey" button on the login page. See "Passkeys". | On |

## Challenge-response and the service account

"Trigger challenges" makes privacyIDEA send the code of an SMS or email token before the user types anything.

1. In privacyIDEA, create an admin account with the right "triggerchallenge".
2. In the settings of the app, enter its name and password under "Challenge Response".
3. Click "Check Credentials". The message shows if the account works.
4. Turn on "Trigger challenges".

## Passkeys

A user can answer the second factor with a passkey. The user logs in with the password first.
Then the user starts the passkey in one of two ways:

* The login page has a "Passkey" button. The user clicks it, and the browser asks for the passkey.
  The app gets the challenge from privacyIDEA with `/validate/initialize`. This needs no service account.
* privacyIDEA triggers a passkey challenge. The browser then asks for the passkey by itself.
  If the user cancels, the "Passkey" button is there to try again.

To set this up:

1. In privacyIDEA, set the policies `webauthn_relying_party_id` and `webauthn_relying_party_name` (scope "enrollment").
   The relying party id is the host name of ownCloud, for example `cloud.example.com`.
2. Enroll a passkey for the user in privacyIDEA.
3. Optional: For the automatic prompt, set the policy `passkey_trigger_by_pin` (scope "authentication") in privacyIDEA.
   Without it, privacyIDEA sends no passkey challenge to the app.
   Then turn on "Trigger challenges" in the settings of the app.

The button is on by default. To hide it, clear "Show the passkey button" in the settings of the app.
Then the page shows the button only if privacyIDEA triggered a passkey challenge.
The button is there for every user, also for a user without a passkey. If the browser finds no passkey,
the page goes back to the OTP field.

The browser allows passkeys only on HTTPS or on `http://localhost`.
The relying party id must be the host name that the user opens in the browser, or its parent domain.

### Which user logs in

The challenge of the button belongs to no user. privacyIDEA accepts the passkey of any user for it,
and its answer names the owner of the passkey. The app compares this name with the name of the ownCloud user.
It does this for every answer that accepts the passkey, also when privacyIDEA chains the enrollment of a new token
to it (`enroll_via_multichallenge`):

* If the names are equal, the login continues.
* If the names are different, the app refuses the login and writes both names to the log.
* If the answer has no user name, the app refuses the login. The policy `no_detail_on_success` removes the
  user name, so do not use this policy together with the button.

The names must be equal, also in upper and lower case.
With the policy `add_resolver_in_response`, the answer also has the realm of the owner.
If you set a realm in the settings of the app, the app then compares the realm too.
Without this policy, and before privacyIDEA 3.14, the passkey of a user with the same name in another realm passes
this check. privacyIDEA 3.14 and later also check the owner of the passkey, because the app sends the user name
(and the realm, if you set one).

A triggered challenge belongs to the passkeys of the user. privacyIDEA checks this, so the app does not compare names.

### Old challenges

The app asks privacyIDEA for a new challenge after 60 seconds and after each answer.
If the button is on, this also replaces a triggered challenge that is older than 60 seconds.
privacyIDEA keeps a challenge for 120 seconds by default.
If privacyIDEA gives no challenge for the button, the app asks again after 60 seconds.

### Limits

* The login page cannot enroll a passkey (enroll via multichallenge).
* Do not give one user a passkey and a WebAuthn token while `passkey_trigger_by_pin` is set.
  privacyIDEA sends a separate challenge for each type, and a client can answer only one of them.

## Push tokens

For a push token or a TiQR token, the page waits until the user confirms the login on the phone.
By default, the page sends its form every 2 to 4 seconds. ownCloud then asks privacyIDEA for the result.

With "Poll in browser", the browser of the user sends the poll requests to the URL that you set in the settings of the app.
If this URL is not on the same origin as ownCloud, privacyIDEA must allow it with CORS headers.
A reverse proxy that serves ownCloud and privacyIDEA under one origin also works.

## Excluded IP addresses

You can exclude client IP addresses from 2FA in the settings of the app.
The app uses the address that ownCloud sees for the client.
ownCloud only reads the header `X-Forwarded-For` when the connection comes from a proxy
that is listed in `trusted_proxies` in `config/config.php`.
If ownCloud runs behind a reverse proxy, set `trusted_proxies` to the address of the proxy.
Without it, ownCloud sees the address of the proxy for every client.

ownCloud uses the first address in the header. The proxy must replace the header with the address of its client.
If the proxy appends the address of its client to a header that the client sent, the client can choose the first
address and skip 2FA. For nginx, use `proxy_set_header X-Forwarded-For $remote_addr;`.

## Users who are not in privacyIDEA

If you turn on "Let the user log in if the user is not found in privacyIDEA", a user whom privacyIDEA does not know
logs in without a second factor (PassOnNoUser).
This works with and without "Trigger challenges". Without it, the user sends the OTP form once.
The login then continues without a second factor.

privacyIDEA also answers "user not found" for every user when the realm in the settings is wrong.
Then all users log in without a second factor.
Make sure that the realm is correct before you turn this on. Use the test button on the settings page.

## Troubleshooting

The app writes to the ownCloud log under the app name `privacyIDEA`.
To see every request, run `occ log:manage --level debug`. The log does not contain the OTP or the PIN.

| Problem | Cause | What to do |
| --- | --- | --- |
| "Failed to communicate with privacyIDEA." | The server is not reachable, or the answer is wrong. | Check "URL of the privacyIDEA Server", "Timeout" and the proxy setting. Read the log. |
| "Failed to fetch authentication token." or "Check if service account has correct permissions" | The service account is wrong, or it lacks the right `triggerchallenge`. | Click "Check Credentials". |
| A certificate error | The app uses the CA bundle of ownCloud. A CA that exists only in the store of the operating system does not work. | Import the CA with `occ security:certificates:import`. |
| Clients in an excluded IP range still need 2FA behind a proxy | ownCloud sees the address of the proxy. | Set `trusted_proxies` in `config/config.php`. |
| Every user skips 2FA | "Let the user log in if the user is not found in privacyIDEA" is on, and the realm is wrong. | Correct "User Realm". Test again. |
| The page has no "Passkey" button | privacyIDEA is older than 3.11.1, the policies for the relying party are missing, or "Show the passkey button" is off. | Check the policies and the setting. The debug log shows why privacyIDEA sent no challenge. |
| The browser refuses a passkey or a security key | The page is not on HTTPS. | Use HTTPS. `http://localhost` also works for tests. |
| The "Passkey" button gives "Failed to authenticate." for the right passkey | The user name in privacyIDEA is not equal to the ownCloud user name (also in upper and lower case), or the policy `no_detail_on_success` is set. | Read the log. It shows both names, or that the answer had no user name. |
| Firefox fills the saved password of the user into the OTP field | The field has `autocomplete="one-time-code"`. Chrome follows it. Firefox 159 does not. | Remove the saved login of the site in Firefox, or use another browser. |

## Development

See `dev/README.md` for the Docker test stack, the unit tests, the end-to-end tests and the release steps.

```
./dev/setup.sh        # start ownCloud and privacyIDEA in Docker, with test users
./dev/test-unit.sh    # unit tests on PHP 7.4 and 8.3
./dev/test-e2e.sh     # browser tests against the stack
```

## Links

* [privacyIDEA documentation](https://privacyidea.readthedocs.io)
* [Product page](https://netknights.it/en/produkte/privacyidea-owncloud-app/)
* [Report a problem](https://github.com/privacyidea/privacyidea-owncloud-app/issues)
* [Changelog](Changelog.md)

The app is free software under the AGPL-3.0 license. See `LICENSE`. Copyright NetKnights GmbH.
