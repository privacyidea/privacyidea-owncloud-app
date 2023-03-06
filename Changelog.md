## 3.1 03/2023

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
