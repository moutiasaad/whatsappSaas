# Session Handoff — 2026-05-25

## Pro live-chat workspace for `/tenant-admin/conversations/{id}`

This session reworked the conversation show page into a full Intercom-style
support desk, with controller hardening, server-side data preload, saved
replies, agent presence, AJAX conversation switching, full i18n, and a
redesigned file-upload experience.

---

## What was implemented

### 1. Controller hardening + preload

`app/Http/Controllers/Admin/ConversationWebController.php`

- `show()` now preloads first 40 messages, recent events, assignable agents
  (admin/supervisor/agent of the conversation's team or whole tenant),
  saved-reply set, online agent roster, customer conversation count, AI
  mode — all into a single render so the view needs **zero** initial
  fetches.
- `loadConversationList()` mirrors the API index scoping:
  - agent / supervisor → team conversations + null-team within tenant
  - admin → whole tenant
  - super_admin → the viewed conversation's tenant
- Default starter saved replies are auto-seeded on first access per tenant
  (Greeting / Hold on / Resolved / Follow up / Closing).
- A flat `$i18n` bag is exposed to the Blade view via `chatI18n()` so the
  Alpine script can read `this.i18n.xxx` without per-call `@js(__())`
  boilerplate.

### 2. Saved replies (canned responses)

- `database/migrations/2026_05_25_000001_create_saved_replies_table.php`
  - Columns: `tenant_id`, `owner_user_id`, `scope` (`tenant`/`personal`),
    `title`, `shortcut`, `body`, `sort_order`.
- `app/Models/SavedReply.php` (`BelongsToTenant`, `visibleTo($user)` scope).
- `app/Http/Controllers/Api/SavedReplyController.php`
  - `index` returns visible replies, auto-seeding defaults if none exist.
  - `store` / `update` / `destroy` with scope-aware authorization
    (personal replies = owner only; tenant replies = admin/supervisor).
- Routes registered under `/api/saved-replies`.

### 3. Agent presence + online roster

- `app/Http/Controllers/Api/AgentPresenceController.php`
  - `POST /api/agents/heartbeat` — writes `agent_presence:{id}` cache entry
    with a 90 s TTL.
  - `GET /api/agents/online` — returns all agents for the actor's tenant
    (super_admin: all tenants) with an `online` boolean computed from the
    presence cache.
- Frontend fires heartbeat on init, every 30 s, and on tab focus.
- Right rail renders a "Team online" card with green/grey dots, with the
  current agent highlighted.

### 4. Agent typing broadcast

- `app/Events/AgentTyping.php` — `ShouldBroadcastNow` on
  `tenant.{tenantId}.conversation.{id}` as `agent.typing`.
- `ConversationController::updatePresence()` now broadcasts
  `AgentTyping->toOthers()` before forwarding the presence to the gateway,
  so peer agents on the same conversation see "Alice is typing…".

### 5. AJAX conversation switching (no page reload)

- New endpoint `GET /api/conversations/{conversation}/workspace` returns
  conversation + customer + instance + team + tenant + owner + first-page
  messages + events + assignable agents + AI mode in **one** call.
- Left-rail rows are now `@click.prevent="switchTo(c.id)"` — they fetch the
  workspace bundle, `history.pushState` to the new URL, re-subscribe the
  Echo channel, mark read, refresh the scroll anchor.
- `popstate` wires browser back/forward.
- Header, profile card, meta rows, timeline, reassign-select are all
  Alpine-reactive (no Blade re-render needed).
- Customer avatars (row + header + profile card) show `<img>` when
  `profile_pic_url` is set, fall back to initials otherwise.

### 6. Full translations (en / fr / ar)

`lang/{en,fr,ar}/ui.php` under `conversation_show_page` got ~50 new keys:

- `search_placeholder`, `search_replies_placeholder`, `caption_placeholder`
- `typing`, `agent_typing` (with `:name` placeholder), `reconnecting`
- `team_online`, `browse_replies`, `no_replies_match`, `more`
- `internal_note`, `ai_reply`, `document`, `clear`, `remove`
- `profile`, `whatsapp`, `call`
- `attach_file`, `attach_image`, `attach_document`, `attach_audio`
- `drop_to_send`, `drop_hint`, `uploading`, `upload_failed`,
  `file_too_large`
- `not_on_whatsapp`, `unassigned`, `system`, `no_conversations`
- `state_pool`, `state_claimed`, `state_closed`
- `release_title`/`_desc`, `close_title`/`_desc`, `reopen_title`/`_desc`
- `claim_success`, `claim_error`, `close_success`, `reopen_success`,
  `reassign_success`, `reassign_failed`
- `ai_updated`, `ai_update_failed`, `send_failed`, `network_error`,
  `load_messages_failed`, `load_workspace_failed`

The view no longer hardcodes any English in toasts, confirms, drag-drop
labels, button titles, or placeholders.

### 7. Pro file-upload redesign

`resources/views/admin/conversations/show.blade.php`

- Replaced the basic preview chip with a polished `cw-attach-card`:
  - Gradient corner stripe at the top (indigo → violet → pink).
  - Type-specific gradient thumb: image (green), video (red), audio
    (amber), document (indigo).
  - File-size displayed alongside the type chip.
  - Shimmering animated progress bar (`cw-shimmer` keyframes).
- Attach button is now `cw-attach-group` with a hover-revealed fly-out
  offering image / document / audio shortcuts (each opens a typed file
  picker).
- Drag-and-drop overlay over the composer with bouncing cloud icon plus
  translated "drop_to_send" + "drop_hint" copy.
- 25 MB client-side guard, with translated `file_too_large` toast.

---

## Files added

```
app/Events/AgentTyping.php
app/Http/Controllers/Api/AgentPresenceController.php
app/Http/Controllers/Api/SavedReplyController.php
app/Models/SavedReply.php
database/migrations/2026_05_25_000001_create_saved_replies_table.php
docs/SESSION_2026-05-25_PRO_LIVE_CHAT.md
```

## Files modified

```
app/Http/Controllers/Admin/ConversationWebController.php
app/Http/Controllers/Api/ConversationController.php
app/Models/Conversation.php
lang/ar/ui.php
lang/en/ui.php
lang/fr/ui.php
resources/views/admin/conversations/show.blade.php
routes/api.php
```

## New API routes

```
GET    /api/conversations/{conversation}/workspace
GET    /api/saved-replies
POST   /api/saved-replies
PUT    /api/saved-replies/{savedReply}
DELETE /api/saved-replies/{savedReply}
POST   /api/agents/heartbeat
GET    /api/agents/online
```

## New broadcast event

```
agent.typing  →  tenant.{tenantId}.conversation.{id}
```

---

## Validation performed

- `php -l` clean on every touched/added PHP file.
- `php artisan migrate --force` ran the saved_replies migration.
- `php artisan view:cache` recompiles cleanly.
- `php artisan route:list` confirms new endpoints registered.
- All three locale files parse with 101 keys each under
  `conversation_show_page`.
- Brace count in the extracted Alpine script: 277/277 balanced.

---

## Suggested first steps next session

1. Manual smoke test of `/tenant-admin/conversations/{id}`:
   - Click between conversations in the left rail — should be instant, no
     page reload, URL should update via `pushState`.
   - Browser back/forward should restore the right conversation.
   - Press `/` in the chat to open the saved-replies picker.
   - Try drag-and-drop of an image onto the composer.
2. Make sure `php artisan queue:work` and `php artisan reverb:start` (or
   the configured broadcaster) are running so the AgentTyping event
   actually reaches peers.
3. If a tenant admin wants to manage shared canned replies, add a small
   CRUD page under `/tenant-admin/saved-replies` that talks to the API.
   The backend is ready.
4. Consider extending the workspace endpoint to also return permissions
   computed by `ConversationPolicy` instead of the client-side `perms`
   getter — the policy is the source of truth, the client-side check is
   currently best-effort.
