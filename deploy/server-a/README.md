# Server A (`wavadesk.com`) — marketing + auth proxy

This directory is the code drop that turns a fresh Laravel install (or a fork
of the current monolith) into **Server A**: the public marketing surface at
`wavadesk.com` that runs the landing page, register form, login form, and
subscription pages, but **has no database of its own for users**.

All user identity lives on **Server B (`app.wavadesk.com`)**. Server A proxies
register / login into Server B's `/api/v1/auth/*` endpoints and then hands the
authenticated browser off to `app.wavadesk.com` via a short-lived signed URL.

---

## Flow (register or login)

```
Browser        Server A                    Server B
   │  (1) POST /register or /login          │
   ├───────────────►│                         │
   │                │  (2) POST /api/v1/auth/…│
   │                ├────────────────────────►│
   │                │                         │  create/verify user,
   │                │                         │  issue Sanctum PAT
   │                │◄────────────────────────┤
   │                │  { token, user, tenant }│
   │                │                         │
   │                │  (3) mint HMAC handoff code
   │                │      store PAT in encrypted session
   │                │                         │
   │  (4) 302 → https://app.wavadesk.com/auth/sso?code=…
   ├◄───────────────┤                         │
   │                                          │
   │  (5) GET /auth/sso?code=…                │
   ├─────────────────────────────────────────►│
   │                                          │  verify HMAC + exp + nonce,
   │                                          │  Auth::login(), rotate session
   │  (6) 302 → /dashboard  (or /register/plan)
   │◄─────────────────────────────────────────┤
```

Notes:

- **The Sanctum token never appears in a URL.** Server A keeps it in an
  encrypted session cookie so subsequent marketing-side calls (billing UI,
  account settings) can hit Server B on the user's behalf. What crosses the
  domain is a purpose-built signed code — single-use, 60-second TTL.
- **CSRF stays intact.** Both apps set their own session cookies on their own
  domains. There is no shared cookie to synchronise.
- **A leaked handoff URL is worthless after one hop or after 60s.** Server B
  caches redeemed nonces so the same code cannot log anyone in twice.

---

## Files in this drop

| Path | Purpose |
|------|---------|
| `app/Services/Auth/SsoHandoffCode.php` | HMAC mint/verify. **Byte-identical copy** of Server B's file. CI/CD keeps them in lockstep. |
| `app/Services/WavadeskApi.php` | Http wrapper around Server B's `/api/v1/auth/*`. |
| `app/Http/Controllers/Auth/RegisterController.php` | Proxies the register form. |
| `app/Http/Controllers/Auth/LoginController.php` | Proxies the login form. |
| `app/Providers/AppServiceProvider.php` | Binds `WavadeskApi` + `SsoHandoffCode` with their env-driven args. |
| `config/services.php.snippet` | `services.wavadesk.app_url` config block. |
| `routes/web.php.snippet` | Auth route registrations. |
| `.env.example` | New env vars Server A needs. |

---

## Install

Assuming Server A is a fresh Laravel 11+ project:

```bash
composer create-project laravel/laravel wavadesk-marketing
cd wavadesk-marketing
```

Then:

1. Copy `app/` and `deploy/server-a/app/**` into place, overwriting the stub
   controllers and provider Laravel ships with.
2. Merge `config/services.php.snippet` into `config/services.php`.
3. Merge `routes/web.php.snippet` into `routes/web.php`.
4. Copy `.env.example` values into `.env` and fill them in (see below).
5. Copy the existing marketing Blade views (`auth/register.blade.php`,
   `auth/login.blade.php`, landing / legal / features) from the monolith.
   The forms don't need to change — they still post the same field names.

Composer packages Server A needs (all already in a stock Laravel install):

```
guzzlehttp/guzzle
```

Server A does **not** need `laravel/sanctum` — it's a client, not a token issuer.

---

## Env vars

### Server A (`wavadesk.com`)

```
WAVADESK_APP_URL=https://app.wavadesk.com
WAVADESK_SHARED_SECRET=<64 hex chars, matches Server B>
WAVADESK_API_TIMEOUT=5
```

Generate the shared secret once and paste it into **both** servers' env:

```
php -r "echo bin2hex(random_bytes(32));"
```

### Server B (`app.wavadesk.com`)

```
WAVADESK_MARKETING_ORIGIN=https://wavadesk.com
WAVADESK_SHARED_SECRET=<same value as Server A>
```

Server B's existing `.env.example` in the monolith root now carries both keys.

---

## API contract (Server B)

### `POST /api/v1/auth/register`

Body:

```json
{
  "company_name": "Acme",
  "email": "owner@acme.example",
  "password": "atLeast8chars",
  "plan_id": 3
}
```

Success (`201`):

```json
{
  "token": "1|abc…",
  "user":   { "id": 42, "name": "Acme", "email": "owner@acme.example", "role": "admin", "home_route": "admin.dashboard" },
  "tenant": { "id": 12, "name": "Acme", "slug": "acme", "plan_id": null,
              "subscription_status": "trial", "trial_ends_at": null, "is_active": true },
  "next_step": "plan"
}
```

Failure (`422`):

```json
{ "message": "The email has already been taken.", "errors": { "email": ["…"] } }
```

Rate limit: 5/min per IP.

### `POST /api/v1/auth/login`

Body:

```json
{ "email": "owner@acme.example", "password": "atLeast8chars" }
```

Success (`200`): same shape as register minus the `next_step` — that comes
back as `"dashboard"` if the tenant has already picked a plan, `"plan"` if
not.

Failure: `422` (bad credentials), `403` (super-admin using marketing door /
disabled account).

Rate limit: 10/min per IP.

### `GET /api/v1/auth/me` (Sanctum bearer)

Returns `{ user, tenant }` for the token holder. Handy if Server A wants to
validate a stashed session without a re-login.

### `POST /api/v1/auth/logout` (Sanctum bearer)

Revokes the current token.

---

## Testing end-to-end (local)

1. On Server B (`app.wavadesk.local:8000`):

   ```
   php artisan serve --port=8000
   ```

   Env:

   ```
   WAVADESK_MARKETING_ORIGIN=http://wavadesk.local:8001
   WAVADESK_SHARED_SECRET=<64 hex chars>
   ```

2. On Server A (`wavadesk.local:8001`):

   ```
   php artisan serve --port=8001
   ```

   Env:

   ```
   WAVADESK_APP_URL=http://wavadesk.local:8000
   WAVADESK_SHARED_SECRET=<same 64 hex chars>
   ```

3. Add both hosts to `hosts`:

   ```
   127.0.0.1 wavadesk.local
   127.0.0.1 app.wavadesk.local
   ```

4. In a browser: `http://wavadesk.local:8001/register` → fill the form → you
   should land on `http://wavadesk.local:8000/register/plan` already signed in.

Curl smoke test (Server B):

```
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'Origin: http://wavadesk.local:8001' \
  -d '{"company_name":"Test","email":"t@example.com","password":"pw12345678"}'
```

Expect a `201` with a `token` field.

---

## Follow-up scope (not in this drop)

- **Billing / subscription pages** still live on the monolith; move them to
  Server A by adding a `/api/v1/billing/*` surface on Server B that accepts
  the same Sanctum PAT.
- **Password reset** flow will need a matching pair of API endpoints. It's
  currently the built-in Laravel flow on the monolith.
- **Session token rotation** — the PAT never expires by default. Consider
  setting `SANCTUM_EXPIRATION` on Server B once the split is live.
