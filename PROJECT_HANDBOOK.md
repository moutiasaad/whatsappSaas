# wavadesk — Project Handbook

Consolidated reference for the WhatsApp SaaS customer-support platform (`wavadesk`). Compiled from CLAUDE.md, docs/, session handoffs, and memory notes. Point-in-time snapshot — always verify against current code before acting on any file/line reference.

---

## 1. Product & Stack

Multi-tenant WhatsApp customer-support workspace. Businesses (tenants) manage WhatsApp conversations through a shared platform with AI auto-reply (Claude), multi-team routing, and full conversation history.

| Concern | Value |
|---|---|
| Framework | Laravel 12 |
| DB | MySQL |
| Views | Blade + Alpine.js + Vite/Tailwind |
| Queue / cache / session driver | `database` |
| Realtime | Laravel Reverb (Pusher protocol via `pusher-js@8.4.0` + custom Echo shim) |
| WhatsApp gateway | Self-hosted CodeChat v1.3.7 (moved off `api.whatstshl.online` on 2026-06-07) |
| Payment gateway | Stripe Checkout Sessions (USD; replaced Flouci on 2026-06-01) |
| AI provider | Anthropic Claude (`anthropic-ai/sdk`) |
| Primary shell env | Windows 11 + PowerShell (Bash also available) |

### URLs / paths

| Item | Value |
|---|---|
| Production domain | `https://wavadesk.com` |
| Local dev URL | `http://localhost:8000` |
| WhatsApp gateway (prod) | `https://xapi-prod-v1.wavadesk.com` |
| DigitalOcean droplet | `142.93.203.48` |
| Laravel app path (prod) | `/www/wwwroot/public/wavadesk.com` |
| Gateway path (prod) | `/www/wwwroot/public/xapi-prod-v1.wavadesk.com` |
| App repo | `github.com/moutiasaad/whatsappSaas` (branch `main`) |
| Companion project | `Smart Routes/bestroutes.space` (Laravel 10 proxy) |

---

## 2. Roles & Route Prefixes

Four roles. Each has a canonical prefix and a named-route prefix. Every controller/view/permission check must respect them.

| Role | Path prefix | Named prefix | Management CRUD |
|---|---|---|---|
| `super_admin` | `/super-admin` | `super_admin.` | yes |
| `admin` (tenant admin) | `/tenant-admin` | `tenant_admin.` | yes |
| `supervisor` | `/supervisor` | `supervisor.` | no (teams only) |
| `agent` | `/agent` | `agent.` | no |

- Legacy `/admin` prefix kept for compatibility; `role_path` middleware enforces on safe methods.
- `User::homeRouteName()` returns the correct dashboard route per role.
- Super Admin login route is dedicated: `/superadmin/login`.

### Permission matrix (as enforced by middleware)

| Capability | Roles |
|---|---|
| Pool / claim / reply / close | all |
| Reassign | supervisor+ |
| Instances / users / teams CRUD | admin, super_admin |
| AI config / knowledge base | admin only |
| Impersonation | admin, super_admin |
| Tenant / billing / audit log | admin, super_admin |
| Platform tenants / plans / global settings | super_admin only |

### Sidebar guards

`resources/views/layouts/admin.blade.php` uses:
```php
$sp = fn(string $p) => Auth::user()->hasSuperAdminPermission($p);
```
Every super-admin sidebar item is wrapped in `@if($sp('slug'))`. The "Super Admins" nav is gated by `@if(Auth::user()->isMasterSuperAdmin())`.

---

## 3. Data Model (canonical)

Schema was aligned to screenshots on 2026-05-20.

**Tenant** — `name`, `slug`, `plan_id` (FK), `subscription_status` (trial/active/inactive/suspended/cancelled), `is_active`, `trial_ends_at`, `subscription_ends_at`. Relations: `plan()`, `users()`, `teams()`, `whatsappInstances()`, `payments()`.

**User** — `name`, `email`, `password`, `role` (super_admin/admin/supervisor/agent), `tenant_id` (nullable — null for super_admin), `api_key`, `sidebar_permissions` (JSON, nullable → master super-admin). Relations: `tenant()`, `teams()` (BelongsToMany). Helpers: `homeRouteName()`, `isMasterSuperAdmin()`, `hasSuperAdminPermission()`.

**Plan** — `name`, `slug`, `price_monthly` (decimal), `price_annual` (decimal), `max_users`, `max_instances`, `max_conversations_per_month`, `ai_included` (bool), `is_active` (bool), `reservations_enabled` (bool — gates the reservation bot).

**WhatsAppInstance** — `tenant_id`, `team_id` (nullable), `name`, `status` ENUM (`connecting`, `qr_pending`, `connected`, `disconnected`, `error`, `banned` — **`pending` is NOT valid**), `phone_number`, `qr_code`, `webhook_url`, `webhook_token`, `webhook_enabled`, `webhook_last_set`, `gateway_instance_id`, `gateway`, `gateway_url`, `gateway_api_key`. Relations: `tenant()`, `team()`.

**Team** — `tenant_id`, `name`, `description`. Relations: `tenant()`, `users()` (BelongsToMany), `instances()`.

**Conversation** — `tenant_id`, `instance_id`, `customer_id`, `team_id`, `assigned_user_id`, `owner_agent_id`, `status` (open/pending/closed), `state` (pool/claimed/closed), `ai_suspended`, `last_message_preview` (VARCHAR 255 as of 2026-06-01).

**Customer** — has `phone_e164`; `displayPhone` accessor masks `@lid` privacy IDs. `toArray()` override forces `phone_e164` to serialize as masked value in every JSON response.

**Message** — `conversation_id`, `direction` (in/out), `author_type` ENUM(`customer`, `agent`, `ai`, `system`) — **never `bot`, that silently fails inserts**. `status` (pending/sent/delivered/read/failed). `is_suggestion` (bool). Bodies used for `last_message_preview` are truncated with `Str::limit($body, 200)`.

**TenantPayment** — `tenant_id`, `plan_id` (nullable), `amount` decimal(10,3), `currency` default `'USD'`, `stripe_session_id` (indexed), `stripe_checkout_url` VARCHAR(600), `status` (pending/completed/failed), `gateway_response` (JSON), `paid_at` (nullable). Helpers: `isCompleted()`, `isPending()`.

**AiSettings** — `mode` (off/suggestion/autonomous/hybrid), `monthly_token_quota` (0 = unlimited), `tokens_used_this_period`, `reply_when_claimed` (bool default false).

**SavedReply** — `tenant_id`, `owner_user_id`, `scope` (`tenant`/`personal`), `title`, `shortcut`, `body`, `sort_order`. Uses `BelongsToTenant`, has `visibleTo($user)` scope.

**KnowledgeEntry** — types: `faq`, `info`, `product` (all three included by `PromptBuilder::buildSystemPrompt()`). Uses `BelongsToTenant` global scope named `'tenant'` — in queue jobs, bypass it: `KnowledgeEntry::withoutGlobalScope('tenant')->where('tenant_id', $id)->get()`.

**LegalPage** — DB-backed legal pages, unique `(slug, locale)`, `forSlug(slug, locale)` with EN fallback.

**PlatformSetting** — global tenant-agnostic settings.

**ReservationSetting** — per-tenant, `is_active`, `trigger_keywords` (array), `matchesTrigger()`.

