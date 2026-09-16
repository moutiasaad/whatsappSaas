# Deploy pipeline

A push to `main` deploys itself. Nothing needs to be run on the server by hand.

```
git push origin main
        │
        ▼
GitHub webhook  ──POST──▶  public/deploy-webhook.php     (as www, no shell access)
                             verifies HMAC-SHA256, writes storage/framework/deploy.trigger
                                          │
                        systemd timer every 20s
                                          ▼
                           deploy/deploy-watcher.sh       (as root)
                             consumes the trigger, then runs
                                          ▼
                           deploy/webhook-deploy.sh
                             git fetch + merge --ff-only origin/main
                                          │  (post-merge hook)
                                          ▼
                           deploy/after-pull.sh
                             composer install, migrate, rebuild caches,
                             queue:restart, npm build — every step dropped to www
```

## Why it is split in two

`public/deploy-webhook.php` is reachable from the internet, so it does not run
shell commands and does not touch git. It only verifies GitHub's signature and
writes a trigger file. Everything privileged happens in the timer-driven half,
which never parses the request body.

## Operating it

| Task | Command |
|---|---|
| Watch a deploy | `tail -f storage/logs/deploy.log` |
| Timer state | `systemctl list-timers wavadesk-deploy.timer` |
| Force a deploy now | `systemctl start wavadesk-deploy.service` |
| Deploy by hand | `bash deploy/webhook-deploy.sh` |
| Pause auto-deploy | `systemctl stop wavadesk-deploy.timer` |

The deploy pulls as root, since that is where the git credentials live, so
`webhook-deploy.sh` chowns everything the merge touched back to `www` before it
finishes. A root-owned file under `storage/` would otherwise make php-fpm and
the queue workers fail on write.

Only `refs/heads/main` deploys. Pushes to other branches return 202 and are
ignored. A push arriving mid-deploy re-arms the trigger instead of being lost,
and `flock` keeps two deploys from interleaving.

## Two hosts, one pipeline

Since the split (`deploy/split-hosting/README.md`) there are two servers, and
both run exactly this pipeline — same repo, same branch, same scripts. Nothing
about deployment is role-aware:

| | wavadesk.com | app.wavadesk.com |
|---|---|---|
| `WAVADESK_ROLE` | `marketing` | `core` (the default) |
| Webhook URL | `https://wavadesk.com/deploy-webhook.php` | `https://app.wavadesk.com/deploy-webhook.php` |
| `GITHUB_WEBHOOK_SECRET` | its own, in its own `.env` | its own, in its own `.env` |
| Serves | landing, features, legal, login/register | panels, payment, the APIs |

One `git push origin main` fans out to both. Each host pulls the whole
codebase, including the half it does not serve, and
`App\Http\Middleware\SplitHostingRedirect` sends a request for the other
half to the host that owns it. So a landing-page change and a dashboard change
travel identically: push once, both boxes update, each serves its own half.

Two consequences worth keeping in mind:

- **Both boxes need Node.** Assets are built per host from that host's own
  checkout; `/public/build` is gitignored and never travels with a pull. A
  marketing box without npm fails its deploy loudly rather than serving a
  landing page with yesterday's CSS.
- **Each host's secret is its own.** `GITHUB_WEBHOOK_SECRET` lives in the
  gitignored `.env`, so the two webhooks can hold different secrets and a pull
  can never overwrite one with the other. Two GitHub webhooks, one per host.

Retiring a bespoke deployer (an `adnanh/webhook` daemon, a cron pull) in favour
of this one is the point: one mechanism, described in one file, reviewed with
the code it deploys.

## Setup on a new box

```bash
node -v && npm -v               # the deploy builds assets; no npm, no deploy
touch .wavadesk-deploy          # marks this checkout as a deploy target
git config core.hooksPath deploy/githooks

# The systemd unit is the ONE file that has to name an absolute path.
# Everything else resolves from the checkout, so stamp this box's path in as
# you install it rather than editing the tracked file (which would leave the
# tree dirty and `merge --ff-only` would then refuse to deploy).
sed "s|/www/wwwroot/public/wavadesk.com|$(pwd)|" \
    deploy/systemd/wavadesk-deploy.service > /etc/systemd/system/wavadesk-deploy.service
install -m 644 deploy/systemd/wavadesk-deploy.timer /etc/systemd/system/

systemctl daemon-reload && systemctl enable --now wavadesk-deploy.timer
```

Then set `GITHUB_WEBHOOK_SECRET` in `.env` and point a GitHub webhook at
`/deploy-webhook.php` with the same secret and content type `application/json`.

Verify end to end rather than trusting the timer: push something, then watch
`journalctl -u wavadesk-deploy.service -f`. A webhook that never fires and a
watcher that finds no trigger look identical from the outside — both are
silence — so confirm GitHub's Recent Deliveries shows a 2xx *and* that the
service actually ran.
