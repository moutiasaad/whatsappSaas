# AI Auto-Reply Fixes & Customer Phone Masking — 2026-06-01

This session fixed the AI auto-reply pipeline end-to-end, added a setting to let
the AI reply on claimed conversations, and stopped WhatsApp `@lid` device IDs from
leaking into the UI as fake phone numbers.

---

## 1. AI auto-reply never sent (SDK call was wrong)

### Symptom
Every inbound message logged:

```
local.ERROR: Call to undefined method Anthropic\Client::messages()
  at app/Services/AI/AutoReplyService.php:51
```

The error was swallowed by the generic `catch`, so the AI silently never replied.

### Cause
The code targeted an older SDK shape. The installed `anthropic-ai/sdk` exposes
`messages` as a **property** (not a method) and `create()` takes **named
arguments**, not an associative array.

### Fix — `app/Services/AI/AutoReplyService.php`
- `new \Anthropic\Client(apiKey: $apiKey)`
- `$client->messages->create(maxTokens: …, messages: …, model: …, system: …)`
- Text extraction now concatenates all `text` content blocks instead of blindly
  reading `content[0]->text`.

Token accounting (`usage->inputTokens` / `outputTokens`) was already correct.

---

## 2. AI didn't reply on old / claimed conversations

### Symptom
Opening an old conversation, sending a message, and getting a customer reply did
not trigger the AI — even after activating AI on that conversation and in settings.

### Cause
The eligibility gate required the conversation to be in the `pool` state:

```php
public function isAiEligible(): bool { return $this->state === 'pool' && !$this->ai_suspended; }
```

Old conversations are `claimed` (claiming also sets `ai_suspended = true`), so AI
was never eligible, regardless of the per-conversation toggle.

### Fix — `app/Models/Conversation.php`
Eligibility is now driven by the two switches the admin actually controls:

```php
public function isAiEligible(): bool { return !$this->isClosed() && !$this->ai_suspended; }
```

| Situation | `ai_suspended` | AI replies? |
|---|---|---|
| Pool, untouched | false | ✅ |
| Agent claims it | true (auto) | ❌ (agent handling it) |
| Admin re-activates AI on it | false | ✅ even though claimed |
| Admin suspends AI on it | true | ❌ |
| Global settings `mode = off` | — | ❌ |
| Conversation closed | — | ❌ |

---

## 3. New setting: "Reply on claimed conversations"

A global per-tenant AI setting controls whether claiming a conversation keeps the
AI active.

| Setting | On claim / reassign | Result |
|---|---|---|
| **OFF** (default) | AI suspended | Human agent takes over (original behavior) |
| **ON** | AI stays active | AI keeps auto-replying on claimed conversations |

### Files
- `database/migrations/2026_06_01_130000_add_reply_when_claimed_to_ai_settings_table.php`
  — new `reply_when_claimed` boolean column (default `false`).
- `app/Models/AiSettings.php` — added to `$fillable` + boolean cast.
- `app/Http/Controllers/Admin/AiSettingsController.php` — validates and saves the toggle.
- `app/Services/Conversations/ConversationService.php` — `claim()` and `reassign()`
  only suspend AI when the setting is off; added `aiRepliesWhenClaimed()` helper.
- `resources/views/admin/ai-settings/index.blade.php` — the toggle UI.
- `lang/en/ui.php`, `lang/fr/ui.php` — labels + hints (Arabic falls back to English).

> Note: the setting applies to **future** claims/reassigns. Already-claimed
> conversations keep AI suspended — flip the per-conversation AI toggle to
> re-activate those.

---

## 4. AI replies stayed `pending` (not a bug — wrong mode)

### Symptom
The AI generated a reply, it appeared in the conversation thread, but stayed
`pending` and never reached the customer.

### Cause
The tenant's AI mode was **Suggestion** (*"AI drafts, agent sends"*). In this mode
the reply is created as a draft (`status: pending`, `is_suggestion: true`) and is
intentionally not delivered. There is no "Approve & Send" action in the UI yet.

### Resolution
Switched the tenant to **Autonomous** mode (*"AI replies automatically"*), which
dispatches `SendOutgoingMessage` and delivers directly to the customer.

The AI mode is changeable per-tenant in **AI Settings → AI Mode**
(Off / Suggestion / Autonomous / Hybrid).

---

## 5. WhatsApp `@lid` leaked into the UI as a fake phone number

### Symptom
A "phone number" (e.g. `27810383020169@lid` / its digits) showed under the
customer name, but it was not the customer's real number.

### Cause
WhatsApp now hides real numbers behind **`@lid`** privacy device IDs. The
`Customer::displayPhone` accessor already masks `@lid`, and the detailed
`workspace` endpoint used it — but the conversation **list / index** endpoints
returned the raw Eloquent model, serializing the raw `phone_e164` (the `@lid`).
The frontend list/sidebar then displayed it.

### Fix — `app/Models/Customer.php`
Added a `toArray()` override so **every** JSON/array serialization returns the
masked `displayPhone` for `phone_e164` (empty for `@lid`, `+digits` for a real
number). The raw column remains available server-side (`$customer->phone_e164`)
for gateway calls and search.

### Limitation
The **real phone number cannot be displayed** for LID chats — this gateway
(`api.whatstshl.online`) doesn't expose it:
- `findContacts` returns the same `@lid` (not `@s.whatsapp.net`)
- the inbound webhook has no real-number field (`senderPn`)

So `resolveLidToPhone()` can't succeed for LID contacts. Real numbers only show
for contacts that message without LID privacy (they arrive as `@s.whatsapp.net`
and already display with the `+` format).

---

## Operational notes
- Restart the queue worker after deploys: `php artisan queue:restart`
  (the AI reply runs inside the queued `ProcessIncomingMessage` job).
- Ensure a worker is running: `php artisan queue:work --queue=whatsapp,default`.
- If PHP-FPM uses OPcache, reload it so model/service changes take effect on web requests.
