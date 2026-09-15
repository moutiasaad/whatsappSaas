#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# wavadesk — restore the bundle onto a FRESH server, as root.
#
#   bash deploy/replicate/import-bundle.sh /root/wavadesk-bundle-<stamp>.tar.gz [flags]
#
# Flags:
#   --force-db         overwrite a database that already has tables
#   --skip-instances   do NOT restore the gateway's Baileys auth (instances/)
#   --parallel         alias for --skip-instances, for a side-by-side copy
#
# ⚠ PARALLEL COPY: if the OLD server is still live, you MUST pass --parallel.
#   WhatsApp allows one live socket per pairing. Two gateways restoring the same
#   instances/ fight over every connection and log each other out in a loop —
#   which takes your PRODUCTION numbers down, not just the copy's. With
#   --parallel the new gateway starts unpaired and each number scans a fresh QR.
#
# Assumes RUNBOOK.md step 1 (provisioning) is already done: php 8.3, node 22,
# mariadb, postgres, apache, supervisor, composer, and the `www` user exist.
#
# Idempotent: safe to re-run. It will not overwrite a database that already has
# tables unless you pass --force-db.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

APP_PATH="${APP_PATH:-/www/wwwroot/public/wavadesk.com}"
GW_PATH="${GW_PATH:-/www/wwwroot/public/xapi-prod-v1.wavadesk.com}"
APP_USER="${APP_USER:-www}"
FORCE_DB=0
SKIP_INSTANCES=0
BUNDLE=""

usage() { echo "usage: $0 /path/to/wavadesk-bundle-*.tar.gz [--force-db] [--parallel|--skip-instances]" >&2; exit 1; }

while [ $# -gt 0 ]; do
    case "$1" in
        --force-db)                   FORCE_DB=1 ;;
        --skip-instances|--parallel)  SKIP_INSTANCES=1 ;;
        -h|--help)                    usage ;;
        -*)                           echo "✗ unknown flag: $1" >&2; usage ;;
        *)                            [ -n "$BUNDLE" ] && { echo "✗ more than one bundle given" >&2; usage; }; BUNDLE="$1" ;;
    esac
    shift
done

[ "$(id -u)" -eq 0 ] || { echo "✗ run as root" >&2; exit 1; }
[ -n "$BUNDLE" ] && [ -f "$BUNDLE" ] || usage
id "$APP_USER" >/dev/null 2>&1 || { echo "✗ user '$APP_USER' does not exist — do RUNBOOK step 1 first" >&2; exit 1; }

WORK="$(mktemp -d /root/wavadesk-restore.XXXXXX)"; chmod 700 "$WORK"
trap 'rm -rf "$WORK"' EXIT
tar -xzf "$BUNDLE" -C "$WORK"
B="$(find "$WORK" -maxdepth 1 -type d -name 'bundle-*' | head -1)"
[ -d "$B" ] || { echo "✗ bundle layout not recognised" >&2; exit 1; }

