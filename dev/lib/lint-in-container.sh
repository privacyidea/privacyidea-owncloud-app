#!/bin/sh
# Runs inside a php:*-cli container. Checks the syntax of every PHP file of the app.
cd /app || exit 1
status=0
for f in $(find . -name '*.php' | sort); do
  if ! out=$(php -l "$f" 2>&1); then
    echo "$out"
    status=1
  fi
done
php -v | head -1
[ "$status" -eq 0 ] && echo "syntax ok"
exit "$status"
