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
                             queue:restart — every step dropped to www
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

## Setup on a new box

```bash
git config core.hooksPath deploy/githooks
install -m 644 deploy/systemd/wavadesk-deploy.* /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now wavadesk-deploy.timer
```

Then set `GITHUB_WEBHOOK_SECRET` in `.env` and point a GitHub webhook at
`/deploy-webhook.php` with the same secret and content type `application/json`.
