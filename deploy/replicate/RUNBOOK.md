# Wavadesk + WhatsApp gateway — replication runbook

Bring the whole stack up on a new production server, in the state it is in today.

**If you are Claude Code CLI on the new server: read this file top to bottom
before running anything, then work the steps in order.** Every step says how to
check it worked. Do not skip step 4 — it is where a replica quietly destroys the
original.

---

## What you are actually copying

Two separate applications that only work together:

| | Wavadesk | WhatsApp gateway |
|---|---|---|
| Path | `/www/wwwroot/public/wavadesk.com` | `/www/wwwroot/public/xapi-prod-v1.wavadesk.com` |
| Stack | PHP 8.3 / Laravel + Vite | Node 22 / TypeScript (Baileys) |
| Database | MariaDB `whatsapp-saas` | PostgreSQL `whatsapp_api` |
| Listens on | php-fpm socket + Reverb `:8080` | `:8084` |
| Public URL | `https://wavadesk.com` | `https://xapi-prod-v1.wavadesk.com` |
| Process mgr | Supervisor | pm2 on the source; Supervisor recommended |

They talk to each other **over the public internet, in both directions**:

```
 Wavadesk  ──── WHATSAPP_API_URL ──────▶  gateway   (send message, create instance, QR)
 Wavadesk  ◀─── WHATSAPP_WEBHOOK_BASE_URL ── gateway (inbound messages, status changes)
 Browser   ◀─── wss://<app-domain>/app/<key> ── Apache ─▶ Reverb :8080 (live inbox)
```

That mutual dependency is why a copy that "starts fine" can still be dead: if the
gateway cannot reach the app's webhook URL, inbound WhatsApp messages never
appear and nothing errors visibly.

---

## The one thing to understand before you start

**`git clone` alone cannot reproduce this stack.** Four things live outside git:

1. **The gateway's code.** Its `origin` points at the *upstream* project,
   `code-chat-br/whatsapp-api`. The running copy carries one commit that was
   never pushed anywhere — `9b9c1e3 feat: render native-flow interactive
   messages (buttons + lists)` — plus ~9 modified files on top of it. Clone
   upstream and you get a gateway that accepts interactive messages and silently
   fails to render them as tappable buttons. `export-bundle.sh` captures this as
   a git bundle + a patch.
2. **Both `.env` files.** `APP_KEY`, DB passwords, `WHATSAPP_API_KEY`,
   Reverb keys, Anthropic, PayPal, Mailtrap.
3. **Two databases**, in two different engines.
4. **`instances/`** in the gateway — the Baileys auth state, ~4 MB. Without it
   every connected WhatsApp number must re-scan a QR, which means every tenant
   is offline until someone with the phone does it.

So the flow is: **export on the old box → scp → clone repo on the new box →
import → rewrite domains → start.**

---

## Step 0 — Decide: cutover, or parallel copy?

This decision changes step 4, and getting it wrong logs your customers out of
WhatsApp. Pick one now.

**A. Cutover / migration** — the new server *replaces* the old one and keeps the
same domains. Nothing in `.env` changes; you repoint DNS at the end and stop the
old services. Simplest, and the WhatsApp sessions keep working.

**B. Parallel copy** — old server keeps running, new one gets *new* domains
(e.g. `app2.example.com` + `xapi2.example.com`). Everything in step 4 applies.

> ⚠ **Baileys allows one live socket per paired device.** In a parallel copy,
> both gateways hold the same credentials from `instances/`, and each reconnect
> kicks the other off — you get two half-working servers and customers seeing
> "disconnected". This takes down the **old, live** server too, not just the copy.
>
> **So for a parallel copy, run step 3 with `--parallel`.** That skips
> `instances/` entirely, and the new gateway comes up with no WhatsApp
> credentials — it cannot claim the pairing even by accident. Each number you
> want to test on the copy then scans a fresh QR from its instances page.
>
> The Baileys credentials live *only* in `instances/<name>/creds.json` on disk.
> The gateway's Postgres `Auth` table holds per-instance **API tokens**, not
> WhatsApp sessions, so restoring that database in parallel mode is harmless.

---

## Step 1 — Provision the new server

Ubuntu 24.x, root over SSH. The source box runs aaPanel, which is *not* required —
what matters is Apache + php-fpm 8.3 + the modules below.

