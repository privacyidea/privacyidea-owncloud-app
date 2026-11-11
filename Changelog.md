## Unreleased

### Features
* Support ownCloud 11. The app now needs ownCloud 10.11 or later.
* Nextcloud 12 and 13 are no longer listed. They are out of support.

### Fixes
* Security: The excluded IP addresses use the address that ownCloud sees. The app no longer trusts the header
  `X-Forwarded-For` from the client. A user could skip 2FA with this header.
  If ownCloud runs behind a reverse proxy, set `trusted_proxies` in `config.php`. See the README.
* Security: The log no longer contains the OTP or the PIN at debug level.
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
* The certificate check uses the CA bundle of ownCloud. Certificates that the admin imported with
  `occ security:certificates:import` now work.
  A private CA that exists only in the store of the operating system no longer works.
  If your privacyIDEA server uses such a CA, import its certificate with `occ security:certificates:import`.
* Poll in browser: The worker script was loaded from `/apps-external/`. It now loads from the directory of the app.
* Poll in browser: The option started the polling when it was off and a URL was set.
* Poll in browser: The worker no longer blocks with synchronous requests. It asks once per second
  and stops when it finishes.
* The checkbox "Poll in browser" showed the wrong state after a reload.
* IPv6 addresses work in the list of excluded IP addresses. Invalid entries are ignored.
* Removed unused files `pi-auto-submit.js` and `pi-response-status.js`.
  PassOnNoUser now continues the login without a click on a button.

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
