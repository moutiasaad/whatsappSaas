# Split hosting: `wavadesk.com` + `app.wavadesk.com`

Two hosts, **one codebase**, one env var deciding which half each plays.

| | `wavadesk.com` | `app.wavadesk.com` |
|---|---|---|
| Role | `WAVADESK_ROLE=marketing` | `WAVADESK_ROLE=core` (default) |
| Owns | landing, pricing, register/login forms, subscriptions | MySQL, WhatsApp webhooks, live chat, every panel |
| Identity | **none** — proxies to the core app | the `users` table |
| Serves `/api/v1/auth/*` | no | yes |
| Redeems `/auth/sso` | no | yes |

Both hosts already deploy this repo from `main`, so there is nothing to copy
between them: a `git push` updates both and the role decides the behaviour.
That is also why there is no second copy of the handoff signer to keep in
lockstep — `app/Services/Auth/SsoHandoffCode.php` mints on one host and
verifies on the other, from the same file.

`core` is the default on purpose. A box that never sets `WAVADESK_ROLE` — a
dev machine, a replica built by `deploy/replicate/`, `app.wavadesk.com` itself
— behaves exactly as the single-host monolith always has.

---

## The flow

```
Browser            wavadesk.com                    app.wavadesk.com
   │  (1) POST /register or /login        │
   ├────────────────►│                     │
   │                 │  (2) POST /api/v1/auth/… over HTTPS
   │                 │      X-Wavadesk-Caller: <shared secret>
   │                 ├────────────────────►│
   │                 │                     │  create / verify user,
   │                 │                     │  issue Sanctum PAT
   │                 │◄────────────────────┤
   │                 │  { token, user, tenant, next_step, plan_hint }
   │                 │                     │
   │                 │  (3) stash PAT in the encrypted session
   │                 │      mint an HMAC handoff code (60s, single use)
   │                 │                     │
   │  (4) 302 → https://app.wavadesk.com/auth/sso?code=…&plan=…
   │◄────────────────┤                     │
   │                                       │
   │  (5) GET /auth/sso?code=…             │
   ├──────────────────────────────────────►│  verify HMAC + exp + nonce,
   │                                       │  Auth::login(), session regenerate
   │  (6) 302 → /register/plan or the panel │
   │◄──────────────────────────────────────┤
```

### Why it is shaped this way

- **The Sanctum token never enters a URL.** It stays in `wavadesk.com`'s
  server-side session so later marketing pages (billing, account) can call the
  core app on the user's behalf. What crosses the domain boundary is a
  purpose-built code that grants one session and nothing else.
- **A leaked handoff URL is worthless** after 60 seconds or after one
  redemption, whichever comes first. The core app caches spent nonces.
- **CSRF stays intact.** Each host sets its own session cookie on its own
  domain. There is no shared cookie, no `SESSION_DOMAIN=.wavadesk.com`, and
  therefore no cookie for a subdomain to be tricked into replaying.
- **`/api/v1/auth/*` is server-to-server.** It is called by `wavadesk.com`'s
  php-fpm, never by a browser, so it is *not* in `config/cors.php` and is
  guarded by the shared secret in an `X-Wavadesk-Caller` header instead. A
  wrong or missing header answers **404**, so the endpoint cannot be probed.
  Without that guard `/api/v1/auth/login` is an open credential oracle.
- **`/auth/sso` is core-only.** Both hosts hold the same signing key; a
  marketing host that also served the redemption route could redeem its own
  codes into a local session — the exact thing the split exists to prevent.

---

## Cutover

### 1. Generate one shared secret

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### 2. Set it on **both** hosts, identically

`app.wavadesk.com/.env` — the core app:

```dotenv
WAVADESK_ROLE=core
WAVADESK_MARKETING_ORIGIN=https://wavadesk.com
WAVADESK_SHARED_SECRET=<the 64 hex chars from step 1>
```

`wavadesk.com/.env` — the marketing app:

