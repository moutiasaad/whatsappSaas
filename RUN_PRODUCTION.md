# TshlBot — Production Runbook

Everything you need to bring `tshlbot.online` up on a fresh production server,
and every command you'll reach for once it's running. Written to be pastable
into Claude Code CLI over SSH.

Stack: PHP 8.2 + Laravel 12, MySQL 8, Node 20, Nginx, Supervisor, Laravel Reverb
(WebSockets), Anthropic SDK, Stripe.

WhatsApp is **hidden** in this build. To re-enable it, grep for
`TSHLBOT-HIDE-WHATSAPP` in `routes/` + `resources/views/layouts/admin.blade.php`
and uncomment the wrapped blocks.

---

## 0. Prereqs on the server (once)

```bash
# System packages
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd \
    php8.2-intl php8.2-redis \
    nginx mysql-server supervisor unzip git curl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 20 (for `npm run build`)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## 1. First-time deploy

```bash
# Pick a path. This runbook and deploy/*.sh default to:
APP_PATH=/www/wwwroot/tshlbot.online
sudo mkdir -p "$APP_PATH"
sudo chown -R $USER:$USER "$APP_PATH"

# Clone
cd /www/wwwroot
git clone <your-repo-url> tshlbot.online
cd tshlbot.online

# Env
cp .env.production .env
php artisan key:generate --force        # generates a fresh APP_KEY
# Then open .env and fill in the blanks:
#   DB_PASSWORD, ANTHROPIC_API_KEY, STRIPE_*, MAIL_*, REVERB_APP_KEY, REVERB_APP_SECRET

# Dependencies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# Database
mysql -u root -p <<SQL
CREATE DATABASE tshlbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tshlbot'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON tshlbot.* TO 'tshlbot'@'localhost';
FLUSH PRIVILEGES;
SQL

php artisan migrate --force
php artisan db:seed --force        # if you have seeders you want in prod

# Storage symlink + writable dirs
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Cache warm
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 2. Nginx site

`/etc/nginx/sites-available/tshlbot.online`:

```nginx
server {
    listen 80;
    server_name tshlbot.online www.tshlbot.online;
    return 301 https://tshlbot.online$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tshlbot.online;

    root /www/wwwroot/tshlbot.online/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/tshlbot.online/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tshlbot.online/privkey.pem;

    client_max_body_size 25M;

    # Laravel Reverb WebSockets — proxy /app/* and /apps/* to Reverb :8080
    location /app/ {
        proxy_pass             http://127.0.0.1:8080;
        proxy_http_version     1.1;
        proxy_set_header       Upgrade $http_upgrade;
        proxy_set_header       Connection "upgrade";
        proxy_set_header       Host $host;
        proxy_set_header       X-Forwarded-For $remote_addr;
        proxy_read_timeout     60m;
        proxy_send_timeout     60m;
    }
    location /apps/ {
        proxy_pass             http://127.0.0.1:8080;
        proxy_http_version     1.1;
        proxy_set_header       Upgrade $http_upgrade;
        proxy_set_header       Connection "upgrade";
        proxy_set_header       Host $host;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/tshlbot.online /etc/nginx/sites-enabled/
sudo certbot --nginx -d tshlbot.online -d www.tshlbot.online
sudo nginx -t && sudo systemctl reload nginx
```

---

## 3. Workers, scheduler, Reverb (Supervisor)

The project ships two helpers under `deploy/`. Adjust the paths for tshlbot
before running:

```bash
# Edit deploy/setup-workers.sh — change APP_PATH default from
#   /www/wwwroot/public/wavadesk.com  →  /www/wwwroot/tshlbot.online
# Also add a [program:reverb] block so the WebSocket server is supervised.

sudo APP_PATH=/www/wwwroot/tshlbot.online \
     APP_USER=www-data \
     bash deploy/setup-workers.sh
```

Add a Reverb supervisor block manually if `setup-workers.sh` doesn't include
one yet. `/etc/supervisor/conf.d/tshlbot-reverb.conf`:

```ini
[program:tshlbot-reverb]
command=/usr/bin/php /www/wwwroot/tshlbot.online/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/www/wwwroot/tshlbot.online
user=www-data
autostart=true
autorestart=true
stopwaitsecs=10
redirect_stderr=true
stdout_logfile=/var/log/supervisor/tshlbot-reverb.log
stdout_logfile_maxbytes=10MB
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Expected active processes:
- `tshlbot-default` — queue worker for `default` queue
- `tshlbot-reverb` — WebSocket server on :8080 (proxied by Nginx over TLS)
- `cron` — runs `php artisan schedule:run` every minute

---

## 4. Redeploying after `git push`

**Auto-deploy is unreliable** (memory: `project_prod_autodeploy_broken`). Every
push needs a manual pull:

```bash
cd /www/wwwroot/tshlbot.online
git pull
bash deploy/after-pull.sh          # composer install, migrate, cache, queue:restart
```

`deploy/after-pull.sh` handles all the standard steps. If you edit it, keep
`APP_PATH` in sync.

---

## 5. Day-to-day commands

```bash
# Where you'll spend most of your time
cd /www/wwwroot/tshlbot.online

