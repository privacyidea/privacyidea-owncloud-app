#!/usr/bin/env bash
# Run the end-to-end tests.
#   ./dev/test-e2e.sh                  # against the default stack
#   ./dev/test-e2e.sh dev/env.oc10     # against the second stack (see dev/README.md)
# More arguments go to Playwright, for example:  ./dev/test-e2e.sh "" -g "alice"
set -euo pipefail
cd "$(dirname "$0")/.."
envfile="${1:-}"
[ "$#" -gt 0 ] && shift
if [ -n "$envfile" ]; then
  set -a; . "$envfile"; set +a
fi
cd tests/e2e
npx playwright test "$@"