```bash
apt update
apt install -y \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-intl php8.3-sqlite3 php8.3-opcache \
  mariadb-server postgresql \
  apache2 supervisor git curl unzip rsync

# Composer
curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/bin/composer && chmod +x /usr/bin/composer

# Node 22 — the source runs v22.12.0 / npm 10.9.0
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs

# Apache modules the stack depends on
a2enmod proxy proxy_http proxy_fcgi proxy_wstunnel rewrite ssl headers deflate
systemctl restart apache2

# The app user everything runs as
id www || useradd -r -m -d /home/www -s /bin/bash www
```

PHP extensions that must be present (`php -m`): `ctype curl dom fileinfo filter
intl mbstring openssl pcntl pdo_mysql posix sockets sodium tokenizer xml zip`.
`pcntl` + `posix` + `sockets` are what Reverb and `queue:work` need — a stock
php-fpm build that disables `pcntl` will start Reverb and then hang.

**Check:** `php -v` → 8.3.x · `node -v` → v22.x · `systemctl is-active mariadb
postgresql apache2 supervisor` → four × `active`.

---

## Step 2 — Get the code and the bundle onto the box

On the **old** server:

```bash
bash /www/wwwroot/public/wavadesk.com/deploy/replicate/export-bundle.sh
```

It prints the tarball path. Move it across (via your laptop; the two servers do
not need to see each other):

```bash
scp root@OLD:/root/wavadesk-migration/wavadesk-bundle-*.tar.gz .
scp wavadesk-bundle-*.tar.gz root@NEW:/root/
```

On the **new** server, clone the app repo — the gateway is *not* cloned, it
comes out of the bundle:

```bash
mkdir -p /www/wwwroot/public
git clone https://github.com/moutiasaad/whatsappSaas.git /www/wwwroot/public/wavadesk.com
cd /www/wwwroot/public/wavadesk.com
git checkout main
chown -R www:www /www/wwwroot/public/wavadesk.com
```

**Check:** `git log --oneline -1` matches `app commit:` in the bundle's
`SOURCE-FINGERPRINT.txt`. If it does not, the old server was running unpushed
work — go back and push it before continuing.

---

## Step 3 — Restore

**Cutover (option A)** — the old server will be stopped:

```bash
bash /www/wwwroot/public/wavadesk.com/deploy/replicate/import-bundle.sh \
     /root/wavadesk-bundle-<stamp>.tar.gz
```

**Parallel copy (option B)** — the old server stays live. Use this one:

```bash
bash /www/wwwroot/public/wavadesk.com/deploy/replicate/import-bundle.sh \
     /root/wavadesk-bundle-<stamp>.tar.gz --parallel
```

This restores both `.env` files, creates and loads both databases and their
users, reconstructs the gateway from the bundle (commit + working-tree patch +
untracked files such as `.htaccess` / `.user.ini`), restores tenant uploads,
then runs `composer install`, `npm ci && npm run build`, `prisma generate` and
the gateway's `npm run build`. It deliberately starts **no services**.

`instances/` (the Baileys auth) is restored **only without `--parallel`**. See
step 0 for why that matters.

Re-running it is safe; it refuses to overwrite a database that already has
tables unless you add `--force-db`.

**Check:** `php artisan migrate:status | grep -ci pending` → `0`, and
`php artisan tinker --execute="echo \App\Models\Tenant::count();"` prints the
tenant count you expect. Run artisan **as www**, never as root — a root-owned
`storage/logs/laravel.log` makes php-fpm and every queue worker fail on write,
silently:

```bash
sudo -u www php artisan migrate:status
```

---

## Step 4 — Domains and `.env` (the step that matters)

**Option A, cutover, same domains:** nothing to change in `.env`. Skip to step 5.

**Option B, parallel copy, new domains:** edit `/www/wwwroot/public/wavadesk.com/.env`:

| Key | Set to |
|---|---|
| `APP_URL` | `https://<new-app-domain>` |
| `WHATSAPP_WEBHOOK_BASE_URL` | `https://<new-app-domain>` — where the gateway POSTs inbound messages |
| `WHATSAPP_API_URL` | `https://<new-gateway-domain>` — must be public, **not** `127.0.0.1` |
| `REVERB_HOST` | `<new-app-domain>` (leave `REVERB_PORT=443`, `REVERB_SCHEME=https`) |
| `SESSION_DOMAIN` | leave blank unless you serve several subdomains |
| `GITHUB_WEBHOOK_SECRET` | new value, or blank to disable auto-deploy here |
| `PAYPAL_MODE` | still `sandbox` on the source — set `live` only deliberately |

And in the gateway's `.env`: `WEBHOOK_GLOBAL_URL` if it is set to a wavadesk URL.

### 4b — Repoint the instances in the database (parallel copy only)

