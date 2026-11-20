## Unreleased

### Features
* Passkeys: A user can log in with a passkey as the second factor. The login page has a "Passkey" button.
  The app gets the challenge from privacyIDEA with `/validate/initialize`, so the button needs no service account
  and no policy `passkey_trigger_by_pin`. The setting "Show the passkey button" in the admin settings hides it.
  This challenge belongs to no user, so the app compares the owner of the passkey that privacyIDEA names
  with the ownCloud user, and refuses another user.
  If privacyIDEA triggers a passkey challenge (policy `passkey_trigger_by_pin` and the setting "Trigger challenges"),
  the browser asks for the passkey by itself. The user needs a passkey token in privacyIDEA 3.11.1 or later.
  See the README.
* Support ownCloud 11. The app now needs ownCloud 10.11 or later.
* Nextcloud 12 and 13 are no longer listed. They are out of support.

### Documentation
* The README is new. It has a quick start, the login flow, a list of all settings and a troubleshooting table.

### Fixes
* Security: The excluded IP addresses use the address that ownCloud sees. The app no longer trusts the header
  `X-Forwarded-For` from a client that is not a trusted proxy. A user could skip 2FA with this header.
  If ownCloud runs behind a reverse proxy, set `trusted_proxies` in `config.php`. The proxy must replace the header
  with the address of its client. See the README.
* Security: The state of a login in the session (for example the PassOnNoUser state) belongs to one user.
  The app removes it when another user uses the session.
* Security: The log no longer contains the OTP or the PIN at debug level.
* Security: The debug log no longer prints the exception of a failed request for the token of the service account.
  The trace of that exception can contain the password of the service account.
* Security: The app no longer stores the token of the service account in the session of the user.
* Security: Error messages of the HTTP client no longer show the internal URL of the privacyIDEA server to the user.
* PassOnNoUser did not work. An HTTP error status from privacyIDEA raised an exception.
  The option that turns this off had an old name that is ignored since Guzzle 6.
* Push and TiQR: The poll request to privacyIDEA sent the transaction ID in the body of a GET request.
  ownCloud 11 does not send it. The ID is now in the query string.
* The OTP field was hidden when the app could not trigger the challenge, for example with a wrong
  service account. The user could not log in. The field is now visible and the error message is shown.
* An error from privacyIDEA during the login (server not reachable, wrong answer) is now shown on the login page
  and written to the log. It no longer ends in an internal server error.
* An empty timeout in the settings ended in an internal server error on the login page on PHP 8.
  The app now uses 5 seconds for an empty value or a value below 1.
* The buttons for the login modes did not work when the page has no OTP field, for example for a user with
  only a push token and a WebAuthn token.
* Poll in browser: The worker script was loaded from `/apps-external/`. It now loads from the directory of the app.
* Poll in browser: The option started the polling when it was off and a URL was set.
* Poll in browser: The worker no longer blocks with synchronous requests. It asks once per second
  and stops when it finishes.
* The checkbox "Poll in browser" showed the wrong state after a reload.
* IPv6 addresses work in the list of excluded IP addresses. Invalid entries are ignored.
* Removed unused files `pi-auto-submit.js` and `pi-response-status.js`.
  PassOnNoUser now continues the login without a click on a button.
* The answer of a WebAuthn or passkey login was sent with the user agent of the HTTP client ("GuzzleHttp")
  and not with "ownCloud Plugin". A privacyIDEA policy with a user agent condition did not match this request.
* The OTP field has `autocomplete="one-time-code"`. Chrome no longer fills the saved password of the user into it.
  Firefox 159 still does, because it ignores this value.
* A successful login ended in "Failed to communicate with privacyIDEA." when the policy `no_detail_on_success`
  was set. The answer of privacyIDEA then has no details, and the app expected them.

### Changes that need action
* The certificate check uses the CA bundle of ownCloud. Certificates that the admin imported with
  `occ security:certificates:import` now work.
  A private CA that exists only in the store of the operating system no longer works.
  If your privacyIDEA server uses such a CA, import its certificate with `occ security:certificates:import`.
* If ownCloud runs behind a reverse proxy and you use excluded IP addresses, set `trusted_proxies` in `config.php`.
  Without it, ownCloud sees the address of the proxy. See the README.

### Note for the release
* `appinfo/signature.json` is out of date. Sign the app again before the release.

## 3.2.0 02/2024

### Features
* Poll in browser.
* Auto-submit after X digits entered.

### Fixes
* Admin settings update.
* Security: Separate JS and CSS from the form template.
* Bugfix: Check and verify the user to test the communication with server.

## 3.1.1 03/2023

### Features
* Implementation of the preferred client mode.
* Implementation of enroll via challenge feature.

### Fixes
* Bugfix: Update CSS to suit the ownCloud X.

## 3.0, 2022-01-20

### Features
* Add WebAuthn.

### Fixes
* Fix multiple challenge response.
* Fix/update settings.
* Show PI error messages in UI.
* PassOnNoUser (user doesn't exist or user have no token assigned) is now working in every scenario.

## 2.7, 2020-06-04

* Add PassOnNoUser
  If the user, who tries to authenticate does not exist in privacyIDEA
  the user can authenticate to ownCloud without 2nd factor.
  This only works with triggerchallenge.

## 2.6, 2020-01-25

* Added support for the PUSH token.
  Use the new challenge verification mechanism
  This feature requires privacyIDEA version 3.2 or higher

## 2.5.2

* Fixed multiple trigger challenges

## 2.5.1

* Fixed multi challenge: During an authentication request
  several challenge response tokens can be used.
* Fix the correct hiding the OTP field, depending on the
  used tokens need an OTP entry field or not (e.g. HOTP/TOTP vs. U2F).  
* Display "detail"->"message" from the authentication response to 
  allow a more flexible communication with the user.
* Use U2F API v1.1

## 2.5

* Allow to exclude client IPs from the need for 2FA

## 2.4.2

* Changed view of configuration to make it more readable
* Checks if service account has needed rights
* Support NextCloud v12 and v13

## 2.4.1

* Allow "include/exclude" of groups who need 2FA
  * Allow groups to be selected
* Make http timeout configurable

## 2.4.0

* Allow 2FA to be disabled for specific user groups.
* Make the handling of the 2FA enable button consistent.
* Improve translations
* Add translation to config UI
* Add possibility to test the configuration

## 2.3.0

* Allow 2FA to be globally enabled or disabled.

## 2.2.0

* Add support for U2F devices to authenticate
  against privacyIDEA.

## 2.1.0

* Add support for challenge response
