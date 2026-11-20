#!/usr/bin/env bash
# Let the test ownCloud use the OpenLDAP of the privacyIDEA dev services (container pi-openldap-test).
#   ./dev/setup-ldap.sh
# The script is safe to run again. It does three things:
#   1. Connects the OpenLDAP container to the network of the test stack. The ownCloud container cannot reach
#      127.0.0.1:1389 of the host, because 127.0.0.1 is the container itself.
#   2. Enables the app user_ldap.
#   3. Writes one LDAP configuration (base dc=example,dc=org, users in ou=people, groups in ou=groups).
# The users are user001 to user100 with the password Test1234!. They do not exist in the privacyIDEA realm "dev".
# Start the OpenLDAP first. In the privacyidea repository: docker compose -f compose-dev.yml up -d openldap-test
set -euo pipefail
cd "$(dirname "$0")/.."

# Same options as dev/setup.sh: DEV_ENV_FILE=dev/env.oc10 DEV_PROJECT=pi-owncloud-dev10 ./dev/setup-ldap.sh
DC=(docker compose -f compose-dev.yml)
if [ -n "${DEV_ENV_FILE:-}" ]; then
  DC+=(--env-file "$DEV_ENV_FILE")
  set -a; . "$DEV_ENV_FILE"; set +a
fi
if [ -n "${DEV_PROJECT:-}" ]; then DC+=(-p "$DEV_PROJECT"); fi
OCC=("${DC[@]}" exec -T owncloud occ)

LDAP_CONTAINER="${LDAP_CONTAINER:-pi-openldap-test}"
LDAP_BIND_DN="${LDAP_BIND_DN:-cn=admin,dc=example,dc=org}"
LDAP_BIND_PASSWORD="${LDAP_BIND_PASSWORD:-admin}"
LDAP_BASE="${LDAP_BASE:-dc=example,dc=org}"

# 1. Network. The name of the network belongs to the Compose project, so ask Docker for it.
if ! docker inspect "$LDAP_CONTAINER" >/dev/null 2>&1; then
  echo "The container $LDAP_CONTAINER does not run. Start the OpenLDAP of the privacyidea repository first." >&2
  exit 1
fi
oc_container="$("${DC[@]}" ps -q owncloud)"
network="$(docker inspect "$oc_container" --format '{{json .NetworkSettings.Networks}}' \
  | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>console.log(Object.keys(JSON.parse(s))[0]))')"
if docker inspect "$LDAP_CONTAINER" --format '{{json .NetworkSettings.Networks}}' | grep -q "\"$network\""; then
  echo "$LDAP_CONTAINER is already in the network $network"
else
  docker network connect "$network" "$LDAP_CONTAINER"
  echo "Connected $LDAP_CONTAINER to the network $network"
fi

# 2. App
"${OCC[@]}" app:enable user_ldap

# 3. Configuration. Use the first one if it exists.
cfg="$("${OCC[@]}" ldap:show-config 2>/dev/null | grep -o -E '\| s[0-9]+ ' | head -1 | tr -d '| ' || true)"
if [ -z "$cfg" ]; then
  cfg="$("${OCC[@]}" ldap:create-empty-config | grep -o -E "s[0-9]+" | head -1)"
  echo "Created the LDAP configuration $cfg"
else
  echo "Use the LDAP configuration $cfg"
fi
# The commands go to a shell in the container on stdin. Arguments with parentheses break when they pass
# the docker wrapper of a Windows host (see "Problems with Docker in WSL" in dev/README.md).
commands=()
set_ldap() { commands+=("occ ldap:set-config $cfg $1 '$2' >/dev/null"); }
set_ldap ldapHost "$LDAP_CONTAINER"
set_ldap ldapPort 389
set_ldap ldapTLS 0
set_ldap ldapAgentName "$LDAP_BIND_DN"
set_ldap ldapAgentPassword "$LDAP_BIND_PASSWORD"
set_ldap ldapBase "$LDAP_BASE"
set_ldap ldapBaseUsers "ou=people,$LDAP_BASE"
set_ldap ldapBaseGroups "ou=groups,$LDAP_BASE"
set_ldap ldapUserFilter "(objectClass=inetOrgPerson)"
set_ldap ldapUserFilterObjectclass inetOrgPerson
set_ldap ldapLoginFilter "(&(objectClass=inetOrgPerson)(uid=%uid))"
set_ldap ldapLoginFilterUsername 1
set_ldap ldapLoginFilterEmail 0
set_ldap ldapGroupFilter "(objectClass=groupOfNames)"
set_ldap ldapGroupFilterObjectclass groupOfNames
set_ldap ldapGroupMemberAssocAttr member
set_ldap ldapGroupDisplayName cn
set_ldap ldapUserDisplayName cn
set_ldap ldapEmailAttribute mail
set_ldap ldapExpertUsernameAttr uid
set_ldap ldapExpertGroupnameAttr cn
set_ldap ldapAttributesForUserSearch "uid;cn;mail"
set_ldap ldapAttributesForGroupSearch cn
set_ldap ldapConfigurationActive 1
printf '%s\n' "${commands[@]}" | "${DC[@]}" exec -T owncloud sh -s

echo
"${OCC[@]}" ldap:test-config "$cfg"
echo
echo "LDAP users log in to ownCloud with their uid, for example user001, and the password Test1234!."
