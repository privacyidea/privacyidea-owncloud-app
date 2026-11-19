#!/usr/bin/env bash
# One-time (and repeatable) setup of the local test stack.
#   ./dev/setup.sh
# Starts the stack, prepares privacyIDEA (realm, tokens, policies), creates the ownCloud
# test users and writes the app configuration. Needs Docker with Compose v2.
set -euo pipefail
cd "$(dirname "$0")/.."

# Optional: DEV_ENV_FILE=dev/env.oc10 DEV_PROJECT=pi-owncloud-dev10 ./dev/setup.sh  (second stack, old ownCloud)
# The environment of this script does not reach Docker (it runs inside WSL on some hosts),
# so the values are passed as arguments.
DC=(docker compose -f compose-dev.yml)
if [ -n "${DEV_ENV_FILE:-}" ]; then
  DC+=(--env-file "$DEV_ENV_FILE")
  set -a; . "$DEV_ENV_FILE"; set +a
fi
if [ -n "${DEV_PROJECT:-}" ]; then DC+=(-p "$DEV_PROJECT"); fi
OCC=("${DC[@]}" exec -T owncloud occ)

mkdir -p "dev/.state/${DEV_STATE:-default}"
"${DC[@]}" up -d --wait
"${DC[@]}" run --rm setup-pi

if ! "${OCC[@]}" app:enable twofactor_privacyidea; then
  echo >&2
  echo "The app could not be enabled. If the message says that the dependencies are not" >&2
  echo "fulfilled, appinfo/info.xml does not list this ownCloud version yet." >&2
  exit 1
fi

for user in alice bob carol dave; do
  if "${OCC[@]}" user:list | grep -q "^  - $user:"; then
    echo "ownCloud user $user exists"
  else
    "${DC[@]}" exec -T -e OC_PASS='Test1234!' owncloud occ user:add \
      --password-from-env --display-name="Test $user" "$user"
  fi
done

set_conf() { "${OCC[@]}" config:app:set twofactor_privacyidea "$1" --value="$2" >/dev/null; }
set_conf url "http://privacyidea:8080"
set_conf realm "dev"
set_conf checkssl "0"
set_conf pitimeout "5"
set_conf piactive "1"
set_conf triggerchallenges "0"
set_conf passOnNoUser "0"
# The ownCloud admin has no token. Exclude the admin group, so that the admin can always log in.
set_conf piexclude "1"
set_conf piexcludegroups "admin"
set_conf serviceaccount_user "admin"
set_conf serviceaccount_password "admin"

echo
echo "ownCloud:    http://localhost:${OWNCLOUD_PORT:-8080}   (admin / admin)"
echo "privacyIDEA: http://localhost:${PRIVACYIDEA_PORT:-8081}   (admin / admin)"
echo "Test users:  alice (TOTP), bob (TOTP + challenge-response), carol (no token), dave (not in privacyIDEA)"
echo "Password for all test users in ownCloud: Test1234!"
