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

# Fail the deploy loudly if anything still needs a migration.
if as_app "$PHP_BIN" artisan migrate:status | grep -qi pending; then
    echo "✗ Deploy finished but migrations are STILL pending — check the output above." >&2
    exit 1
fi

echo "✓ Deploy complete — no pending migrations"