**`.env` is not enough.** Each row of `whatsapp_instances` carries its own
`gateway_url` and `gateway_api_key`, and the restored database still points them
at the **old, live** gateway. Left alone, the copy drives production: it sends
real WhatsApp messages from real customer numbers, and the moment it refreshes
an instance it re-registers that instance's webhook to the copy's URL — which
takes inbound traffic *away* from the live server. Nothing warns you.

Do this before starting any service on the new box:

```sql
-- point every instance at the NEW gateway, and stop them re-registering webhooks
UPDATE whatsapp_instances
   SET gateway_url     = 'https://<new-gateway-domain>',
       webhook_enabled = 0,
       webhook_url     = NULL,
       status          = 'disconnected',
       phone_number    = NULL;
```

Rows whose `gateway_url` is the hosted SaaS (`https://api.whatstshl.online`)
belong to someone else's account entirely — repoint those too, or delete them
on the copy. Then re-pair only the test numbers you actually want, by QR.

> The same argument applies to anything else in the restored database that
> reaches the outside world on its own: mail, PayPal, and the deploy webhook.
> A trial copy should have `MAIL_MAILER=log` and `PAYPAL_MODE=sandbox`.

> `WHATSAPP_WEBHOOK_BASE_URL` pointing at localhost is the single most common
> cause of "gateway says connected, but no message ever reaches /conversations".
> The gateway calls that URL from its own process — it must resolve publicly.

Then, because `REVERB_*` and `VITE_*` are compiled into the JS bundle:

```bash
cd /www/wwwroot/public/wavadesk.com
sudo -u www npm run build          # REQUIRED after changing any VITE_/REVERB_ value
sudo -u www php artisan config:clear && sudo -u www php artisan config:cache
```

Point DNS for both hostnames at the new IP. If Cloudflare is in front, the app
hostname must be **proxied (orange cloud) with WebSockets enabled** for `wss://`
to survive; the gateway hostname works either way.

---

## Step 5 — Web server

Create two vhosts. Templates are in `deploy/replicate/templates/`; the bundle's
`config/` also has the exact files the old server runs, for reference.

**App vhost** — `DocumentRoot` is the `public/` subdirectory, PHP handled by
php-fpm, and it must `IncludeOptional` the Reverb proxy snippet in **both** the
`:80` and `:443` blocks:

```apache
<FilesMatch \.php$>
    SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
</FilesMatch>
<Directory "/www/wwwroot/public/wavadesk.com/public">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>
IncludeOptional /etc/apache2/wavadesk-proxy/*.conf
```

> The source box uses the aaPanel socket `/tmp/php-cgi-83.sock`. On a plain
> Ubuntu install it is `/run/php/php8.3-fpm.sock`. Check with
> `ls /run/php/` before pasting.

Copy `templates/apache-proxy-reverb.conf` into that include directory.

**Gateway vhost** — no document root, body is `templates/apache-proxy-gateway.conf`.

Issue TLS for both hostnames (certbot, acme.sh, or the panel), then
`apachectl configtest && systemctl reload apache2`.

**Check:** `curl -I https://<app-domain>` → 200/302, and
`curl -s https://<gateway-domain>/` returns the gateway's JSON banner, not a 502.
A 502 here means the node process is not up yet — that is step 6.

---

## Step 6 — Services

```bash
APP=/www/wwwroot/public/wavadesk.com
GW=/www/wwwroot/public/xapi-prod-v1.wavadesk.com
T=$APP/deploy/replicate/templates

sed "s#__APP_PATH__#$APP#g" $T/supervisor-wavadesk.conf > /etc/supervisor/conf.d/wavadesk.conf
sed "s#__APP_PATH__#$APP#g" $T/supervisor-reverb.conf   > /etc/supervisor/conf.d/reverb.conf
sed "s#__GW_PATH__#$GW#g"   $T/supervisor-gateway.conf  > /etc/supervisor/conf.d/wavadesk-gateway.conf

supervisorctl reread && supervisorctl update && supervisorctl status
```

Expected: `wavadesk-whatsapp_00/_01`, `wavadesk-default_00`,
`wavadesk-scheduler`, `wavadesk-reverb_00`, `wavadesk-gateway` — all `RUNNING`.

Two things the source server does that you should **not** copy:

- It also has a systemd `wavadesk-queue.service` consuming the *same* `whatsapp`
  queue as the supervisor workers. Two managers, overlapping jobs. Use
  supervisor only here; do not create that unit.
- It has **no scheduler at all** for wavadesk. The template above adds
  `schedule:work`. Watch `/var/log/supervisor/wavadesk-scheduler.log` on first
  run — scheduled work that has never executed in production may surprise you.

