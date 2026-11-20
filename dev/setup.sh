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
if [ -n "${DEV_OVERLAY:-}" ]; then DC+=(-f "$DEV_OVERLAY"); fi  # for example compose-tls.yml (HTTPS stack)
if [ -n "${DEV_PROJECT:-}" ]; then DC+=(-p "$DEV_PROJECT"); fi
OCC=("${DC[@]}" exec -T owncloud occ)

mkdir -p "dev/.state/${DEV_STATE:-default}"
"${DC[@]}" up -d --wait

# Optional: privacyIDEA reads the users of an OpenLDAP (dev/env.tls). The OpenLDAP container must reach privacyIDEA
# over the network of this stack, so connect it. The ownCloud container has no other way to 127.0.0.1:1389 of the host.
if [ -n "${PI_LDAP_URI:-}" ]; then
  ldap_container="${LDAP_CONTAINER:-pi-openldap-test}"
  network="${DEV_PROJECT:-pi-owncloud-dev}_default"
  if ! docker inspect "$ldap_container" >/dev/null 2>&1; then
    echo "The container $ldap_container does not run. Start the OpenLDAP of the privacyidea repository first." >&2
    exit 1
  fi
  if ! docker inspect "$ldap_container" --format '{{json .NetworkSettings.Networks}}' | grep -q "\"$network\""; then
    docker network connect "$network" "$ldap_container"
    echo "Connected $ldap_container to the network $network"
  fi
fi

"${DC[@]}" run --rm setup-pi

if ! "${OCC[@]}" app:enable twofactor_privacyidea; then
  echo >&2
  echo "The app could not be enabled. If the message says that the dependencies are not" >&2
  echo "fulfilled, appinfo/info.xml does not list this ownCloud version yet." >&2
  exit 1
fi

# DEV_EXTRA_USERS: more local users. They need the same names as the users in privacyIDEA (for example user001 of the OpenLDAP).
for user in alice bob carol dave erin frank ${DEV_EXTRA_USERS:-}; do
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
set_conf triggerchallenges "${DEV_TRIGGERCHALLENGES:-0}"
set_conf passOnNoUser "0"
# The ownCloud admin has no token. Exclude the admin group, so that the admin can always log in.
set_conf piexclude "1"
set_conf piexcludegroups "admin"
set_conf serviceaccount_user "admin"
set_conf serviceaccount_password "admin"

# HTTPS stack (dev/env.tls): ownCloud sits behind the proxy. It must trust the proxy and build https links.
if [ -n "${OWNCLOUD_TLS_HOST:-}" ]; then
  proxy_ip="$("${DC[@]}" exec -T owncloud getent hosts proxy | awk '{print $1}')"
  set_sys() { "${OCC[@]}" config:system:set "$@" >/dev/null; }
  set_sys trusted_domains 1 --value="$OWNCLOUD_TLS_HOST"
  set_sys overwriteprotocol --value=https
  set_sys overwritehost --value="$OWNCLOUD_TLS_HOST:$OWNCLOUD_TLS_PORT"
  set_sys overwrite.cli.url --value="https://$OWNCLOUD_TLS_HOST:$OWNCLOUD_TLS_PORT"
  set_sys trusted_proxies 0 --value="$proxy_ip"
  echo "ownCloud trusts the proxy $proxy_ip and builds https links"
fi

echo
echo "ownCloud:    http://localhost:${OWNCLOUD_PORT:-8080}   (admin / admin)"
echo "privacyIDEA: http://localhost:${PRIVACYIDEA_PORT:-8081}   (admin / admin)"
echo "Test users:  alice (TOTP), bob (TOTP + challenge-response), carol (no token), dave (not in privacyIDEA)"
echo "             erin and frank (passkeys that the e2e tests enroll)"
echo "Password for all test users in ownCloud: Test1234!"
if [ -n "${OWNCLOUD_TLS_HOST:-}" ]; then
  echo
  echo "HTTPS:       https://$OWNCLOUD_TLS_HOST:$OWNCLOUD_TLS_PORT   and   https://${PRIVACYIDEA_TLS_HOST}:${PRIVACYIDEA_TLS_PORT}"
  echo "The host names must resolve to 127.0.0.1 (hosts file). See dev/README.md."
fi
