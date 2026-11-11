# Local test stack and tests

This directory holds everything that you need to test the app in a real ownCloud with a real privacyIDEA server.
The stack runs in Docker. The compose file is `compose-dev.yml` in the repository root.

The stack has four services: ownCloud, MariaDB, Redis and privacyIDEA.
ownCloud 11 is the default. The app directory `twofactor_privacyidea/` is mounted into the ownCloud container.
You edit files on the host and ownCloud serves them at once.

There are three kinds of checks:

| Check | Command | What it does |
| --- | --- | --- |
| Syntax | `./dev/lint-php.sh` | Runs `php -l` on every PHP file with PHP 7.4 and 8.3. |
| Unit tests | `./dev/test-unit.sh` | PHPUnit tests of code that needs no ownCloud, with PHP 7.4 and 8.3. |
| End-to-end tests | `./dev/test-e2e.sh` | Playwright tests in a real browser against the stack. |

## Start the stack

1. Install Docker with Compose v2.
2. Run `./dev/setup.sh` in the repository root.
3. Open `http://localhost:8080` for ownCloud. Log in as `admin` with the password `admin`.
4. Open `http://localhost:8081` for privacyIDEA. Log in as `admin` with the password `admin`.

The script is safe to run again. It creates new tokens each time.
The new token seeds replace the old seeds in `dev/.state/default/seeds.json`.

If you change `<version>` in `appinfo/info.xml`, ownCloud asks for an upgrade of the app.
Run `docker compose -f compose-dev.yml exec owncloud occ upgrade`.

If the app cannot be enabled, read the message. The message `ownCloud 10 or lower is required` means that
`appinfo/info.xml` does not list the ownCloud version.

## Test users

The password of every test user in ownCloud is `Test1234!`.

| User | In privacyIDEA | What it tests |
| --- | --- | --- |
| `alice` | One TOTP token, no PIN | Login with an OTP value. |
| `bob` | One TOTP token and a challenge-response policy | Login after a triggered challenge. |
| `carol` | No token | A user without a token. |
| `dave` | Does not exist | PassOnNoUser. |

The ownCloud group `admin` is excluded from 2FA. This keeps the admin login simple.

To get the current OTP of a user, run `node dev/otp.mjs alice`.

## Run the end-to-end tests

1. Run `cd tests/e2e && npm install && npx playwright install chromium` once.
2. Run `./dev/test-e2e.sh` in the repository root.

Add Playwright arguments after an empty first argument, for example `./dev/test-e2e.sh "" -g "alice"`.

The tests use a real browser. They log in as the test users and change app settings over HTTP,
in the same way as the admin settings page. They never call `docker`.
Every test starts from the default settings (`DEFAULT_CONFIG` in `tests/e2e/helpers.mjs`).
Use one worker only. privacyIDEA rejects an OTP that was used before.

The ids in the test names (B1, C4, S1, E, ...) are the finding ids of the Changelog and of `journal.md`.
If a defect is known and not fixed, declare its test with `test.fail()`.
The test passes while the defect exists. When someone fixes the defect, the test reports "expected to fail".
Then change `test.fail()` to `test()`. No test uses `test.fail()` at the moment.

## Use another ownCloud version

Create a second stack next to the first one. It has its own ports, volumes and seeds:

```
DEV_ENV_FILE=dev/env.oc10 DEV_PROJECT=pi-owncloud-dev10 ./dev/setup.sh
./dev/test-e2e.sh dev/env.oc10
```

`dev/env.oc10` sets ownCloud 10.16.7, the ports 8090 and 8091, and `DEV_STATE=oc10`.
Copy the file and change `OWNCLOUD_VERSION` to test another version.

Delete the second stack with
`docker compose -f compose-dev.yml --env-file dev/env.oc10 -p pi-owncloud-dev10 down -v`.

## Test another copy of the app

Set `APP_DIR` in a file named `.env` in the repository root. The file is not in git.

```
APP_DIR=./twofactor_privacyidea
```

On Windows with Docker in WSL, use a path that WSL can read, for example `/mnt/c/Users/you/app/twofactor_privacyidea`.

## Where the files are