env_get() { grep -m1 "^$1=" "$2" | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
as_app()  { sudo -u "$APP_USER" -H "$@"; }

echo "── source fingerprint ─────────────────────────────"; cat "$B/SOURCE-FINGERPRINT.txt"; echo "───────────────────────────────────────────────────"; echo

# ── 0. app source ────────────────────────────────────────────────────────────
# Production runs a branch that was never pushed, so `git clone` from GitHub
# lands you behind HEAD. Fetch the bundled history over whatever was cloned and
# check out the exact commit the source box was running.
echo "→ [0/8] app source (sync to the source commit)"
if [ -s "$B/app/app-repo.bundle" ] && [ -d "$APP_PATH/.git" ]; then
    APP_HEAD="$(cat "$B/app/HEAD.txt")"
    APP_BRANCH="$(cat "$B/app/branch.txt")"
    if [ -n "$(git -C "$APP_PATH" status --porcelain --untracked-files=no)" ]; then
        echo "   ⚠ working tree has tracked modifications — NOT touching it."
        echo "     Resolve by hand, then re-run. Source HEAD was $APP_HEAD ($APP_BRANCH)."
    else
        git -C "$APP_PATH" fetch "$B/app/app-repo.bundle" \
            "+refs/heads/*:refs/remotes/bundle/*" --tags 2>/dev/null || true
        if git -C "$APP_PATH" cat-file -e "$APP_HEAD^{commit}" 2>/dev/null; then
            git -C "$APP_PATH" checkout -B "$APP_BRANCH" "$APP_HEAD"
            echo "   checked out $APP_BRANCH @ $APP_HEAD"
            if [ -s "$B/app/working-tree.patch" ]; then
                git -C "$APP_PATH" apply "$B/app/working-tree.patch" \
                    && echo "   working-tree edits applied"
            fi
            chown -R "$APP_USER:$APP_USER" "$APP_PATH/.git"
        else
            echo "   ✗ commit $APP_HEAD missing after fetch — check the bundle" >&2
        fi
    fi
elif [ ! -d "$APP_PATH/.git" ]; then
    echo "   ✗ $APP_PATH is not a git checkout — clone it first (see README)" >&2; exit 1
else
    echo "   (bundle predates app-repo capture — skipping; verify the commit by hand)"
fi

# ── 1. app .env ──────────────────────────────────────────────────────────────
echo "→ [1/8] app .env"
if [ -f "$APP_PATH/.env" ]; then
    cp "$APP_PATH/.env" "$APP_PATH/.env.bak.$(date +%s)"
    echo "   existing .env backed up"
fi
install -o "$APP_USER" -g "$APP_USER" -m 640 "$B/env/wavadesk.env" "$APP_PATH/.env"

# ── 2. MariaDB ───────────────────────────────────────────────────────────────
echo "→ [2/8] MariaDB restore"
DB_NAME="$(env_get DB_DATABASE "$APP_PATH/.env")"
DB_USER="$(env_get DB_USERNAME "$APP_PATH/.env")"
DB_PASS="$(env_get DB_PASSWORD "$APP_PATH/.env")"
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1'; FLUSH PRIVILEGES;"
EXISTING="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")"
if [ "$EXISTING" -gt 0 ] && [ "$FORCE_DB" -eq 0 ]; then
    echo "   ⚠ '$DB_NAME' already has $EXISTING tables — skipping. Re-run with --force-db to overwrite."
else
    gunzip -c "$B/db/wavadesk-mysql.sql.gz" | mysql --default-character-set=utf8mb4 "$DB_NAME"
    echo "   restored"
fi

# ── 3. Postgres (gateway) ────────────────────────────────────────────────────
echo "→ [3/8] Postgres restore"
PG_DB="$(cat "$B/db/pg-dbname.txt")"
GW_PG_URL="$(env_get DATABASE_URL "$B/env/gateway.env")"
PG_USER="$(sed -E 's#^[a-z]+://([^:]+):.*#\1#' <<<"$GW_PG_URL")"
PG_PASS="$(sed -E 's#^[a-z]+://[^:]+:([^@]+)@.*#\1#' <<<"$GW_PG_URL")"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$PG_USER'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE ROLE \"$PG_USER\" LOGIN PASSWORD '$PG_PASS';"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$PG_DB'" | grep -q 1 \
  || sudo -u postgres createdb -O "$PG_USER" "$PG_DB"
PG_TABLES="$(sudo -u postgres psql -tAd "$PG_DB" -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public';")"
if [ "$PG_TABLES" -gt 0 ] && [ "$FORCE_DB" -eq 0 ]; then
    echo "   ⚠ '$PG_DB' already has $PG_TABLES tables — skipping. Re-run with --force-db to overwrite."
else
    sudo -u postgres pg_restore --no-owner --no-acl --clean --if-exists -d "$PG_DB" "$B/db/gateway-postgres.dump" || true
    sudo -u postgres psql -d "$PG_DB" -c "GRANT ALL ON SCHEMA public TO \"$PG_USER\"; GRANT ALL ON ALL TABLES IN SCHEMA public TO \"$PG_USER\"; GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO \"$PG_USER\";"
    echo "   restored"
fi