```dotenv
WAVADESK_ROLE=marketing
WAVADESK_CORE_URL=https://app.wavadesk.com
WAVADESK_SHARED_SECRET=<the same 64 hex chars>
WAVADESK_API_TIMEOUT=5
```

### 3. Rebuild the config cache on both

Required, not optional. `deploy/after-pull.sh` runs `config:cache`, and once
the config is cached Laravel **stops loading `.env` at all** — a value read
through `env()` at runtime comes back `null`. Everything here is read through
`config('wavadesk.*')` for that reason, but the cache still has to be rebuilt
for a new `.env` value to be seen.

```bash
sudo -u www php artisan config:clear && sudo -u www php artisan config:cache
sudo -u www php artisan route:clear && sudo -u www php artisan route:cache
```

`route:cache` matters too: `routes/web.php` and `routes/api.php` branch on the
role as they are loaded, so a stale route cache would serve the other host's
routes.

### 4. Smoke test, in this order

From the marketing host, confirm the core app answers **only** with the secret:

```bash
# No header → 404, as if the route did not exist.
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  https://app.wavadesk.com/api/v1/auth/login

# With the secret + bad credentials → 422 and a field error.
curl -s -X POST https://app.wavadesk.com/api/v1/auth/login \
  -H "X-Wavadesk-Caller: $WAVADESK_SHARED_SECRET" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"nobody@example.com","password":"wrong"}'
```

Then in a browser: register on `wavadesk.com`, and confirm you land on
`app.wavadesk.com` already signed in, on the plan picker, with the plan you
clicked pre-selected. Check that the URL you were redirected through contains
a `code=` and **no token**.

### 5. Rolling back

Set `WAVADESK_ROLE=core` on `wavadesk.com` and rebuild the caches. It is back
to being a self-contained monolith; nothing else has to be undone.

---

## What is *not* done yet

These are deliberate omissions, not oversights — each needs a decision before
it is worth building.

- **The marketing host still has its own database.** Only identity was moved.
  The landing and pricing pages still read `plans` locally, so the two plan
  tables can drift. The signup path already tolerates that: `plan_id` is sent
  as a hint, the core app does not validate it against its own table, and the
  plan picker re-validates before granting anything. A genuinely database-less
  marketing host needs a `GET /api/v1/plans` endpoint first.
- **The marketing header does not know it is signed in.** After the handoff the
  session on `wavadesk.com` holds the PAT under
  `Wavadesk::SESSION_TOKEN`, but no view reads it, so coming back to
  `wavadesk.com` shows the signed-out header. A view composer sharing
  `session(Wavadesk::SESSION_USER)` would fix it.
- **Subscriptions still run on whichever host serves them.** Moving billing to
  the marketing host needs `/api/v1/billing/*` on the core app.
- **Password reset is core-only.** `wavadesk.com` has no mailer path for it
  yet; it needs `/api/v1/auth/password/*`.
- **Tokens do not expire.** Set `SANCTUM_EXPIRATION` once the flow is live.

---

## Where the code is

| Path | Side | Purpose |
|---|---|---|
| `config/wavadesk.php` | both | the role and its peer settings |
| `app/Support/Wavadesk.php` | both | `isMarketing()` / `isCore()` and the shared constants |
| `app/Services/Auth/SsoHandoffCode.php` | both | mints on marketing, verifies on core |
| `app/Services/WavadeskApi.php` | marketing | Http client for `/api/v1/auth/*` |
| `app/Http/Controllers/Auth/RegisterController.php` | both | `storeViaCoreApi()` is the marketing branch |
| `app/Http/Controllers/Auth/LoginController.php` | both | `attemptLoginViaCoreApi()` is the marketing branch |
| `app/Http/Controllers/Api/V1/Auth/AuthApiController.php` | core | register / login / me / logout |
| `app/Http/Controllers/Auth/SsoHandoffController.php` | core | redeems the code |
| `app/Http/Middleware/EnsureMarketingCaller.php` | core | the shared-secret guard |
| `tests/Feature/SplitHostingMarketingTest.php` | — | the marketing half |
| `tests/Feature/SplitHostingCoreTest.php` | — | the core half |
