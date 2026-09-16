#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# wavadesk — deploy trigger watcher (run by deploy-webhook.timer)
#
# public/deploy-webhook.php is internet-facing and so is not allowed to execute
# anything. It only writes a trigger file after verifying GitHub's signature;
# this script is the privileged half that acts on it.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# The checkout this script lives in, not a hardcoded path: the split put a
# second server at a different path, and the old default made this exit 0
# every 20s looking for a trigger file under the *other* box's directory —
# no error, no log, no deploy. APP_PATH in the environment still wins.
APP_PATH="${APP_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
TRIGGER="$APP_PATH/storage/framework/deploy.trigger"

[ -f "$TRIGGER" ] || exit 0

# Consume the trigger first: a push landing mid-deploy re-arms it instead of
# being swallowed by the deploy already in flight.
rm -f "$TRIGGER"

exec bash "$APP_PATH/deploy/webhook-deploy.sh"
