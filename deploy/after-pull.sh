#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# wavadesk — run after every git pull in production
# Installs deps, runs migrations, rebuilds caches, restarts queue workers.
#
# Invoked automatically by the post-merge git hook (deploy/githooks/post-merge).
# Can also be run by hand:  bash deploy/after-pull.sh
#
# Safe to run as root: every composer/artisan command is dropped to APP_USER so
# it never leaves root-owned files behind (a root-owned storage/logs/laravel.log
# makes the php-fpm/queue workers fail with "Permission denied" on every write).
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

APP_PATH="${APP_PATH:-/www/wwwroot/public/wavadesk.com}"
APP_USER="${APP_USER:-www}"
PHP_BIN="${PHP_BIN:-$(command -v php || echo /usr/bin/php)}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || echo /usr/bin/composer)}"

cd "$APP_PATH"

# Run a command as APP_USER when we're root, otherwise run it as-is.
# -H sets HOME so composer/npm find their cache dir instead of writing to /root.
as_app() {
    if [ "$(id -u)" -eq 0 ]; then
        sudo -u "$APP_USER" -H "$@"
    else
        "$@"
    fi
}

if [ "$(id -u)" -eq 0 ]; then
    echo "→ Running as root; dropping to '$APP_USER' for composer/artisan"
fi

echo "→ Installing composer dependencies..."
as_app "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction -q

# Config cache must be cleared BEFORE migrating: a stale cached config can point
# artisan at the wrong database and make the migration silently no-op the schema.
echo "→ Clearing caches..."
as_app "$PHP_BIN" artisan config:clear
as_app "$PHP_BIN" artisan route:clear
as_app "$PHP_BIN" artisan view:clear

echo "→ Pending migrations:"
as_app "$PHP_BIN" artisan migrate:status | grep -i pending || echo "    (none)"

echo "→ Running migrations..."
# Not -q: migration output is the single most useful line in a deploy log, and
# a schema change that never ran is the most common cause of a post-deploy 500.
as_app "$PHP_BIN" artisan migrate --force

echo "→ Warming caches..."
as_app "$PHP_BIN" artisan config:cache
as_app "$PHP_BIN" artisan route:cache
as_app "$PHP_BIN" artisan view:cache

echo "→ Restarting queue workers gracefully..."
as_app "$PHP_BIN" artisan queue:restart
# Workers finish their current job, exit, then Supervisor restarts them.

echo "→ Building frontend assets..."
# /public/build is gitignored, so a pull that changes resources/js or
# resources/css lands the PHP and leaves yesterday's compiled bundle live.
# Nothing errors — the page half-updates — which is why this runs on every
# deploy instead of trying to guess when it matters. Last, deliberately: a
# broken build should leave a working database with stale assets, never a
# migrated-but-unmigrated app.
if [ "${WAVADESK_SKIP_ASSETS:-0}" = "1" ]; then
    echo "    WAVADESK_SKIP_ASSETS=1 — skipped"
elif [ ! -f package.json ]; then
    echo "    no package.json — skipped"
elif ! command -v npm >/dev/null 2>&1; then
    # Loud rather than quietly skipped: a host with no npm cannot serve current
    # assets, and learning that from a user's screenshot is worse than here.
    echo "✗ npm not found — cannot rebuild assets on this host." >&2
    echo "  Install Node, or set WAVADESK_SKIP_ASSETS=1 if this box serves no pages." >&2
    exit 1
else
    as_app npm ci --no-audit --no-fund
    as_app npm run build
    # The build already runs as APP_USER, but a stray root-owned file under
    # public/build 403s every asset request through php-fpm.
    #
    # Guarded on the directory existing: under `set -e` a chown of a missing
    # path aborts the deploy *after* a successful build, which would turn a
    # vite config that writes somewhere else into a failed deploy with no
    # useful error.
    if [ "$(id -u)" -eq 0 ] && [ -d public/build ]; then
        chown -R "$APP_USER:$APP_USER" public/build
    fi
fi

# Fail the deploy loudly if anything still needs a migration.
if as_app "$PHP_BIN" artisan migrate:status | grep -qi pending; then
    echo "✗ Deploy finished but migrations are STILL pending — check the output above." >&2
    exit 1
fi

echo "✓ Deploy complete — no pending migrations"