# Tail all Laravel logs
tail -f storage/logs/laravel.log

# Live-tail structured events (Pail — dev only, avoid in prod if noisy)
php artisan pail

# Clear caches (after config/env changes)
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Queue
php artisan queue:restart                    # graceful restart of all workers
sudo supervisorctl restart tshlbot:*         # hard restart
sudo supervisorctl status
tail -f /var/log/supervisor/tshlbot-default.log

# Reverb
sudo supervisorctl restart tshlbot-reverb
tail -f /var/log/supervisor/tshlbot-reverb.log
# Sanity check from another shell:
curl -I https://tshlbot.online/app/         # should NOT be 404

# Scheduler — verify the cron ran in the last minute
grep "Running scheduled command" storage/logs/laravel.log | tail

# DB console
mysql -u tshlbot -p tshlbot

# One-off tasks (tinker)
php artisan tinker
```

---

## 6. Health checks after a deploy

```bash
# 1. HTTP up
curl -I https://tshlbot.online          # expect 200 / 302 to /login

# 2. Routes loaded (should NOT list any WhatsApp/instances routes)
php artisan route:list | grep -Ei 'instance|whatsapp'
# expected output: empty

# 3. Queue picking up jobs
php artisan queue:work --once           # runs one job, then exits

# 4. Reverb reachable
curl -sSf http://127.0.0.1:8080/app/ping || echo "REVERB DOWN"

# 5. DB reachable
php artisan db:show
```

---

## 7. Re-enabling WhatsApp later

Everything is present in the code — just uncomment. In the project root:

```bash
# Show every block that was hidden
grep -rn "TSHLBOT-HIDE-WHATSAPP" routes/ resources/views/layouts/admin.blade.php
```

For each block: remove the surrounding `/* … */` (PHP) or `{{-- … --}}` (Blade)
wrappers and the `TSHLBOT-HIDE-WHATSAPP:begin`/`:end` marker lines. Then:

```bash
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart tshlbot:*
```

Fill in `WHATSAPP_API_KEY` in `.env` and reconnect any instances via the
tenant-admin `/instances` page.

---

## 8. Troubleshooting

**500 on every page.** Almost always cached routes/views from before the last
deploy. `php artisan optimize:clear`.

**"No application encryption key has been specified."** `.env` is missing
`APP_KEY`. `php artisan key:generate --force`, then re-cache config.

**Jobs sitting in `jobs` table, never running.** Supervisor stopped. Check
`sudo supervisorctl status`. If nothing is listed, run
`sudo bash deploy/setup-workers.sh` again.

**Live-chat not updating without refresh.** Reverb down or Nginx WebSocket
proxy misconfigured. Verify:
- `sudo supervisorctl status tshlbot-reverb` says `RUNNING`
- Browser DevTools → Network → WS → the `/app/…` connection is `101 Switching`
- `.env` has `REVERB_HOST=tshlbot.online`, `REVERB_PORT=443`,
  `REVERB_SCHEME=https` **and you ran `npm run build` after any change** —
  the VITE_REVERB_* values are baked into the JS bundle.

**Broadcast events not firing but no 500 in controller.** The app uses
`ShouldBroadcastNow` and wraps `event()` calls in `rescue()`
(feedback: `should_broadcast_now_rescue`). Broadcast failures are logged, not
thrown. Check `storage/logs/laravel.log` for `Failed to broadcast`.

**Stripe webhook returning 400.** `STRIPE_WEBHOOK_SECRET` in `.env` must match
the endpoint secret shown in the Stripe dashboard, and the webhook URL must be
exactly `https://tshlbot.online/payment/webhook`.

**Sessions logging out at random.** `SESSION_DOMAIN` in `.env` should be
`.tshlbot.online` (leading dot) if you serve both `tshlbot.online` and
`www.tshlbot.online`. Also verify `SESSION_SECURE_COOKIE=true` on HTTPS.

---

## 9. File map

```
/www/wwwroot/tshlbot.online/
├── .env                      ← production secrets (NOT in git)
├── .env.production           ← template committed alongside .env.example
├── deploy/
│   ├── after-pull.sh         ← run after every `git pull`
│   ├── setup-workers.sh      ← one-time Supervisor setup
│   └── supervisor/*.conf     ← generated configs
├── storage/logs/laravel.log  ← app log
├── public/                   ← Nginx document root
└── routes/{web,api}.php      ← WhatsApp entry points wrapped in
                                TSHLBOT-HIDE-WHATSAPP blocks
```

Log locations:
- App: `storage/logs/laravel.log`
- Nginx: `/var/log/nginx/{access,error}.log`
- PHP-FPM: `/var/log/php8.2-fpm.log`
- Supervisor children: `/var/log/supervisor/tshlbot-*.log`
