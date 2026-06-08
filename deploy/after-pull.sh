#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# wavadesk — run after every git pull in production
# Clears caches and gracefully restarts queue workers
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

APP_PATH="${APP_PATH:-/www/wwwroot/public/wavadesk.com}"
PHP_BIN="${PHP_BIN:-$(command -v php || echo /usr/bin/php)}"

cd "$APP_PATH"

echo "→ Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction -q

echo "→ Running migrations..."
$PHP_BIN artisan migrate --force -q

echo "→ Clearing and warming caches..."
$PHP_BIN artisan config:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "→ Restarting queue workers gracefully..."
$PHP_BIN artisan queue:restart
# Workers will finish current jobs then restart automatically via Supervisor

echo "✓ Deploy complete"
