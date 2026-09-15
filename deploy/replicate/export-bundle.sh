#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# wavadesk — export everything the new server needs that git does NOT carry.
#
# RUN THIS ON THE **CURRENT** PRODUCTION SERVER, as root.
#
#   bash deploy/replicate/export-bundle.sh
#
# Produces  /root/wavadesk-migration/wavadesk-bundle-<date>.tar.gz  containing:
#   • both .env files (wavadesk + the WhatsApp gateway)      ← secrets
#   • MariaDB dump   (whatsapp-saas)                         ← the app database
#   • Postgres dump  (whatsapp_api)                          ← the gateway database
#   • gateway instances/  (Baileys auth state)               ← keeps WhatsApp paired
#   • storage/app         (tenant uploads)
#   • a git bundle of the gateway repo INCLUDING its unpushed commit
#     and uncommitted local edits (see WHY below)
#   • the Apache / Supervisor / systemd unit files as they run today
#
# WHY the gateway needs a bundle instead of a clone:
#   /www/wwwroot/public/xapi-prod-v1.wavadesk.com has `origin` pointing at the
#   UPSTREAM repo (code-chat-br/whatsapp-api). It carries one commit that was
#   never pushed anywhere — the native_flow fix that made interactive buttons
#   and lists actually render — plus ~9 modified files on top. Cloning upstream
#   on the new box gives you a gateway that silently drops interactive messages.
#
# ⚠ THE OUTPUT CONTAINS PLAINTEXT SECRETS (DB passwords, API keys, APP_KEY).
#   Never commit it, never put it on a public host. scp it and delete it.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

APP_PATH="${APP_PATH:-/www/wwwroot/public/wavadesk.com}"
GW_PATH="${GW_PATH:-/www/wwwroot/public/xapi-prod-v1.wavadesk.com}"
OUT_DIR="${OUT_DIR:-/root/wavadesk-migration}"
STAMP="$(date +%Y%m%d-%H%M%S)"
WORK="$OUT_DIR/bundle-$STAMP"

[ "$(id -u)" -eq 0 ] || { echo "✗ run as root (needs pg_dump as postgres + supervisor confs)" >&2; exit 1; }
[ -d "$APP_PATH" ]   || { echo "✗ APP_PATH not found: $APP_PATH" >&2; exit 1; }
[ -d "$GW_PATH" ]    || { echo "✗ GW_PATH not found: $GW_PATH"  >&2; exit 1; }

mkdir -p "$WORK"/{env,db,gateway,storage,config}
chmod 700 "$OUT_DIR" "$WORK"

env_get() { grep -m1 "^$1=" "$2" | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }

echo "→ [1/7] .env files"
cp "$APP_PATH/.env" "$WORK/env/wavadesk.env"
cp "$GW_PATH/.env"  "$WORK/env/gateway.env"

echo "→ [2/7] MariaDB dump (app database)"
DB_NAME="$(env_get DB_DATABASE "$APP_PATH/.env")"
DB_USER="$(env_get DB_USERNAME "$APP_PATH/.env")"
DB_PASS="$(env_get DB_PASSWORD "$APP_PATH/.env")"
DB_HOST="$(env_get DB_HOST     "$APP_PATH/.env")"
# --single-transaction keeps the site up while dumping; no --lock-tables.
MYSQL_PWD="$DB_PASS" mysqldump \
    -u"$DB_USER" -h"${DB_HOST:-127.0.0.1}" \
    --single-transaction --quick --routines --triggers --events \
    --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -9 > "$WORK/db/wavadesk-mysql.sql.gz"
echo "$DB_NAME" > "$WORK/db/mysql-dbname.txt"