**ImpersonationLog**, **ConversationEvent**, **WebhookEvent** — audit / event trails.

---

## 4. WhatsApp Gateway (CodeChat)

Self-hosted CodeChat v1.3.7 (fork of Evolution API contract). Talks the same shape as the iStoreBox contract used before.

### Env (production)
```
WHATSAPP_API_URL=https://xapi-prod-v1.wavadesk.com
WHATSAPP_API_KEY=2cb50042fef04ebccf160556d11074081f73c38b9b75a56679ba64b0f8af0233
WHATSAPP_WEBHOOK_BASE_URL=https://wavadesk.com
```
Auth header: `apikey`. Client: `app/Services/WhatsApp/Gateway/EvolutionApiClient.php`.

### Endpoints used
| Method | Path | Notes |
|---|---|---|
| POST | `/instance/create` | |
| GET | `/instance/connect/{name}` | returns `{ count, base64, code }`; `base64` already has `data:image/png;base64,...` |
| GET | `/instance/fetchInstance/{name}` | returns `connectionStatus: "ONLINE"` + `ownerJid` |
| GET | `/instance/connectionState/{name}` | live state — trust this over stored `connectionStatus` |
| PUT | `/webhook/set/{name}` | |
| POST | `/message/sendText/{name}` | |
| POST | `/message/sendMedia/{name}` | |
| POST | `/message/sendListMessage/{name}` | custom-added |
| POST | `/message/sendButtons/{name}` | custom-added |
| PATCH | `/chat/readMessages/{name}` | **PATCH**, not POST (fixed 2026-06-07) |
| PATCH | `/chat/updatePresence/{name}` | body `{ number, presence }`; values: `unavailable\|available\|composing\|recording\|paused` |
| DELETE | `/instance/delete/{name}` | |
| DELETE | `/instance/logout/{name}` | clears session before reconnect |

Instance name format: `wa-{tenant_id}-{instance_id}` (SingleInstance uses timestamp suffix on force-reconnect).

### Webhook

- Format: `https://wavadesk.com/api/webhooks/whatsapp/{webhook_token}`
- Route: `POST /api/webhooks/whatsapp/{token}` → `WhatsAppWebhookController::handle()` (public, no auth)
- Instance identified by `webhook_token` (64-char random, auto-generated on create). Unknown token → 401 (expected).
- No signature secret by default (`webhook_secret = null` → verification always passes).

### CodeChat webhook payload
```json
{
  "event": "messages.upsert",
  "instance": { "name": "wa-1-3", "id": 1, "ownerJid": "..." },
  "data": {
    "id": 1882, "keyId": "…", "keyFromMe": false,
    "keyRemoteJid": "1234567890@s.whatsapp.net",
    "pushName": "John", "messageType": "conversation",
    "content": { "text": "Hello" },
    "messageTimestamp": 1701767264
  }
}
```

### Event handling (`ProcessIncomingMessage`)

| CodeChat event | Normalized | Handler |
|---|---|---|
| `messages.upsert` | `messages.upsert` | `handleMessage` |
| `send.message` | `send.message` | ignored |
| `connection.update` | `connection.update` | `handleConnectionUpdate` |
| `qrcode.updated` | `qrcode.updated` | `handleConnectionUpdate` → status = `connecting` |
| `status.instance` | `status.instance` | `handleConnectionUpdate` |

### QR / connect flow

- `connect()` creates the gateway instance if missing (name `wa-{tid}-{iid}`), fetches QR, returns cached QR if still connecting/qr_pending.
- Webhook registered inside `connect()` — no longer deferred to status check.
- QR modal does **not** re-POST `/api/instances/{id}/connect` in a loop.
- Fallback close: **Validate button** (added 2026-06-23, `fb8533c`) calls `GET /api/instances/{id}/status`. Connected → close modal + toast; still pending → info toast.
- QR image rides plain HTTP, independent of the WebSocket. Reverb only auto-closes the modal.

### Phone number sync

`extractPhoneNumber()` in `InstanceController` reads `ownerJid` / `number` / `me.jid` / `me.id` from `fetchInstance`, strips `@s.whatsapp.net` and any `:{deviceId}` suffix, stores digits in `whatsapp_instances.phone_number`. Fixes the previous "Connectée" + "Aucun numéro" case.

### Instance CRUD UI (2026-06-23)

- **Create** (`79f40e2`): Gateway selector + Webhook URL info card removed — noise. Only Evolution-style gateway supported. `store()` forces `gateway = 'evolution_api'` and pulls URL/key from `config('services.whatsapp.default_url'|'default_api_key')`. `update()` accepts only `name` + `team_id`. Webhook still auto-registered server-side.
- **Index rows** (`6ac68fe`): Connect / Show QR / Refresh / Webhook Events / Edit only. **Delete and Disconnect moved to the edit page.**
- **Edit page**: red Danger Zone for Delete, orange Connection card for Disconnect (visible only when status is `connected` / `qr_pending` / `connecting`).

### Migrating instances after a gateway swap

```bash
php artisan tinker --execute="DB::table('whatsapp_instances')->update([
    'gateway_instance_id' => null,
    'webhook_enabled'     => false,
    'webhook_url'         => null,
    'webhook_last_set'    => null,
    'qr_code'             => null,
    'status'              => 'disconnected',
    'phone_number'        => null,
]); echo 'Done';"
```
Then reconnect from the admin panel and re-scan.

---

## 5. Gateway Server (CodeChat v1.3.7)

Same DO droplet as the Laravel app. Node.js + TypeScript, Express 5, `@whiskeysockets/Baileys@6.7.23` (pinned exact), Prisma + PostgreSQL 16, port **8084**.

