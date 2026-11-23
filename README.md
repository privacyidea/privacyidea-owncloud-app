# privacyIDEA for ownCloud

A two-factor authentication provider for ownCloud. After the password, ownCloud asks for a second factor
and verifies it with your privacyIDEA server. Tokens and policies stay in privacyIDEA.

Supported second factors: OTP tokens (HOTP, TOTP, YubiKey and others), challenge-response tokens (SMS, email),
push, TiQR, WebAuthn security keys and passkeys.

## Requirements

- ownCloud 10.11 or later, or ownCloud 11
- PHP 7.4 to 8.3
- privacyIDEA 3.7 or later. Passkeys need 3.11.1 or later.
- HTTPS for WebAuthn and passkeys

A production setup needs a subscription file from NetKnights GmbH, see the
[product page](https://netknights.it/en/produkte/privacyidea-owncloud-app/). For tests, the app runs without it.
For Nextcloud, use the [privacyIDEA Nextcloud app](https://github.com/privacyidea/privacyidea-nextcloud-app).

## Installation

Install the app from the [ownCloud marketplace](https://marketplace.owncloud.com/apps/twofactor_privacyidea),
or copy the directory `twofactor_privacyidea` into an app directory (`apps_paths` in `config/config.php`) and run:

```
occ app:enable twofactor_privacyidea
```

In the official ownCloud 11 Docker image, the app directory is `/var/www/owncloud/custom` (volume `/mnt/data/apps`).

## Configuration

The settings are under Settings > Admin > Additional > privacyIDEA 2FA.

1. Enter the URL of the privacyIDEA server, for example `https://pi.example.com`.
2. Enter the realm if the users are not in the default realm of privacyIDEA.
3. Check the connection with "Test authentication" (a user and an OTP of that user).
4. Turn on "Activate two factor authentication with privacyIDEA".

Keep an admin session open while you test. To turn 2FA off from the command line:

```
occ config:app:set twofactor_privacyidea piactive --value=0
```

| Setting | Description | Default |
| --- | --- | --- |
| Activate two factor authentication with privacyIDEA | 2FA for all users who are not excluded. | Off |
| URL of the privacyIDEA Server | Base URL of privacyIDEA. | |
| Timeout | Seconds to wait for privacyIDEA. With the policy `push_wait`, set a higher value than `push_wait`. | 5 |
| Include groups / Exclude groups | Include: only the listed groups need 2FA. Exclude: the listed groups skip 2FA. | Exclude, no groups |
| Exclude ip addresses | Clients from these addresses skip 2FA. Single addresses, ranges (`10.0.1.12-10.0.1.113`) or a comma-separated list, IPv4 and IPv6. Behind a proxy, see [Reverse proxy](#reverse-proxy). | |
| User Realm | privacyIDEA realm of the users. Empty means the default realm. | |
| Auto Submit OTP Length | Submits the form after this many characters. Applies only to challenges that ask for an OTP. | Off |
| Verify the SSL certificate | Uses the CA bundle of ownCloud. Import a private CA with `occ security:certificates:import`. | On |
| Ignore the system wide proxy settings | Connects to privacyIDEA without the HTTP proxy of ownCloud. | Off |
| Poll in browser | The browser polls privacyIDEA for the push confirmation. See [Push and TiQR](#push-and-tiqr). | Off |
| Trigger challenges | Triggers the challenges of the user before the login page shows. Needs a service account. | Off |
| Let the user log in if the user is not found in privacyIDEA | See [Users unknown to privacyIDEA](#users-unknown-to-privacyidea). | Off |
| Show the passkey button | See [Passkeys](#passkeys). | On |

## Triggered challenges

With "Trigger challenges", the app triggers all challenges of the user (SMS, email, push and others) before
the login page shows. This needs a service account in privacyIDEA with the right `triggerchallenge`.
Enter its credentials in the settings and use "Check Credentials".

Without this setting, the user starts a challenge-response token with its PIN in the OTP field.

After a declined, expired or refused challenge, the login page offers "New challenge". It triggers new
challenges, or lets the user enter the PIN again.

## Passkeys

The user logs in with the password, then with a passkey. In privacyIDEA:

1. Set the policies `webauthn_relying_party_id` and `webauthn_relying_party_name` (scope enrollment).
   The relying party ID is the host name of ownCloud or one of its parent domains.
2. Enroll a passkey for the user.
3. Optional: set the policy `passkey_trigger_by_pin` (scope authentication) and turn on "Trigger challenges".
   The browser then asks for the passkey right after the password.

Without step 3, the user starts the passkey with the "Passkey" button. The button needs no service account.
It shows for every user, and "Show the passkey button" turns it off. The text below the button comes from the
policy `passkey_challenge_text`.

The challenge of the button belongs to no user. The app therefore accepts a passkey only if privacyIDEA names
the ownCloud user as its owner:

- The user name in privacyIDEA must equal the ownCloud user ID (case-sensitive).
- The policy `no_detail_on_success` removes the owner from the answer. The button then refuses every login.
- The answer has no realm. If two realms have users with the same name, set the policy `add_resolver_in_response`.
  The app then also compares the realm with "User Realm". privacyIDEA 3.14 and later check the realm themselves.

## Push and TiQR

The login page polls privacyIDEA until the user confirms the login on the phone. It stops after a decline
(privacyIDEA 3.9 and later), after 120 seconds or after an error, and offers "New challenge".

With "Poll in browser", the browser polls privacyIDEA directly at the "Poll in browser URL". If this URL has
another origin than ownCloud, privacyIDEA must allow it with CORS headers.

## Enrollment at login

The policy `enroll_via_multichallenge` works for HOTP, TOTP, email, SMS and push tokens. The login page shows
the QR code and the otpauth link of the new token. The app cannot enroll smartphone containers or passkeys,
and the user cannot skip an optional enrollment.

## Reverse proxy

Behind a reverse proxy, set these values in `config/config.php`:

| Value | Needed for |
| --- | --- |
| `trusted_proxies` | Excluded IP addresses. Without it, ownCloud sees the address of the proxy for every client. |
| `overwriteprotocol`, `overwritehost` | WebAuthn and passkeys. The app sends the origin of ownCloud to privacyIDEA, and the browser must sign for the same origin. |

The proxy must replace the header `X-Forwarded-For`, not append to it. ownCloud uses the first address in the
header. With an appended header, a client chooses its own address and skips 2FA.
For nginx: `proxy_set_header X-Forwarded-For $remote_addr;`.

## Users unknown to privacyIDEA

With "Let the user log in if the user is not found in privacyIDEA", users that privacyIDEA does not know log in
without a second factor. A wrong "User Realm" makes every user unknown. Check the realm with "Test authentication"
before you turn this on.

## Logging and troubleshooting

The app logs under the app name `privacyIDEA`. For details, run `occ log:manage --level debug`.
The log does not contain OTPs, PINs or the secrets of enrolled tokens.

| Problem | Cause and fix |
| --- | --- |
| "Failed to communicate with privacyIDEA." | privacyIDEA is not reachable, or its answer is not valid. Check the URL, the timeout and the proxy setting. |
| "Failed to fetch authentication token." | The service account is wrong, or it lacks the right `triggerchallenge`. Use "Check Credentials". |
| Certificate error | The app uses the CA bundle of ownCloud. Import the CA with `occ security:certificates:import`. |
| Excluded IP addresses have no effect behind a proxy | Set `trusted_proxies`. |
| Every user logs in without 2FA | PassOnNoUser is on, and "User Realm" is wrong. |
| No "Passkey" button | privacyIDEA is older than 3.11.1, the relying party policies are missing, or the button is off. The debug log shows the reason. |
| WebAuthn or passkey login fails behind a proxy | Set `overwriteprotocol` and `overwritehost`. The log shows both origins. |
| The "Passkey" button refuses the right passkey | The user name in privacyIDEA differs from the ownCloud user ID, or `no_detail_on_success` is set. The log shows the names. |
| Firefox fills the saved password into the OTP field | Firefox ignores `autocomplete="one-time-code"`. Remove the saved login of the site. |

## Development

See [dev/README.md](dev/README.md) for the Docker test stack, the tests and the release process.

## License

AGPL-3.0, see `LICENSE`. Copyright NetKnights GmbH.

[privacyIDEA documentation](https://privacyidea.readthedocs.io) ·
[Report a problem](https://github.com/privacyidea/privacyidea-owncloud-app/issues) ·
[Changelog](Changelog.md)