| Path | Content |
| --- | --- |
| `compose-dev.yml` | The services. |
| `dev/setup.sh` | Starts the stack, prepares privacyIDEA, creates users, writes the app configuration. |
| `dev/setup-privacyidea.mjs` | Creates the resolver, realm, tokens and policies through the privacyIDEA REST API. |
| `dev/lib/fixtures.mjs` | Test users and token seeds. Shared with the tests. |
| `dev/lib/totp.mjs` | A TOTP generator without packages. |
| `dev/privacyidea/users.passwd` | The users of the privacyIDEA realm. |
| `dev/lint-php.sh`, `dev/test-unit.sh`, `dev/test-e2e.sh` | The three checks. |
| `composer.json`, `phpunit.xml.dist`, `tests/unit/` | PHPUnit. The app itself needs no Composer packages. |
| `tests/e2e/` | Playwright tests. |

## Facts about the services

- The privacyIDEA image `gpappsoft/privacyidea-docker` is a community image. NetKnights does not publish it.
  The tag is set in `PRIVACYIDEA_VERSION`. The image runs one gunicorn worker and SQLite.
- privacyIDEA ignores a key that a client sends to `/token/init` when no admin policy exists.
  The setup script lets the server generate the key and reads the seed from the answer.
- The ownCloud 11 image loads external apps from `/var/www/owncloud/custom` with the URL `/custom`.
- The ownCloud changelog says that 11.1 locks an account for 10 minutes after 5 failed password logins in 15 minutes.
  No test checked this. If a login fails for no clear reason, unlock the account with
  `docker compose -f compose-dev.yml exec owncloud occ user:unlock <user>`.
- The test stack uses `http://localhost`. The browser treats it as a secure context, so WebAuthn can work there.

## Continuous integration

The workflows are in `.github/workflows/`. Every GitHub action is pinned by commit SHA.
Dependabot updates the pins. Before you pin a new action, compare the SHA with the release tag.

| Workflow | When | What it does |
| --- | --- | --- |
| `php-tests.yml` | Pull request, push to `master` | `composer run lint` and `composer run test:unit` on PHP 7.4 and 8.3. |
| `e2e.yml` | Pull request, push to `master`, every night, by hand | The Playwright tests against ownCloud 10.16.7 and 11.1.0 (pinned). At night: the floating tags `10` and `11`. |
| `semgrep.yml` | Pull request, push to `master` | Security scan. The findings do not fail the job. They show as code scanning alerts. |
| `fixup.yml` | Pull request | Blocks `fixup!` and `squash!` commits. |

Update the pinned ownCloud versions in `e2e.yml` when ownCloud releases a new version.
To run the tests against one image tag, start `e2e.yml` by hand and enter the tag, for example `11.1.0`.
If the nightly run fails, a new ownCloud release probably broke something. Open the logs and traces in the run.

The workflows `php-tests.yml` and `e2e.yml` run the same commands as `dev/lint-php.sh`, `dev/test-unit.sh` and `dev/setup.sh` with `dev/test-e2e.sh`.
The stack pulls images from Docker Hub. Docker Hub limits the pulls without login. A run can fail for this reason alone.

## Problems with Docker in WSL

- Docker runs in a WSL distro on some Windows hosts. WSL stops the distro when no session is open.
  The containers then exit with code 255. Run `docker compose -f compose-dev.yml up -d --wait` to start them again.
  To prevent this, set `vmIdleTimeout=-1` in `%UserProfile%\.wslconfig` or keep a WSL terminal open.
- Environment variables that you set in a Windows shell do not reach `docker compose`.
  Put values in `.env` or pass `--env-file`.
- Quoting breaks when a command with `sh -c '...'` goes through `wsl.exe`. Put the commands in a script file
  and mount it, as `dev/lint-php.sh` does.

## Not built yet

- PHPUnit tests for the provider with a mocked HTTP client.
- A mock privacyIDEA server. It would let tests force HTTP 500, timeouts and slow answers.
- Tests for real push tokens and WebAuthn.
  Chromium can emulate a WebAuthn device through the DevTools protocol.
- A code style check (php-cs-fixer). The code has no style configuration yet.
- Static analysis (psalm) against the ownCloud API. ownCloud has no package with API stubs.
  One option: read `lib/public` from a shallow clone of `owncloud/core` for each supported version.