- Local source: `C:\Users\mouti\OneDrive\Documents\Projects\Chatbot\whatsapp-api`
- Prod path: `/www/wwwroot/public/xapi-prod-v1.wavadesk.com`
- Upstream: `github.com/code-chat-br/whatsapp-api` (can't push directly — mirror to a fork)

### Runtime model — critical

- **No PM2 / no supervisord.** Runs as a bare detached node process: `nohup node dist/src/main.js > /tmp/wa_api.log 2>&1 &`
- Logs: `tail -f /tmp/wa_api.log`. Find PID: `ps aux | grep node`.
- `start.sh` does `rm -rf ./dist && npm run build`. **Never** run `start.sh` or `npm run start:prod` unless a full rebuild is wanted.
- Safe restart: kill node process → `npm run build` → relaunch with `nohup`.

An earlier iteration used pm2 as **"CodeChat Api"** — that app still knows the ecosystem, but the current process model is bare node.

### Baileys pinning

- `7.0.0-rc13` broke QR generation (count:0 forever, no creds.json). Downgraded to **6.7.23**.
- `src/utils/extract-id.ts` patched to remove 7.x-only fields (`remoteJidAlt` / `participantAlt`) so tsc compiles on 6.x.
- On 6.7.23 baseline: `buttonsMessage` / `listMessage` return 201 but WhatsApp renders them as **plain text**. The **decisive fix** for tappable interactive messages was gateway-side (see §7).

### Custom endpoints added (both `src/` and `dist/`)

- `POST /message/sendListMessage/{instance}` — uses `listMessageLegacySchema`, calls `listMessage()`.
- `POST /message/sendButtons/{instance}` — uses `buttonsMessageSchema`, calls `buttonsMessage()`.
- Files touched: `whatsapp.service.ts/js`, `sendMessage.controller.ts/js`, `sendMessage.router.ts/js`.

### Interactive-message fix (root cause)

- The old build sent `interactiveMessage` payloads **without** the `biz → interactive → native_flow` relay node, so WhatsApp silently dropped buttons (for real numbers) and the whole message (for `@lid`).
- Fix in `src/whatsapp/services/whatsapp.service.ts` (commit `9b9c1e3`, **local-only**, not pushed):
  1. Add `header: { title, hasMediaAttachment: false }` to `interactiveMessage`.
  2. Attach `additionalNodes` in `relayMessage`:
     ```ts
     relayOptions.additionalNodes = [{ tag:'biz', attrs:{}, content:[{
       tag:'interactive', attrs:{ type:'native_flow', v:'1' },
       content:[{ tag:'native_flow', attrs:{ name:'mixed', v:'1' }, content:[] }],
     }]}];
     ```
  3. Rewrote `listMessage()` to build a native-flow `single_select` (legacy list proto no longer renders in current WhatsApp).

### Sultankoo reference (working buttons payload)

```json
{
  "number": "phone@s.whatsapp.net",
  "options": { "delay": 1200, "presence": "composing" },
  "buttonsMessage": {
    "title": "Title", "description": "Body text", "footer": "",
    "buttons": [
      { "type": "reply", "displayText": "Option 1", "id": "btn_1" },
      { "type": "reply", "displayText": "Option 2", "id": "btn_2" }
    ]
  }
}
```

### Known operational pain points

- Gateway **loses its live WhatsApp link on every restart** (does not auto-reconnect from saved creds). Every restart = one QR re-scan.
- pm2 boot-persistence (`pm2 startup`) is not configured — a server reboot kills the gateway.
- Local `xapi-prod-v1` git remote points at upstream — push the interactive-message fix to a fork so it survives future rebuilds.

---

## 6. Reservation Bot

`app/Services/Reservation/ReservationBotService.php` — end-to-end WhatsApp appointment booking via a cache-backed state machine.

### Startup checklist (bot not replying)

1. `plan->reservations_enabled` must be `true` (gated in `ProcessIncomingMessage::tenantHasReservations()`).
2. `reservation_settings.is_active` must be `true` for the tenant.
3. Queue workers running: `supervisorctl status wavadesk:*`. Job runs on the `whatsapp` queue.
4. Webhook URL is `{APP_URL}/api/webhooks/whatsapp/{instance->webhook_token}` (note `api/` prefix and plural `webhooks`).
5. `WHATSAPP_WEBHOOK_BASE_URL` in `.env` must be a public URL, not `127.0.0.1`. For local, use ngrok/Cloudflare Tunnel and reconnect the instance afterwards.

### State machine

- Cache key: `reservation_bot:{tenantId}:{phone}`, TTL 1800 s (30 min).
- Steps: `select_date` → `select_period` (only if AM and PM both exist) → `select_slot` → `enter_name` → `enter_notes` (optional) → `confirmed`.
- Trigger keywords (defaults): `حجز`, `book`, `booking`, `réservation`. Overridable per-tenant.
- Cancel keywords: `إلغاء`, `cancel`, `annuler`, `الغاء`.
- Tap-to-select response is read from `message.listResponseMessage.singleSelectReply.selectedRowId`, plus button-tap paths (`buttonReply.id`, `listReply.rowId`, then display-text fallbacks).

### Interactive messages

- `sendList()` and `sendButtons()` used on every step; text fallback on failure.
- With the gateway `additionalNodes` fix in place, buttons/lists render as **tappable** on both real `@s.whatsapp.net` and `@lid` recipients.
- A tap is decoded back to the numeric choice — flow logic unchanged.

### Historical bugs (all fixed)

- `author_type = 'bot'` silently rejected by MySQL enum — must be `'ai'`. Fix in `persistMessage()`.
- Multi-device JID (`21265182831:5@s.whatsapp.net`) — strip `@…` then `:\d+$` in `ProcessIncomingMessage::extractFrom()` so cache keys match.
- `Message::create()` was outside try-catch → wedged sessions on throw. Now inside `persistMessage()` with its own try-catch; step errors clear the session.
- `@lid` resolution added a blocking ~13 s HTTP call → job re-picked past `retry_after` → duplicate-worker dedup returned early. Solution: don't resolve `@lid` (gateway accepts it as recipient), added `keyLid` to sender-extraction, wrapped `broadcast()` in try/catch.

### Reservation instance details

- Tenant 4, plan "Growth", `reservations_enabled = true`.
- Business number: `21627541269`. Test real number (receives interactive): `21628067392`. Test `@lid` device: `186827017302085@lid`.
- Instance ID rotates on every reconnect (13→14→15→16 as of 2026-06-10). Always look up: `WhatsAppInstance::withoutGlobalScopes()->where('tenant_id',4)->latest('id')->first()`.

### TODO (from last session)

- Multilingual bot: add `locale` column to `reservation_settings` (default `ar`), add `lang/{ar,en,fr}/reservation.php`, `App::setLocale($settings->locale ?? 'ar')` in `handle()`, use `trans('reservation.*')` for defaults.
- Push gateway fix to a fork.
- Fix gateway session-persistence-across-restart.
- Configure `pm2 startup` for boot-persistence.

---

## 7. AI Auto-Reply

`app/Services/AI/AutoReplyService.php` — the Anthropic client and eligibility rules.

### SDK call shape

```php
$client   = new \Anthropic\Client(apiKey: $apiKey);
$response = $client->messages->create(
    maxTokens: ..., messages: ..., model: ..., system: ...
);
// text extraction concatenates all text content blocks
```
`messages` is a **property**, `create()` uses **named args** — not a method + assoc array.

### Eligibility

`app/Models/Conversation.php`:
```php
public function isAiEligible(): bool { return !$this->isClosed() && !$this->ai_suspended; }
```
Old gate required `state === 'pool'` — blocked AI on claimed conversations even after manual re-activation.

Behavior summary:

| Situation | `ai_suspended` | AI replies? |
|---|---|---|
| Pool, untouched | false | ✅ |
| Agent claims it | true (auto) | ❌ |
| Admin re-activates on the conversation | false | ✅ (even if claimed) |
| Admin suspends per-conversation | true | ❌ |
| Global mode = off | — | ❌ |
| Conversation closed | — | ❌ |

### Quota

`AiSettings::hasQuota()`:
```php
if ($this->monthly_token_quota === 0) return true; // 0 = unlimited
return $this->tokens_used_this_period < $this->monthly_token_quota;
```
`0` means **unlimited**. Token Quota UI card removed from AI settings — confusing.

### PromptBuilder

- Claude Messages API requires strictly-alternating user/assistant roles and first message MUST be user. `buildMessages()` merges consecutive same-role turns and drops leading assistant turns.
- `buildSystemPrompt()` includes `faq`, `info`, and `product` knowledge entries (previously `product` was silently dropped).

### `reply_when_claimed` setting

- Global per-tenant toggle in `ai_settings.reply_when_claimed` (default `false`).
- OFF (default): claiming/reassigning sets `ai_suspended = true`.
- ON: AI keeps replying after claim/reassign.
- Applies to **future** claims/reassigns only. Already-suspended conversations need the per-conversation AI toggle flipped.

### AI modes

| Mode | Behavior |
|---|---|
| `off` | No auto-reply |
| `suggestion` | AI drafts (`is_suggestion: true`, `status: pending`), NOT sent. No approve-and-send UI yet |
| `autonomous` | Dispatches `SendOutgoingMessage`, delivered directly |
| `hybrid` | Autonomous, toggleable per-conversation |

### `@lid` phone masking

`Customer::toArray()` override returns masked `displayPhone` for `phone_e164` in every JSON/array serialization. Raw column remains available server-side as `$customer->phone_e164` for gateway calls and search. Real number can't be resolved for LID contacts — CodeChat webhook has no real-number field for `@lid`, and `findContacts` returns the same `@lid`.

### Operational

- Restart queue worker after deploys: `php artisan queue:restart`
- Worker: `php artisan queue:work --queue=whatsapp,default`
- If PHP-FPM has OPcache, reload it so model/service changes take effect.

---

## 8. Knowledge Base

Per-tenant. Used by the AI to build the system prompt.

- Types: `faq` (→ `## Frequently Asked Questions`), `info` (→ `## General Information`), `product` (→ `## Products & Services`).
- `KnowledgeController::store()/update()/destroy()` redirects use `auth()->user()->routeNamePrefix() . '.knowledge.index'` — never hardcode `admin.knowledge.index` (breaks tenant-admin).
- Edit view: `resources/views/admin/knowledge/edit.blade.php` (was missing → 500). Type select, title, body, `is_active` toggle, `@method('PUT')` form.
- **Global scope pitfall:** `KnowledgeEntry` uses `BelongsToTenant` global scope named `'tenant'`. In queue jobs (no request context) bypass it: `KnowledgeEntry::withoutGlobalScope('tenant')->where('tenant_id', $id)->get()`.

---

## 9. Real-time Broadcasting

Driver: **Laravel Reverb** (Pusher protocol). Frontend uses `pusher-js@8.4.0` CDN + a custom Echo shim in `resources/views/layouts/admin.blade.php` (no `laravel-echo` npm package).

### `.env`

```
BROADCAST_CONNECTION=reverb   # was `log` — `log` silently swallows everything
REVERB_APP_ID=…
REVERB_APP_KEY=…
REVERB_APP_SECRET=…
REVERB_HOST=wavadesk.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```
Start: `php artisan reverb:start --host=0.0.0.0 --port=8080`.

### Event classes (`app/Events/`) — all implement `ShouldBroadcastNow`

| Event | Channel | broadcastAs |
|---|---|---|
| `MessageReceived` | `tenant.{id}.conversation.{id}` | `message.received` |
| `MessageSent` | same | `message.sent` |
| `AgentTyping` | same | `agent.typing` |
| `ConversationClaimed` | conversation + `tenant.pool` + `team.pool` | `conversation.claimed` |
| `ConversationReleased` | same three | `conversation.released` |
| `ConversationClosed` | `tenant.{id}.conversation.{id}` | `conversation.closed` |
| `ConversationReopened` | `tenant.pool` + `team.pool` | `conversation.reopened` |

Reason for `ShouldBroadcastNow` (not `ShouldBroadcast`): with `database` queue driver, queued broadcasts add seconds of latency. `Now` fires inline.

### Echo shim state

`window._echoConnected` (bool), `window._echoStateListeners` (push callbacks reacting to state changes).

### Channel authorization (`routes/channels.php`)

| Channel | Access |
|---|---|
| `tenant.{t}.conversation.{c}` | Agent owns OR same team; supervisor same team; admin/super_admin always |
| `tenant.{t}.pool` | admin/super_admin only |
| `tenant.{t}.team.{t}.pool` | team members + admin/super_admin |

### Chat polling + WebSocket hybrid

Livewire message component was abandoned (broken when nested inside outer Alpine scope). Current model: pure Alpine `x-for` on `messages: []`, polls `/api/conversations/{id}/messages` every 3 s. WebSocket events also call `_refreshMessages()` for instant delivery when Reverb is up. `wsConnected` reactive var is UI-only (dot indicator).

`_refreshMessages()`:
1. Patches status on existing messages (pending → sent → delivered → read).
2. Appends genuinely new messages.

Message tick icons (Remixicon):

| Status | Icon | Class |
|---|---|---|
| `failed` | `ri-close-circle-line` | `cw-tick-wrap` |
| `pending` | `ri-time-line` | `cw-tick-wrap cw-tick-clock` |
| `sent` | `ri-check-line` | `cw-tick-wrap` |
| `delivered` / `read` | `ri-check-double-line` | `cw-tick-wrap cw-tick-blue` (#38bdf8) |

`cw-tick-pop` keyframe (scale .5→1) plays on DOM insertion.

### `switchTo(id)` — AJAX conversation switching

1. Guards: `!id`, `id === conversationId`, `_switching` debounce.
2. `GET /api/conversations/{id}/workspace` — bundles conversation + customer + instance + team + tenant + owner + first-page messages + events + assignable agents + AI mode.
3. `applyWorkspace(data)` then `history.pushState`.
4. `popstate` wired for back/forward.
5. On error: `console.error('[switchTo] server error: ...')` + toast. Open DevTools console to diagnose.

`ConversationPolicy::view` (agent visibility):
```php
return $conversation->owner_agent_id === $user->id
    || $this->canAccessTeam($user, $conversation);
```
Both conditions required — first covers agents reassigned from teams they left; second covers pool + teammates.

### Presence typing

`ConversationController::updatePresence()` broadcasts `AgentTyping->toOthers()` before forwarding presence to the gateway.

### Production Reverb requirements (when WS fails on `wss://wavadesk.com/app/<key>`)

Diagnose in order:
1. `.env`: `BROADCAST_CONNECTION=reverb`, non-empty Reverb keys, `REVERB_HOST=wavadesk.com`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, server host/port. Then `php artisan config:clear && config:cache`.
2. Reverb daemon on 8080 (`ss -ltn | grep 8080`). Start under supervisor/pm2.
3. **nginx WebSocket proxy** — `wavadesk.com` server block needs `location /app` and `location /apps` proxying to `http://127.0.0.1:8080` with Upgrade/Connection headers and `proxy_http_version 1.1`. Most-common missing piece.

Local dev has `BROADCAST_CONNECTION=log` on purpose. No local code change fixes a production WS issue.

The QR modal does NOT depend on WebSocket — QR image rides plain HTTP; WS only handles auto-close on connect. Fallback is the Validate button.

---

## 10. Payments — Stripe

Flouci fully removed; Stripe Checkout Sessions used everywhere. Currency: USD (was TND).

### `.env`

```
STRIPE_SECRET_KEY=sk_test_…
STRIPE_PUBLIC_KEY=pk_test_…
STRIPE_WEBHOOK_SECRET=            # set after registering webhook in Stripe dashboard
```

### Files

- `config/services.php` — `stripe.secret`, `stripe.public`, `stripe.webhook_secret`
- `app/Services/StripeService.php` — `createCheckoutSession()`, `retrieveSession()`, `isCompleted()`, `constructWebhookEvent()`
- `app/Http/Controllers/PaymentController.php` — checkout, initiate, upgrade, success, failed, webhook
- `app/Models/TenantPayment.php` — fillable includes `stripe_session_id`, `stripe_checkout_url`, `gateway_response`
- Migration: `database/migrations/2026_06_01_130000_migrate_tenant_payments_to_stripe.php`

### DB columns (`tenant_payments`)

| Column | Notes |
|---|---|
| `stripe_session_id` | was `flouci_payment_id` |
| `stripe_checkout_url` | was `flouci_pay_url`, VARCHAR(600) |
| `gateway_response` | was `flouci_response`, JSON |
| `currency` | default `'USD'` (was `'TND'`) |

### Payment flow (new signup)

1. Register → creates Tenant (inactive) + User, stores user ID in `_pending_register_user` session
2. `/payment/checkout/{tenant}` → order summary, plan name + USD price
3. POST `/payment/initiate` → creates Stripe Checkout Session, stores `stripe_session_id`, redirects to `session->url`
4. Stripe redirects to `/payment/success?session_id={CHECKOUT_SESSION_ID}`
5. `success()` calls `retrieveSession()`, checks `payment_status === paid`, activates tenant (+30 days), logs in user
6. `POST /payment/webhook` (CSRF-exempt) handles `checkout.session.completed`

### Upgrade flow

- Route: `POST /payment/upgrade` (auth)
- `PaymentController::upgrade()` validates `tenant_id` + `plan_id`, updates `tenant->plan_id`, redirects to checkout
- Billing page plan cards post to this route; settings page shows "Upgrade Plan" when higher plans exist

### Free-plan shortcut

Plans with `price_monthly = 0` skip payment — tenant activated immediately.

### Trial → Active bug (fixed 2026-06-06)

`processPayment()` used `$tenant->is_active` to branch new-vs-upgrade — new tenants had `is_active=false` so they hit the wrong branch and were left in `subscription_status = 'trial'`. Fix: always set `subscription_status = 'active'`, `subscription_ends_at = now()->addMonth()`, `is_active = true`, `plan_id = $payment->plan_id` on payment completion regardless of prior state.

### Notes

- Stripe requires a **business name** at `dashboard.stripe.com/account` before Checkout Sessions work (even in test mode).
- `APP_DEBUG=true` surfaces real Stripe errors instead of the generic message on checkout failure.
- Package: `stripe/stripe-php` via composer.

---

## 11. Landing & Registration Flow

Design tokens: primary `#10b981` emerald, dark hero `#0d1117`, font Outfit. ERP reference layout at `C:\Users\mouti\OneDrive\Documents\Projects\Gestion\ERP`.

### Views

- `resources/views/landing.blade.php` — sticky nav, dark hero (locale-aware chat mockup via `landing.chat_*` keys), marquee trust bar, 6-card features, billing toggle + dynamic plan cards, 4-step how-it-works, 6-item FAQ (`align-items:start` on `.faq-grid` prevents neighbor stretch), dark CTA, footer with legal links. IntersectionObserver reveal + monthly/annual toggle. Plan cards embed count via `:count` placeholder — do NOT add a separate `{{ $plan->max_users }}` prefix.
- `resources/views/auth/register.blade.php` — split-panel form. Left: dark marketing panel. Right: plan selector cards + workspace/admin sections + password strength meter.
- `resources/views/auth/verify-otp.blade.php` — 6 digit inputs (auto-advance, backspace, paste), 60 s resend countdown (client timer + server 429), 3-step progress indicator on left.
- `resources/views/payment/checkout.blade.php` — Stripe order summary
- `resources/views/payment/success.blade.php` — animated check + meta refresh to dashboard
- `resources/views/payment/failed.blade.php` — retry link

### OTP email flow (2026-06-05)

- `RegisterController::store()` → generates 6-digit OTP → stores `session('_reg_pending')`: `otp`, `expires_at` (+10 min), `sent_at`, `data`, bcrypt `password` → `Mail::to()->send(new OtpVerification($otp))` → redirect to `register.otp`.
- `verifyOtp()` → expiry check → `hash_equals()` → create Tenant + User in transaction → free plans log in directly, paid plans redirect to `payment.checkout`.
- `resendOtp()` → JSON `{ok:true}` or `{error:...}`, 60 s cooldown.
- Mailable: `app/Mail/OtpVerification.php`. Template: `resources/views/emails/otp-verification.blade.php` (dark header, ecfdf5 green OTP box, 42px font, 14px letter-spacing).

### Routes

```
GET  /                             landing
GET  /register                     register            (guest)
POST /register                     register.store      (guest)
GET  /register/verify-otp          register.otp        (guest)
POST /register/verify-otp          register.otp.verify (guest)
POST /register/resend-otp          register.otp.resend (guest)
GET  /payment/checkout/{tenant}    payment.checkout
POST /payment/initiate             payment.initiate
GET  /payment/success              payment.success
GET  /payment/failed               payment.failed
POST /payment/webhook              payment.webhook     (CSRF-exempt)
GET  /legal/terms                  legal.terms
GET  /legal/privacy                legal.privacy
GET  /legal/cookies                legal.cookies
```

### Legal pages

- Model `LegalPage` with `(slug, locale)` unique key; `forSlug($slug, $locale)` with EN fallback.
- Migration: `2026_06_05_070146_create_legal_pages_table.php`.
- Admin editor: `LegalPageController` (Quill.js). Index cards `grid-template-columns:repeat(3,1fr)`.
- Super-admin routes: `platform/legal-pages`, `platform/legal-pages/{slug}/{locale}/edit` (PUT same path).
- Sidebar key: `ui.sidebar.legal_pages`.

### Login page (2026-06-05)

- Logo navigates to landing via `route('landing')`.
- Language switcher + logo in the same `.login-header` flex row (was `position:absolute` — caused float outside card).
- "Sign up here" link below form via `auth.login.no_account` / `auth.login.sign_up_here`.

---

## 12. Dashboard — Tenant Scope Rule

**Every dashboard query must include a `tenant_id` filter.** `DashboardController::index()` was leaking data across tenants until 2026-06-05.

Pattern for queries on models that don't have `tenant_id` directly (e.g. `Message`, `ConversationEvent`):
```php
whereHas('conversation', fn ($q) => $q->where('tenant_id', $tenantId))
```

Never add global `Model::count()` / `Model::get()` calls to tenant-scoped dashboards.

---

## 13. Profile Page

All roles have `/{role-prefix}/profile`.

### Controller (`app/Http/Controllers/Admin/ProfileController.php`)

- `show()` → `admin.profile.index`
- `updatePassword()` → validates `current_password` via `Hash::check`, bcrypt update
- `requestEmailChange()` → validates new email unique, generates OTP, stores `_email_change_pending` session, sends `OtpVerification` mail to new address, returns JSON
- `verifyEmailChange()` → checks session TTL (10 min), OTP match, updates email

### Routes (in shared panel closure)

```php
Route::get('/profile',                 [ProfileController::class, 'show'])->name('profile.show');
Route::put('/profile/password',        [ProfileController::class, 'updatePassword'])->name('profile.password');
Route::post('/profile/email-change',   [ProfileController::class, 'requestEmailChange'])->name('profile.email-change');
Route::post('/profile/email-verify',   [ProfileController::class, 'verifyEmailChange'])->name('profile.email-verify');
```

### View

Two-column grid, full width. Change Password card (current/new/confirm PUT). Change Email card (readonly current + new + "Send verification code" → OTP modal with 60 s resend cooldown). Alpine `profilePage()` with `requestOtp()`, `verifyOtp()`, `resendOtp()`, `startCooldown()`. Reuses `OtpVerification` mailable.

### Sidebar footer

```blade
<a href="{{ route($panelPrefix . '.profile.show') }}" class="sidebar-user-btn">
    <i class="ri-user-settings-line"></i>
</a>
```
The old green avatar in the top-right navbar was removed.

### Pitfall

PHP class constants (`self::RESEND_COOLDOWN_S`) cannot be interpolated inside Blade JS strings — hardcode the value (`60`) directly.

---

## 14. Billing / Payments Admin

Super-admin-only history page at `/super-admin/billing/payments`.

- Route: `GET /billing/payments` → `BillingController::payments()` → `billing.payments` (guarded internally with `abort_unless(isSuperAdmin(), 403)`).
- Controller: `TenantPayment` eager-loads `tenant` + `plan`; filters `status` + `search` (tenant name); paginates 25/page; computes `$totals` (count by status) and `$totalRevenue` (sum of completed).
- View: `resources/views/admin/billing/payments.blade.php`. 4 KPI cards (Total Revenue USD, Total Transactions, Pending orange, Failed red); tenant search + status dropdown; table columns: #, Tenant (initials avatar + name + slug), Plan, Amount (green when completed), Status badge, Date (paid_at or created_at), Stripe Session ID (monospace, truncated); pagination + empty state.
- Sidebar link uses `route($panelPrefix . '.billing.payments')`. Billing nav uses `billing.index` (not `billing.*`) so it doesn't stay active on the payments subpage.
- Translation keys in both `lang/en/ui.php` and `lang/fr/ui.php`: `sidebar.payments`, `payments_page.*`.

---

## 15. Super Admin Management

Master-only CRUD at `/super-admin/super-admins`.

### Permission model

- `users.sidebar_permissions` — nullable JSON. `null` = master (full access); array of slugs = restricted.
- `isMasterSuperAdmin()` → `isSuperAdmin() && sidebar_permissions === null`.
- `hasSuperAdminPermission(string $slug)` → `true` for non-super-admins (always allowed), `true` if master, else check the array.
- If all 13 slugs are selected on create/update → store `null` (promote to master-equivalent).

### 13 permission slugs (`config/super_admin_permissions.php`)

Platform: `platform_tenants`, `platform_plans`, `platform_system_health`, `platform_legal_pages`  
Content: `conversations`, `customers`  
Management: `instances`, `teams`, `users`  
Account: `reports`, `audit_log`, `billing`, `notifications`

### Files

- `config/super_admin_permissions.php` — slug → `[icon, group]` map
- `app/Http/Controllers/Admin/SuperAdminManagerController.php` — index/create/store/edit/update/destroy + `assertMaster()` guard
- Migration `2026_06_09_085929_add_sidebar_permissions_to_users_table.php` — adds `sidebar_permissions JSON NULL` after `api_key`
- Views under `resources/views/admin/super-admins/`
- `_perm_styles.blade.php` — toggle switch CSS + select/deselect JS

### UI critical rule

Use PHP `foreach ($permissions as $slug => $meta)` when building groups — `collect()->groupBy()` drops associative string keys, which produced `perm_0`/`perm_1` translation lookup failures.

---

## 16. External API — X-Api-Key

Bearer tokens were removed in the 2026-06-08 session. External callers use a static API key.

- Key format: `wvd_` + 48 random chars, stored in `users.api_key`.
- Header: `X-Api-Key` (middleware also accepts lowercase and `?api_key=` query).
- Middleware: `App\Http\Middleware\AuthenticateWithApiKey` — tries the header first, then falls back to `Auth::guard('web')->user()` so browser sessions on `/api/*` still work.
- No expiry. User regenerates from Profile page if compromised.

### Endpoints (auth via X-Api-Key)

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/send` | Direct send to any number — `DirectSendController` |
| GET | `/api/instance` | Primary instance details |
| GET | `/api/instance/status` | Refresh live status |
| POST | `/api/instance/connect` | Get QR (`?force=true` for fresh QR) |
| POST | `/api/instance/disconnect` | Log out WhatsApp session |

### DirectSendController — `POST /api/send`

- Picks first `connected` instance for the tenant
- Sends text via `EvolutionApiClient::sendText()`
- Uses `ConversationService::findOrCreateForIncoming()` to create/reuse conversation
- Reopens closed conversations on outbound
- Creates a `Message` record (`direction=out`, `author_type=system`) so it appears in the dashboard
- Returns `{ success, instance_id, conversation_id, data }`; 422 if no connected instance

### SingleInstanceController — `/api/instance/*`

- Always `WhatsAppInstance::where('tenant_id', ...)->orderBy('id')->first()` — no `{id}` param
- `connect(?force=true)`: on force, deletes old gateway instance (clears `webhook_enabled`, `webhook_url`, `gateway_instance_id`), creates a new one with timestamp suffix (`wa-{tid}-{iid}-{time}`), fetches fresh QR
- `status()`: never downgrades `connecting → disconnected` from a gateway poll alone; removes `getQrCode()` call (would invalidate a QR being scanned)
- Webhook registered in `connect()`; skipped if already `webhook_enabled=true` AND URL matches

### Documentation

- `public/docs/api.html`
- `wavadesk-api.postman_collection.json` — `api_key` collection variable, auth `apikey`, header `X-Api-Key`

---

## 17. Companion Project — bestroutes.space

Separate Laravel 10 app at `C:\Users\mouti\OneDrive\Documents\Projects\Smart Routes\bestroutes.space`. WhatsApp panel proxies Wavadesk API via Guzzle.

- Panel: `/dashboard/whatsapp`
- Controller: `app/Http/Controllers/Admin/AdminWhatsAppController.php` — `wavadesk($method, $path, $payload)` helper adds `X-Api-Key` + `Accept: application/json` headers
- View: `resources/views/admin/whatsapp/index.blade.php` (Alpine + inline styles)
- Layout: `resources/views/admin/layouts/app.blade.php` — `showDeleteModal()` supports `confirmLabel` + `confirmIcon`

### Critical quirks

1. **Pre-compiled static CSS** — bundled `dashboard-tailwind.css`, not JIT/CDN. Dynamic PHP-interpolated class names (`bg-{{ $color }}-500`) are purged. Always use inline `style=""` for dynamic colors, layout, and toggle states.
2. **`#mainContent` ID collision** — layout CSS forces `margin-left: 16rem !important`. Use a different ID (e.g. `id="waConnectionBody"`) for card body containers.
3. **Guzzle proxy pattern** — always `X-Api-Key: {config key}` + `Accept: application/json`.

Wavadesk endpoints used: `GET /api/instance`, `GET /api/instance/status`, `POST /api/instance/connect` (`?force=true`), `POST /api/instance/disconnect`.

---

## 18. UI Patterns

### AJAX Alpine page pattern

Applied to `/users`, `/teams`, `/tenants`, `/plans`, `/conversations`, `/customers`.

**Controller:**
- `expectsJson()` branch returns `response()->json(array_merge($paginated->toArray(), ['stats' => $stats]))` with `Cache-Control: no-store, no-cache, must-revalidate`.
- HTML branch returns bare `view('...')` — no data (Alpine loads it).
- `destroy(Request $request, Model $model)` — returns `response()->json(['message' => '...'])` on AJAX.
- `bulk()` — all error/success paths have `expectsJson()` branches.

**View:**
- Alpine component with `loading`, `rows`, `stats`, `pagination`, `selected`, `filters`.
- `reload(page)` updates all state.
- `pageshow` handler: `if (e.persisted) this.reload()` — fixes bfcache.
- Delete modal: `{ show, id, name, saving }`, `confirmDelete()` DELETE fetch → `reload()`.
- Bulk bar: `selected` array, `submitBulk()` posts JSON, `bulkSaving` flag.

**Checkboxes** must use CSS classes `header-cb` (select-all) and `row-cb` (per-row) — never inline `accent-color`. The admin layout defines the fully custom checkbox styles via `appearance:none`.

**Plans page** has no delete — only toggle status (PATCH) and bulk enable/disable.

### Conversation show page (`conversationPro()`)

`resources/views/admin/conversations/show.blade.php`.

- Left rail tabs use `ui.conversations_page.*` (NOT `ui.conversations_index.*` — the latter doesn't exist and `__()` returns the key string, never null, so `?? 'fallback'` never fires):
  ```blade
  {{ __('ui.conversations_page.my_conversations') }}   {{-- default --}}
  {{ __('ui.conversations_page.pool') }}
  {{ __('ui.conversations_page.all') }}
  {{ __('ui.conversations_page.closed') }}
  ```
- Default tab: `listTab: 'mine'` — only conversations owned by the logged-in agent.
- `_refreshList()` hits the web conversations index route (`route($panelPrefix.'.conversations.index')`) with `Accept: application/json` → delegates to `ApiConversationController::index()`. Polls every 5 s. Sends `tab=mine|pool|all|closed` and `per_page=80`. `filteredList` getter is a client-side safety net (`owner_agent_id !== this._meId` for `mine`).
- **File input**: three `<input type="file">` were consolidated to one. Use `style="display:none"` — NOT `class="hidden"` (no `.hidden` CSS in the scoped style block).
- **Attach button**: `cw-attach-group` with `cw-attach-main` + `cw-attach-fly` popover (centered above, CSS `::after` arrow). Fly-out reveals on hover/focus-within. Gradient bg + spring animation on hover.

### Claimed-by agent visibility

- **Show page**: state badge reads "Claimed · John Smith" from Alpine `agentName`, initialized from `$conversation->ownerAgent?->name` and updated via WebSocket.
- **List page**: small blue sub-line under the message preview. `owner_agent` eager-loaded in `ConversationController::index()` via `->with(['ownerAgent', ...])`.

### Saved replies

- Model: `SavedReply` — scope (tenant/personal), title, shortcut, body, sort_order
- Management page: `resources/views/admin/saved-replies/index.blade.php` (AJAX + Alpine)
- Web route: `GET /saved-replies` → `admin.saved-replies.index`
- API routes: `GET|POST /api/saved-replies`, `PUT|DELETE /api/saved-replies/{savedReply}` (existing)
- In the show page: `applySavedReply(reply, fromShortcut = false)` — **replaces** the draft, does not concatenate

### `last_message_preview` truncation

- Column widened to VARCHAR(255) in `2026_06_01_120000_widen_last_message_preview_column.php`
- All writes use `Str::limit($body, 200)` — in `MessageController::store()`, `storeNote()`, and `ProcessIncomingMessage`
- Bug it fixed: saved reply body up to 4000 chars was stored directly → SQLSTATE 22001

### Settings subscription card

- `SettingsController::index()` now passes `$latestPayment` (last completed) + `$upgradePlans` (excluding current)
- Card shows plan name + USD price, trial end date (color urgency), subscribed-since date, plan limits
- "Upgrade Plan" (green) → billing page when upgrade plans exist
- "Manage Billing" now visible to all admins (was super-admin only)

### Pro selector card pattern (chip + corner check)

Used for the registration plan picker and the reservation slots period picker.

- `resources/views/auth/register.blade.php` — `.plan-lbl` / `.plan-opt` / `.plan-lbl .check`
- `resources/views/admin/reservations/slots.blade.php` — `.period-picker` / `.period-card` / `.period-check`

Rules:
- Hide native input absolutely (`position:absolute;opacity:0;pointer-events:none`); keep it inside the `<label>` so a card click selects it.
- Body: large icon chip left (~2.5rem rounded square, accent tint), title + hint stacked right.
- Active card: solid accent border + soft accent-tinted background + 3px outer ring `box-shadow: 0 0 0 3px rgba(accent,.12)` + stronger chip tint.
- Top-right corner: small circular check badge (1.25rem, accent bg, white check icon). `display:none` default, `inline-flex` when active.
- Hover: light accent border + subtle tint, no ring (reserved for active).
- Multiple accents per page → `data-accent="morning"` / `data-accent="afternoon"` (or plan slug) on the card, CSS matches attribute.
- RTL: flip check badge to top-left via `[dir="rtl"] .your-check { left:.55rem; right:auto; }`.
- Inject CSS via `@push('styles')` — admin layout has `@stack('styles')` at `resources/views/layouts/admin.blade.php:1673`.

Reach for this pattern whenever the user asks for a "pro" selector.

### Inline Create Team / Create Agent modal

Empty-state pickers must let the user create records inline instead of routing to `/teams/create`. Two implementations to keep in sync:

- `resources/views/admin/instances/create.blade.php` — `instanceQuickCreate()`
- `resources/views/admin/users/create.blade.php` — `userCreateModals()`

Rules:
- Wrap parent form: `<div x-data="…factory()" @keydown.escape.window="escHandler($event)">`.
- Always render the `<select multiple>` (empty when no teams exist) + a `+` icon button. Empty-state hint is a link that opens the same modal — never link out.
- Two stacked modals (team wraps agent). `z-index 1000` outer, `1100` inner. `escHandler` peels in reverse.
- Endpoints: team modal → `route('admin.teams.store')`; agent modal → `route('admin.users.store')` with `role: 'agent'`. Both controllers already do `expectsJson()` branches.
- Pass `$agents` (tenant-scoped `whereIn('role', ['agent','supervisor'])->where('is_active', true)`) to the view.
- After team-create: append `<option selected>` to the parent `<select id="teams">`, flip `hasTeams`, rebuild the multi-select via `window.rebuildTeamsMultiSelect()`. `initMultiSelect` is idempotent — drops prior `.ss-wrap[data-for="…"]` sibling before re-rendering.
- After agent-create: push into `this.agents` and auto-add id to `teamForm.members` in `$nextTick`.
- Member picker uses the app's Select2-style widget (`.ss-wrap`), NOT real Select2.
- Copy lives in `lang/{en,fr,ar}/ui.php` under the parent page's namespace (e.g. `user_form_page.team_modal_title`, `…create_agent`, `…team_created_toast`, `…team_create_network_error`).

### Destructive actions belong on the edit page

- Index rows keep only Connect / Show QR / Refresh / Webhook Events / Edit — no red delete or logout icons.
- Edit pages gather destructive actions in colored cards:
  - **Connection card** (orange `#f59e0b` left border) — Disconnect/Logout, visible only when relevant state applies.
  - **Danger Zone card** (red `#ef4444` left border) — permanent Delete.
- Wire through `window.confirmDelete(url, { title, message })` or `window.confirmDelete(null, { title, message, callback })` (defined in `admin.blade.php`). Reload on success for server-rendered pages.
- Each new destructive action gets `*_button`, `*_prompt`, `*_warning`, `*_success`, `*_error` locale keys in en/fr/ar.

### Brand wording rule — no "SaaS" in user-facing UI

Brand is **wavadesk**. Scrub the literal word "SaaS" anywhere the end user sees inside the admin app.

- Sidebar / logo subtitle: plain `wavadesk` (with `Platform` underneath if a subtitle is wanted). Never `SaaS Platform`.
- Form subtitles and helper copy in `lang/{en,fr,ar}/ui.php`: describe the *thing* ("Add a new tenant account"), not the platform category.
- HTML `<title>` fallbacks: `wavadesk`, never `WhatsApp SaaS`.

**Intentionally left alone** (keep "SaaS"):
- `resources/views/legal/terms.blade.php` — "wavadesk is a multi-tenant SaaS platform…" is legal categorization.
- `lang/{en,fr,ar}/landing.php` `page_desc` — SEO meta description.
- File-header CSS comment in `admin.blade.php` — not user-visible.

---

## 19. Localization

Locales: `en` (default), `fr`, `ar` (RTL).

```
lang/
  en/  auth.php  landing.php  ui.php  validation.php
  fr/  auth.php  landing.php  ui.php  validation.php
  ar/  auth.php  landing.php  ui.php  validation.php
```

- Locale switching: `POST /locale` → `LocaleController@update` (session-stored).
- Switcher dropdown in landing nav and admin sidebar.
- RTL: `config('locales.supported')` drives `dir="rtl"` on `<html>`. Admin layout applies RTL class per current locale.

### Key namespaces

- `auth.login.*` — per-role login strings
- `auth.register.*` — registration + payment + checkout + OTP
- `auth.errors.*` — login errors
- `landing.*` — hero, features, pricing, how-it-works, FAQ, CTA, footer, `landing.chat_*` for hero mockup
- `ui.*` — shared admin panel labels
- `validation.*` — validation messages + attribute names

### Rule — `__()` never returns null

`__('ui.something')` returns the key string itself when missing. A `?? 'fallback'` in Blade never fires. Always add new keys to **both** `lang/en/ui.php` and `lang/fr/ui.php` immediately.

---

## 20. Operations

### Local dev

- Windows 11 + PowerShell primary shell. Bash tool available for POSIX scripts.
- Standard Laravel workflow: `php artisan serve` at `http://localhost:8000`.
- Two workers must be running to exercise the full flow:
  - `php artisan queue:work --queue=whatsapp,default`
  - `php artisan schedule:work`
- For WhatsApp inbound webhooks to reach `/conversations`, the gateway needs a public URL — use ngrok/Cloudflare Tunnel and set `WHATSAPP_WEBHOOK_BASE_URL`. Reconnect the instance after changing.
- Local `.env` has `BROADCAST_CONNECTION=log` intentionally — no Reverb WS locally.

### Production Laravel deploy

```bash
sudo -u www php artisan optimize:clear
sudo supervisorctl restart wavadesk:*   # REQUIRED — workers cache PHP classes in memory
```

### Production gateway deploy (⚠️ costs a QR re-scan)

Every restart of the CodeChat gateway costs a re-scan (session-persistence bug).
```bash
cd /www/wwwroot/public/xapi-prod-v1.wavadesk.com
npx tsc --noEmit                              # verify it compiles
# safe restart (bare node process):
kill $(pgrep -f 'node dist/src/main.js')
nohup node dist/src/main.js > /tmp/wa_api.log 2>&1 &
# recovery:
#   DELETE /instance/logout/{gatewayId}
#   restart node again
#   GET    /instance/connect/{gatewayId}  -> fresh QR
```
Then re-scan from the business phone: WhatsApp → Linked Devices → Link a device. The app re-creates the instance under a new ID after each scan.

### "No reply" diagnosis order

1. Gateway up? `ss -ltn | grep 8084`; `GET /instance/fetchInstance/{id}` should be 200.
2. Instance connected? `GET /instance/connectionState/{id}` should be `{"state":"open"}`. Stored `connectionStatus` can lie — trust live state.
3. Messages arriving? `psql ... -c 'SELECT ... FROM "Message" WHERE "keyFromMe"=false ORDER BY id DESC LIMIT 5;'`
4. Bot ran / errored? `grep ReservationBot storage/logs/laravel.log`.
5. Wedged reservation session? `Cache::forget("reservation_bot:4:<phone-or-lid>")`.

---

## 21. Working rules (feedback captured in memory)

### Commit and push after each task

After finishing any task on this project, commit and push to `origin/main` without waiting for confirmation. Treat commit + push as part of "task done."

- `git add` specific files (not `-A`), `git commit`, `git push`.
- Conventional-commit-style message summarizing the *why*.
- Include `Co-Authored-By: Claude` trailer.
- Group related edits from the same task into ONE commit.
- Still ask before destructive ops (`reset --hard`, `push --force`, etc.).
- Don't commit `.claude/settings.local.json` unless explicitly requested.

### Never use `sed` for PHP imports

Use Read + Edit. `sed` replaces patterns independently and has no awareness that both the `use` line and the `implements` clause must change together — caused "Interface ShouldBroadcastNow not found" in production once.

### `__()` returns the key, not null

See §19. Always add keys to both `lang/en/ui.php` and `lang/fr/ui.php` at the moment you introduce them.

### HTML dataset boolean check

Use `'flag' in element.dataset`, not `element.dataset.flag`. A valueless attribute (`data-no-loading`) gives `dataset.noLoading === ""`, and `if ("")` is falsy — the check silently fails. Also: don't set `button.disabled = true` inside a click handler before the form submission completes.

---

## 22. Recent commit trail (main)

Top-of-tree (2026-06-23 era):
- `b067814` — ui(reservations/slots): polish period picker — chip + corner check badge
- `48c7cf7` — ui(reservations/slots): visible checkbox indicator in Add Slot period picker
- `77fd5e1` — feat(users/create): inline Create Team modal with nested Create Agent
- `fb8533c` — feat(instances): add Validate button to QR modal
- `79de36f` — feat(instances): auto-hide QR and show new status on successful connect
- `6ac68fe` — instances: move Delete/Disconnect from row actions to edit view
- `79f40e2` — instances: remove gateway selector + webhook URL info card
- `033a2ec` — gateway: PATCH readMessages, connection-state fixes for QR/status.instance
- `9b9c1e3` — (gateway repo, local only) render native-flow interactive messages via `additionalNodes`

---

## 23. Additional context files

Living documents in the repo that this handbook consolidates from — go to them for the raw context:

- `CLAUDE.md` — code-review-graph tool guidance + four session handoffs (May 20–21, 2026).
- `AI_AUTOREPLY_FIXES_2026-06-01.md` — AI SDK / eligibility / `reply_when_claimed` / `@lid` masking narrative.
- `SESSION_2026-05-25_PRO_LIVE_CHAT.md` (+ `docs/` mirror) — Intercom-style conversation workspace rewrite.
- `docs/RESERVATION_BOT_HANDOFF.md` (2026-06-10) — current state and TODOs for reservation bot.
- `docs/RESERVATION_BOT_INTERACTIVE_FIX.md` — root cause & gateway `additionalNodes` fix in detail.
- `AGENTS.md`, `GEMINI.md` — mirrors of the code-review-graph header.

`README.md` is the default Laravel README, unchanged.