# ── 4. gateway source ────────────────────────────────────────────────────────
echo "→ [4/8] gateway source"
if [ ! -d "$GW_PATH/.git" ]; then
    mkdir -p "$(dirname "$GW_PATH")"
    # Clone from the BUNDLE, not from upstream — upstream lacks the native_flow commit.
    git clone "$B/gateway/gateway-repo.bundle" "$GW_PATH"
    git -C "$GW_PATH" checkout "$(cat "$B/gateway/branch.txt")" 2>/dev/null || \
    git -C "$GW_PATH" checkout "$(cat "$B/gateway/HEAD.txt")"
    # Restore whatever origin the source actually used; fall back to upstream
    # only if the bundle predates origin-url.txt.
    if [ -s "$B/gateway/origin-url.txt" ]; then
        GW_ORIGIN="$(cat "$B/gateway/origin-url.txt")"
        # The source uses a per-repo ssh Host alias (github-whatsappboot) defined
        # only in ITS ~/.ssh/config. That alias does not resolve here, so rewrite
        # it to the canonical host; set up a key for this box separately.
        case "$GW_ORIGIN" in
            *github-whatsappboot:*)
                GW_ORIGIN="${GW_ORIGIN/github-whatsappboot:/github.com:}"
                echo "   origin alias rewritten to canonical host: $GW_ORIGIN"
                echo "   ⚠ this box needs its own deploy key/PAT before it can fetch or push"
                ;;
        esac
        git -C "$GW_PATH" remote set-url origin "$GW_ORIGIN"
    else
        git -C "$GW_PATH" remote set-url origin https://github.com/code-chat-br/whatsapp-api.git
    fi
    if [ -s "$B/gateway/working-tree.patch" ]; then
        git -C "$GW_PATH" apply "$B/gateway/working-tree.patch" && echo "   working-tree edits applied"
    fi
    chown -R "$APP_USER:$APP_USER" "$GW_PATH"
else
    echo "   already present — left alone"
fi
install -o "$APP_USER" -g "$APP_USER" -m 640 "$B/env/gateway.env" "$GW_PATH/.env"

echo "→ [5/8] gateway instances/ (Baileys auth)"
if [ "$SKIP_INSTANCES" -eq 1 ]; then
    echo "   SKIPPED (--parallel) — this gateway starts unpaired."
    echo "   Every number must scan a fresh QR here. The old server keeps its sessions."
elif [ -f "$B/gateway/instances.tar.gz" ]; then
    tar -xzf "$B/gateway/instances.tar.gz" -C "$GW_PATH"
    chown -R "$APP_USER:$APP_USER" "$GW_PATH/instances"
    echo "   restored — paired numbers should reconnect without a new QR"
    echo "   ⚠ CUTOVER ONLY. If the old server is still running, stop its gateway NOW"
    echo "     or the two will log each other out. On the source box the gateway is"
    echo "     NOT under a process manager - it is a bare detached 'bash start.sh'"
    echo "     chain orphaned to init, so there is no pm2/supervisorctl stop for it:"
    echo "       ssh root@OLD \"pkill -f 'node ./dist/src/main.js'; pkill -f 'bash start.sh'\""
    echo "     Then confirm nothing still listens:  ssh root@OLD \"ss -lntp | grep 8084\""
fi

if [ -f "$B/gateway/untracked.tar.gz" ]; then
    tar -xzf "$B/gateway/untracked.tar.gz" -C "$GW_PATH"
    chown -R "$APP_USER:$APP_USER" "$GW_PATH"
    echo "   untracked gateway files restored (.htaccess, .user.ini, .well-known/)"
fi

echo "→ [6/8] tenant uploads"
[ -f "$B/storage/storage-app.tar.gz" ] && tar -xzf "$B/storage/storage-app.tar.gz" -C "$APP_PATH/storage"

# ── 7. app build ─────────────────────────────────────────────────────────────
echo "→ [7/8] composer + npm build"
cd "$APP_PATH"
as_app composer install --no-dev --optimize-autoloader --no-interaction
as_app npm ci
as_app npm run build
as_app php artisan storage:link || true
chown -R "$APP_USER:$APP_USER" storage bootstrap/cache public/build
chmod -R 775 storage bootstrap/cache
# A root-owned storage/logs/laravel.log makes php-fpm AND every queue worker
# fail on write, and the failure is silent until you tail the log.
find storage -user root -exec chown "$APP_USER:$APP_USER" {} + 2>/dev/null || true

echo "→ [8/8] gateway deps + build"
cd "$GW_PATH"
as_app npm install
as_app npx prisma generate
as_app npm run build

echo
echo "✓ Restore done. Nothing is listening yet — that is deliberate."
echo "  Continue at RUNBOOK.md step 4 (domains + .env rewrite) before starting services."
if [ "$SKIP_INSTANCES" -eq 1 ]; then
    echo "  Parallel mode: instances/ was NOT restored, so starting this gateway is safe"
    echo "  for the old server. Re-pair each number by QR from the instances page."
else
    echo "  Read step 4 first: starting the gateway while the OLD server still runs will"
    echo "  fight it for the same WhatsApp sessions and log both of them out."
fi
