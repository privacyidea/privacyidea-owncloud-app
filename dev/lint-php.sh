#!/usr/bin/env bash
# Syntax check of the app on the oldest and the newest PHP that ownCloud supports.
#   ./dev/lint-php.sh
set -euo pipefail
cd "$(dirname "$0")/.."
for version in ${PHP_VERSIONS:-7.4 8.3}; do
  docker run --rm -v ./twofactor_privacyidea:/app:ro -v ./dev/lib/lint-in-container.sh:/lint.sh:ro \
    "php:${version}-cli" sh /lint.sh
done
