#!/usr/bin/env bash
# StudentOrbit — start the dev server with local env (SMTP secrets) loaded.
# Usage:  ./start.sh        (or:  bash start.sh)
set -euo pipefail
cd "$(dirname "$0")"

if [ -f .env.local ]; then
  # shellcheck disable=SC1091
  source .env.local
  echo "Loaded .env.local (SMTP user: ${SO_SMTP_USER:-<unset>})"
else
  echo "WARNING: .env.local not found — password-reset email will be disabled."
fi

echo "Starting StudentOrbit on http://localhost:8000  (Ctrl+C to stop)"
exec php -S localhost:8000 -t public
