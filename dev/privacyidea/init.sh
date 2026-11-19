#!/bin/sh
# Runs in the container "privacyidea-init" instead of the entrypoint of the image.
# The test stack has no secret files, so create the encryption key if it does not exist.
# The key is 96 random bytes (three keys of 32 bytes). Never change it after tokens exist.
set -eu
KEY=/etc/privacyidea/enckey
if [ ! -f "$KEY" ]; then
  head -c 96 /dev/urandom > "$KEY"
  chmod 600 "$KEY"
fi
exec /opt/privacyidea/entrypoint.sh
