# Reservation Bot — Session Handoff / Continuation Notes

_Last updated: 2026-06-10_

Read this first to continue work. The full root-cause analysis lives in
[`RESERVATION_BOT_INTERACTIVE_FIX.md`](./RESERVATION_BOT_INTERACTIVE_FIX.md).

---

## Current state (what works now)

- **Auto-reply works.** Sending the trigger (`book` / `حجز`) starts the reservation flow.
- **Interactive messages render as TAPPABLE** (verified live on both a real `@s.whatsapp.net`
  number and an `@lid` recipient):
  - Dates → tappable **list**
  - Morning/afternoon → tappable **buttons**
  - Time slots → tappable **list**
  - Each falls back to numbered text automatically if a gateway send fails.
- A tap is decoded back to the numeric choice, so the flow logic is unchanged.
- The booking-completion FK crash (stale `conversation_id`) is fixed, and any step error now
  clears the session + replies instead of wedging it.

---

## Key facts / IDs

| Thing | Value |
|---|---|
| App dir | `/www/wwwroot/public/wavadesk.com` |
| App repo | `github.com/moutiasaad/whatsappSaas` (branch `main`) |
| Gateway dir | `/www/wwwroot/public/xapi-prod-v1.wavadesk.com` |
| Gateway repo remote | `github.com/code-chat-br/whatsapp-api` (UPSTREAM — not ours, can't push) |
| Gateway runtime | pm2 app **"CodeChat Api"**, port **8084**, run as **root** |
| Gateway DB | PostgreSQL `whatsapp_api` @ 127.0.0.1:5432 |
| Reservation tenant | **tenant_id = 4** (plan "Growth", `reservations_enabled = true`) |
| Reservation instance | currently **id 16**, gateway id `wa-4-16` (re-created across re-scans: 13→14→15→16) |
| Business WhatsApp number | `21627541269` |
| Real test number (can receive interactive) | `21628067392` |
| `@lid` test device | `186827017302085@lid` |
| Trigger keywords | `حجز`, `book`, `booking`, `réservation` |

> ⚠️ The reservation instance ID changes every time it is reconnected via the app's Connect flow.
> Always look it up: `WhatsAppInstance::withoutGlobalScopes()->where('tenant_id',4)->latest('id')->first()`.

---

## Commits made

**wavadesk repo (pushed to `main`):**
- `98bfb4e` — buttons attempt + `@lid`/dedup/`keyLid`/broadcast fixes
- `7af4e2d` — (interim) revert period step to text
- `bf1cd8d` — recognize iStoreBox button/list tap replies (inbound `extractText` paths)
- `0b31c4c` — enable tappable lists/buttons on all steps + `conversation_id` crash-proofing + doc

**gateway repo (committed LOCALLY only — NOT pushed, remote is upstream):**
- `9b9c1e3` — render native-flow interactive messages: `additionalNodes` biz>interactive>native_flow,
  `header` field, `listMessage()` rewritten to `single_select`.

---

## The decisive fix (why interactive now renders)

The old gateway built the interactive payload but relayed it **without** the
`biz → interactive → native_flow` node, so WhatsApp ignored the buttons and showed only body text
(and dropped it entirely for `@lid`). Adding `additionalNodes` to `relayMessage` for interactive
messages fixed it. The SaaS `api.whatstshl.online` (same project, newer build) already did this —
that's why it worked for Sultankoo but not here. It was a **gateway encoding bug, not a WhatsApp
platform limit.**

Files: `xapi-prod-v1.wavadesk.com/src/whatsapp/services/whatsapp.service.ts`
(`sendMessageWithTyping` relay options, `buttonsMessage`, `listMessage`).

---

## Operating procedures

### Deploy app (Laravel) code changes
```bash
sudo -u www php artisan optimize:clear
sudo supervisorctl restart wavadesk:*    # REQUIRED — workers cache PHP classes in memory
```

### Deploy gateway (TS) changes — ⚠️ costs a QR re-scan
A gateway rebuild restarts the process, and **this gateway loses its WhatsApp link on every
restart** (does not auto-reconnect from saved creds). Recovery:
```bash
cd /www/wwwroot/public/xapi-prod-v1.wavadesk.com
npx tsc --noEmit                          # verify it compiles first
sudo -u root npx pm2 restart "CodeChat Api"
# wait for gateway up, then:
#   DELETE /instance/logout/{gatewayId}
#   sudo -u root npx pm2 restart "CodeChat Api"
#   GET /instance/connect/{gatewayId}     -> returns a fresh QR (base64)
```
Then re-scan from the business phone: **WhatsApp → Linked Devices → Link a device**.
(The app re-creates the instance under a new ID after each scan.)

### Diagnose "no reply"
1. Gateway up? `ss -ltn | grep 8084`; `GET /instance/fetchInstance/{id}` should be 200.
2. Instance connected? `GET /instance/connectionState/{id}` should be `{"state":"open"}`
   (the stored `connectionStatus` can lie — trust the live state).
3. Messages arriving? Gateway DB:
   `psql ... -c 'SELECT ... FROM "Message" WHERE "keyFromMe"=false ORDER BY id DESC LIMIT 5;'`
4. Bot ran / errored? `grep ReservationBot storage/logs/laravel.log`.
5. Wedged session? Clear it: `Cache::forget("reservation_bot:4:<phone-or-lid>")`.

---

## TODO — next session

1. **Translation (in progress / requested).** Make the reservation bot multilingual:
   - `reservation_settings` has the per-tenant message columns but **no `locale` column** — add one
     (migration) defaulting to `ar`, add to model `$fillable`.
   - Create `lang/{ar,en,fr}/reservation.php` for the ~31 built-in strings + localized weekday/month
     names (use Carbon locale for `formatDateAr`).
   - In `ReservationBotService::handle()` set `App::setLocale($settings->locale ?? 'ar')` and replace
     hardcoded strings with `trans('reservation.*')`. The per-tenant custom messages
     (`welcome_message`, etc.) still override the translated defaults.
2. **Push the gateway fix to a fork.** Point `xapi-prod-v1` git remote at your own fork and push
   commit `9b9c1e3` so it's backed up remotely.
3. **Fix gateway session-survives-restart** (the bug forcing a re-scan after every restart). This is
   the single biggest pain point — fixing it makes deploys/uptime painless and stops re-scan churn.
4. **Configure pm2 boot-persistence** (`pm2 startup`) so a server reboot doesn't kill the gateway.

---

## Gotchas learned

- Workers must be restarted after PHP changes (classes are cached in memory).
- The reservation instance ID rotates on every reconnect — never hard-code it.
- Stored gateway `connectionStatus: ONLINE` can be stale; the live `connectionState` is truth.
- Interactive to `@lid` works now, but only with the gateway `additionalNodes` fix in place.
- A crashing step handler used to wedge the cached session forever — now guarded.
