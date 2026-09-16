#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# wavadesk — pull the deploy branch after a verified GitHub push
#
# The post-merge hook (deploy/githooks/post-merge) runs after-pull.sh, so this
# script deliberately does not call it: doing both would deploy twice.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# Derived from this script's own location so the same checkout works at any
# path on any host. APP_PATH in the environment still wins.
APP_PATH="${APP_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
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

# We pull as root (that is where the git credentials live), so everything the
# merge writes lands root-owned. A root-owned file under storage/ makes php-fpm
# and the queue workers fail on write, so hand it all back to www: the objects
# git wrote, and every path the merge touched.
chown -R "$APP_USER:$APP_USER" "$APP_PATH/.git"
chown "$APP_USER:$APP_USER" "$LOG" "$LOCK" 2>/dev/null || true

if [ "$before" != "$after" ]; then
    git diff --name-only "$before" "$after" | while read -r path; do
        chown "$APP_USER:$APP_USER" "$path" 2>/dev/null || true
        dir="$(dirname "$path")"
        [ "$dir" != "." ] && chown "$APP_USER:$APP_USER" "$dir" 2>/dev/null || true
    done
fi

if [ "$before" = "$after" ]; then
    echo "= already at ${after:0:8}, nothing to deploy"
else
    echo "✓ deployed ${before:0:8} -> ${after:0:8}"
fi