echo "→ [3/7] Postgres dump (gateway database)"
# The gateway keeps chats, contacts, messages AND Baileys session rows here.
PG_URL="$(env_get DATABASE_URL "$GW_PATH/.env")"
PG_DB="$(sed -E 's#.*/([^/?]+)(\?.*)?$#\1#' <<<"$PG_URL")"
sudo -u postgres pg_dump --no-owner --no-acl -Fc "$PG_DB" > "$WORK/db/gateway-postgres.dump"
echo "$PG_DB" > "$WORK/db/pg-dbname.txt"

echo "→ [4/7] gateway source (git bundle + working-tree diff)"
git -C "$GW_PATH" bundle create "$WORK/gateway/gateway-repo.bundle" --all
git -C "$GW_PATH" rev-parse HEAD > "$WORK/gateway/HEAD.txt"
git -C "$GW_PATH" branch --show-current > "$WORK/gateway/branch.txt"
# Uncommitted edits, captured as a patch so nothing silently disappears.
git -C "$GW_PATH" diff > "$WORK/gateway/working-tree.patch" || true
git -C "$GW_PATH" status --short > "$WORK/gateway/status.txt" || true

echo "→ [5/7] gateway instances/ (Baileys auth — skip this and every number re-scans a QR)"
tar -C "$GW_PATH" -czf "$WORK/gateway/instances.tar.gz" instances 2>/dev/null || echo "   (no instances/ dir)"

echo "→ [6/7] tenant uploads"
tar -C "$APP_PATH/storage" -czf "$WORK/storage/storage-app.tar.gz" app 2>/dev/null || true

echo "→ [7/7] live service config, as it actually runs today"
cp /etc/supervisor/conf.d/wavadesk.conf        "$WORK/config/" 2>/dev/null || true
cp /etc/supervisor/conf.d/reverb.conf          "$WORK/config/" 2>/dev/null || true
cp /www/server/panel/vhost/apache/wavadesk.com.conf                "$WORK/config/" 2>/dev/null || true
cp /www/server/panel/vhost/apache/xapi-prod-v1.wavadesk.com.conf   "$WORK/config/" 2>/dev/null || true
cp -r /www/server/panel/vhost/apache/proxy/wavadesk.com            "$WORK/config/proxy-wavadesk"  2>/dev/null || true
cp -r /www/server/panel/vhost/apache/proxy/xapi-prod-v1.wavadesk.com "$WORK/config/proxy-xapi"    2>/dev/null || true

{
    echo "# Source server fingerprint — taken $(date -Is)"
    echo "php:        $(php -v 2>/dev/null | head -1)"
    echo "node:       $(node -v 2>/dev/null)  npm: $(npm -v 2>/dev/null)"
    echo "mariadb:    $(mysql --version 2>/dev/null)"
    echo "postgres:   $(sudo -u postgres psql -tAc 'select version()' 2>/dev/null | head -1)"
    echo "apache:     $(apachectl -v 2>/dev/null | head -1)"
    echo "app commit: $(git -C "$APP_PATH" rev-parse HEAD)"
    echo "app branch: $(git -C "$APP_PATH" branch --show-current)"
    echo "gw  commit: $(git -C "$GW_PATH" rev-parse HEAD)"
    echo "php ext:    $(php -m 2>/dev/null | tr '\n' ' ')"
} > "$WORK/SOURCE-FINGERPRINT.txt"

TARBALL="$OUT_DIR/wavadesk-bundle-$STAMP.tar.gz"
tar -C "$OUT_DIR" -czf "$TARBALL" "bundle-$STAMP"
rm -rf "$WORK"
chmod 600 "$TARBALL"

echo
echo "✓ Bundle: $TARBALL  ($(du -h "$TARBALL" | cut -f1))"
echo
echo "  Next, from your laptop:"
echo "    scp root@THIS_SERVER:$TARBALL ."
echo "    scp wavadesk-bundle-$STAMP.tar.gz root@NEW_SERVER:/root/"
echo
echo "  ⚠ Contains plaintext secrets. Delete both copies once the new box is verified:"
echo "    rm $TARBALL"
