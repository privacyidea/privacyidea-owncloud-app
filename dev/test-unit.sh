#!/usr/bin/env bash
# Unit tests (PHPUnit 9.6) on the oldest and the newest PHP that ownCloud supports.
#   ./dev/test-unit.sh
# The first run installs PHPUnit into ./vendor with a Composer container.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f vendor/autoload.php ]; then
  docker run --rm -v ./:/app -w /app composer:2 composer install --no-interaction --no-progress
fi
for version in ${PHP_VERSIONS:-7.4 8.3}; do
  docker run --rm -v ./:/app:ro -w /app "php:${version}-cli" php vendor/bin/phpunit --colors=never --do-not-cache-result
done
