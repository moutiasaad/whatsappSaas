#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# wavadesk — pull the deploy branch after a verified GitHub push
#
# The post-merge hook (deploy/githooks/post-merge) runs after-pull.sh, so this
# script deliberately does not call it: doing both would deploy twice.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

APP_PATH="${APP_PATH:-/www/wwwroot/public/wavadesk.com}"
APP_USER="${APP_USER:-www}"
BRANCH="${DEPLOY_BRANCH:-main}"
LOG="$APP_PATH/storage/logs/deploy.log"
LOCK="$APP_PATH/storage/framework/deploy.lock"

cd "$APP_PATH"
exec >>"$LOG" 2>&1

echo ""
echo "── $(date -Is) deploy start (branch=$BRANCH user=$(id -un)) ──"

# Serialize: two pushes in quick succession queue instead of interleaving.
exec 9>"$LOCK"
if ! flock -w 600 9; then
    echo "✗ timed out waiting for the deploy lock"
    exit 1
fi

before="$(git rev-parse HEAD)"
git fetch origin "$BRANCH"
git merge --ff-only "origin/$BRANCH"
after="$(git rev-parse HEAD)"

# git as root leaves root-owned objects behind, which break the next pull run
# as www. Hand .git back every time rather than relying on nobody using root.
chown -R "$APP_USER:$APP_USER" "$APP_PATH/.git"

if [ "$before" = "$after" ]; then
    echo "= already at ${after:0:8}, nothing to deploy"
else
    echo "✓ deployed ${before:0:8} -> ${after:0:8}"
fi