The gateway on the source runs under pm2 (`npm run start:prod`). The supervisor
program above runs `node dist/src/main.js` directly instead, because
`start.sh` does `rm -rf dist && npm run build` on every start — fine by hand,
but under an autorestarting supervisor a crash loop becomes a rebuild loop.
Run one or the other, never both: they both bind `:8084`.

Optional auto-deploy (same pipeline as the old box — see `deploy/README.md`):

```bash
cd $APP
git config core.hooksPath deploy/githooks
install -m 644 deploy/systemd/wavadesk-deploy.* /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now wavadesk-deploy.timer
```

---

## Step 7 — Verify, in this order

Each check tells you which layer is broken, so run them in sequence and stop at
the first failure.

```bash
# 1. gateway process alive and answering locally
curl -s http://127.0.0.1:8084/ | head -c 200

# 2. gateway reachable on its public name (Apache + TLS + DNS)
curl -s https://<gateway-domain>/ | head -c 200

# 3. app can authenticate to the gateway (WHATSAPP_API_KEY correct)
curl -s -H "apikey: $(grep ^WHATSAPP_API_KEY .env | cut -d= -f2-)" \
     https://<gateway-domain>/instance/fetchInstances | head -c 400

# 4. Reverb up and proxied — expect an HTTP 400/426 from the ws endpoint, NOT 502
curl -sI https://<app-domain>/app/$(grep ^REVERB_APP_KEY .env | cut -d= -f2-) | head -3

# 5. queue actually drains
sudo -u www php artisan queue:work --once --queue=whatsapp

# 6. app logs clean
tail -n 50 storage/logs/laravel.log
```

Then in a browser:

- Log in, open `/conversations` — history from the old server should be there.
- Open `/tenant-admin/instances` — instances listed, status `connected`, and a
  **phone number shown**. `Connectée` + `Aucun numéro` means the status sync did
  not map the gateway's `ownerJid`/`number`/`me.jid`; hit refresh-status once.
- Send a WhatsApp message *from a different phone* to a connected number. It
  must appear in the inbox within a second or two. If the gateway is connected
  but nothing arrives, it is `WHATSAPP_WEBHOOK_BASE_URL` — re-check step 4 and
  reconnect the instance so the webhook is re-registered.
- Watch the inbox update without refreshing → Reverb and `wss://` are good.

---

## Step 8 — Clean up

```bash
rm /root/wavadesk-bundle-*.tar.gz          # on the new server
rm /root/wavadesk-migration/*.tar.gz       # on the old server
rm -f /www/wwwroot/public/wavadesk.com/.env.bak.*
```

The bundle holds every secret in the stack in plaintext. Delete both copies and
the one on your laptop.

---

## Known traps, all of them hit at least once on the source server

| Symptom | Cause | Fix |
|---|---|---|
| Queue workers log nothing, jobs never run | `storage/logs/laravel.log` owned by `root` after an artisan run as root | `chown -R www:www storage bootstrap/cache`; always `sudo -u www php artisan …` |
| Gateway `connected`, zero inbound messages | `WHATSAPP_WEBHOOK_BASE_URL` not publicly resolvable | set a real public URL, then reconnect the instance so the webhook re-registers |
| `502` on the gateway domain | node on `:8084` is down | `supervisorctl restart wavadesk-gateway`, then read its log |
| Inbox does not live-update; console shows a failed `wss://` | `proxy_wstunnel` not enabled, the proxy snippet missing from the `:443` vhost, or Cloudflare WebSockets off | enable the module, include the snippet in **both** vhosts, turn on WebSockets |
| Buttons/lists arrive as plain text | gateway rebuilt from upstream instead of the bundle — `9b9c1e3` missing | restore the gateway from `gateway-repo.bundle`, not `git clone` upstream |
| Sender unknown / conversation not matched | inbound payload carries `keyLid` (`@lid`) rather than `keyRemoteJid` | already handled in app code; if it regresses, check the id-extraction path |
| A new feature 500s right after deploy | migrations skipped | `sudo -u www php artisan migrate:status \| grep -i pending` |
| `.env` edited but nothing changed | config cache stale | `config:clear && config:cache`; for `VITE_*`/`REVERB_*` also `npm run build` |
| Both servers' WhatsApp keep dropping | two gateways sharing one `instances/` | stop one — see step 0; re-import with `--parallel` |
| Copy sends messages from real customer numbers, or production stops receiving inbound | `whatsapp_instances.gateway_url` still points at the live gateway | step 4b — repoint the rows, don't rely on `.env` |
