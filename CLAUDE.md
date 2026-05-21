<!-- code-review-graph MCP tools -->
## MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

### Key Tools

| Tool | Use when |
|------|----------|
| `detect_changes` | Reviewing code changes — gives risk-scored analysis |
| `get_review_context` | Need source snippets for review — token-efficient |
| `get_impact_radius` | Understanding blast radius of a change |
| `get_affected_flows` | Finding which execution paths are impacted |
| `query_graph` | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes` | Finding functions/classes by name or keyword |
| `get_architecture_overview` | Understanding high-level codebase structure |
| `refactor_tool` | Planning renames, finding dead code |

### Workflow

1. The graph auto-updates on file changes (via hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.

---

## Session Handoff (May 20, 2026)

### What was implemented

- Role system and panel separation work was requested and iterated across:
  - `Super Admin`
  - `Admin (Tenant Admin)`
  - `Supervisor`
  - `Support Agent`
- Login/panel behavior was adjusted during the session so:
  - Super Admin has dedicated login route: `/superadmin/login`
  - Other roles have their own route/panel behavior
- Sidebar role descriptions were requested and added per role intent:
  - Super Admin: platform/tenants/plans/global/billing/system health
  - Admin: tenant users/teams/instances/AI/knowledge base/impersonation
  - Supervisor: team conversation oversight/reassign/release
  - Support Agent: claim/reply/close frontline conversations
- Permissions were updated to match the provided matrix (pool, claim, reply, reassign, close, instances, users/teams, AI config, impersonation, tenant/billing, audit log).
- 419 login issue was addressed during the previous steps.
- Super Admin tenant index page UI was adjusted per request (filter + top-right add action style).
- Add Tenant action was adjusted to navigate to a dedicated create page.

### Latest completed task (models aligned to schema screenshots)

Two screenshots were used as schema source of truth:

- `C:\Users\mouti\OneDrive\Images\Screenshots\Capture d'écran 2026-05-20 152414.png`
- `C:\Users\mouti\OneDrive\Images\Screenshots\Capture d'écran 2026-05-20 152419.png`

Model updates made for key columns/casts/relations:

- `app/Models/Conversation.php`
- `app/Models/ConversationEvent.php`
- `app/Models/Customer.php`
- `app/Models/ImpersonationLog.php`
- `app/Models/KnowledgeEntry.php`
- `app/Models/Message.php`
- `app/Models/Plan.php`
- `app/Models/Team.php`
- `app/Models/Tenant.php`
- `app/Models/User.php`
- `app/Models/WebhookEvent.php`
- `app/Models/WhatsAppInstance.php`

Key alignment done:

- Added integer casts for FK/counter fields.
- Kept/added array, boolean, decimal, datetime casts where needed.
- Added `team_id` support to `WhatsAppInstance` fillable + `team()` relation.
- Added `instances()` relation on `Team`.

Validation performed:

- `php -l` passed for all changed model files (no syntax errors).

### Known gap / next step

- Screenshot included a `subscriptions` table concept, but project currently has no `Subscription` model/migration.
- If full parity is required, create:
  - migration for `subscriptions`
  - `Subscription` model
  - relations with `Tenant` and `Plan`

---

## Session Handoff (May 20, 2026 - UI_PATTERNS rollout continuation)

### Scope completed in this phase

- Applied `UI_PATTERNS.md` progressively to major admin/super-admin/supervisor pages requested in chat:
  - `/super-admin/platform/tenants*`
  - `/super-admin/platform/plans*`
  - `/super-admin/platform/global-settings*`
  - `/super-admin/platform/system-health`
  - `/super-admin/conversations*`
  - `/super-admin/customers*`
  - `/supervisor/teams`

### Notable backend updates

- `app/Http/Controllers/Admin/SuperAdminPlatformController.php`
  - Added/expanded show/edit/update/toggle flows for tenants/plans/global settings and disable/guard logic.
- `app/Http/Controllers/Admin/ConversationWebController.php`
  - Super-admin control/filter context for conversations.
- `app/Http/Controllers/Api/ConversationController.php`
  - Conversation list/filter behavior aligned with updated UI controls.
- `app/Http/Controllers/Admin/CustomerController.php`
  - Added richer filters (search, tenant, instance, activity/date, sort), KPI stats, and detailed customer-conversation filtering on show page.
- `app/Http/Controllers/Admin/TeamController.php`
  - Added supervisor-scoped team listing, filtering/sorting/pagination/stats for index.
  - Added supervisor access guard for editing/updating teams (`ensureTeamAccess`).

### Notable route/permission updates

- `routes/web.php`
  - Added supervisor route access for teams management:
    - `supervisor.teams.index`
    - `supervisor.teams.edit`
    - `supervisor.teams.update`
  - Kept admin/super-admin full team CRUD under management routes.

### View/UI updates (patterned)

- `resources/views/layouts/admin.blade.php`
  - Sidebar role text/entries updated; supervisor now has dedicated Teams nav item.
- `resources/views/admin/customers/index.blade.php`
- `resources/views/admin/customers/show.blade.php`
- `resources/views/admin/conversations/index.blade.php`
- `resources/views/admin/conversations/show.blade.php`
- `resources/views/admin/teams/index.blade.php`
- `resources/views/admin/teams/edit.blade.php`
- `resources/views/admin/platform/*.blade.php` (tenants/plans/global/system health pages and supporting edit/show views)

### Data/config additions created in this phase

- `app/Models/PlatformSetting.php`
- `database/migrations/2026_05_20_000014_create_platform_settings_table.php`
- New platform UI partials and edit/show pages under:
  - `resources/views/admin/platform/`

### Current behavior for Supervisor Teams

- `/supervisor/teams` now uses patterned UI (page header, stat cards, toolbar filters, data table, badges, action buttons, pagination).
- Supervisor can open **Manage** on teams they belong to and submit updates.
- Supervisor cannot delete teams or manage instances (admin/super-admin only in UI and routes).

### Important note about tooling

- `code-review-graph` MCP calls repeatedly timed out in this environment during this phase.
- Fallback used: direct file inspection and patching via shell/apply_patch.

### Suggested first steps next session

1. Run full app smoke test in browser for:
   - `/super-admin/customers`
   - `/super-admin/conversations`
   - `/supervisor/teams`
2. Run route + view compile checks:
   - `php artisan route:list`
   - `php artisan optimize:clear`
   - `php artisan view:cache` (if environment temp file warning is resolved)
3. Normalize remaining hardcoded `admin.*` routes in older blades (especially team create/edit and other legacy pages) to `routeNamePrefix()` pattern where needed.

---

## Session Handoff (May 21, 2026 - WhatsApp gateway / instances / QR)

### WhatsApp gateway contract now used by this app

The current WhatsApp provider is the API exposed at:

- `https://api.whatstshl.online`

This is the iStoreBox WhatsApp API contract, not the older Evolution-only flow.

Relevant endpoints already wired in the app:

- `POST /instance/create`
- `GET /instance/connect/{instanceName}`
- `GET /instance/fetchInstance/{instanceName}`
- `PUT /webhook/set/{instanceName}`
- `POST /message/sendText/{instanceName}`
- `POST /message/sendMedia/{instanceName}`
- `DELETE /instance/delete/{instanceName}`

Auth:

- API key header: `apikey`

### QR / connect behavior

The QR flow was simplified to match the faster behavior observed in the Sultankoo reference project:

- `connect()` now only creates the gateway instance if needed and fetches the QR.
- Webhook registration is no longer done inside the QR connect path.
- `status()` now sets the webhook only after the gateway reports the instance as connected.
- The QR modal on the instances page no longer re-POSTs `/api/instances/{id}/connect` in a timer loop.
- If the instance already has a cached QR code and is still `connecting` / `qr_pending`, the connect endpoint returns the cached QR immediately.

### Instance status / phone number sync

Status refresh now does more than flip connected/disconnected:

- It fetches the full gateway instance payload.
- It maps gateway states like `open`, `online`, `connected` to local `connected`.
- It extracts the owner phone from gateway fields such as:
  - `ownerJid`
  - `number`
  - `me.jid`
  - `me.id`
- The extracted phone is stored in `whatsapp_instances.phone_number`.

This fixes the case where the UI showed:

- `Connectée`
- but still displayed `Aucun numéro`

### Instance deletion

The instances list now has a delete action in addition to logout/logout-like disconnect:

- `resources/views/admin/instances/index.blade.php`
  - delete icon added in the row actions
  - uses the shared delete modal
- `app/Http/Controllers/Admin/InstanceWebController.php`
  - `destroy()` now tries to delete the remote gateway instance first
  - then deletes the local `WhatsAppInstance` record

### Current operational expectations

For a local test to work end-to-end:

1. Create instance.
2. Click Connect once.
3. Scan the QR with the business/agent WhatsApp account.
4. Wait for status to become connected.
5. Click refresh status if needed so the phone number syncs.
6. Ensure `php artisan queue:work` is running.
7. Ensure `php artisan schedule:work` is running.
8. Use a second WhatsApp number as the customer for conversation tests.

### Files changed in this phase

- `app/Services/WhatsApp/Gateway/EvolutionApiClient.php`
- `app/Http/Controllers/Api/InstanceController.php`
- `app/Http/Controllers/Admin/InstanceWebController.php`
- `resources/views/admin/instances/index.blade.php`

### Notes

- The instances page should not keep hammering `/api/instances/{id}/connect`.
- If QR is still slow, the remaining bottleneck is the gateway response time itself, not the frontend retry loop.
- If the number still does not appear after connected, inspect one real `fetchInstance` JSON response and map the phone field explicitly if the gateway version differs.

---

## Session Handoff (May 21, 2026 - Localization sweep and users form updates)

### UI localization sweep completed

The French locale file was cleaned up and the app was expanded to use shared translation keys across the main admin and platform pages.

Covered pages / areas:

- `/tenant-admin`
- `/conversations`
- `/customers`
- `/knowledge`
- `/instances`
- `/users`
- `/teams`
- `/audit-log`
- `/ai-settings`
- `/billing`
- `/platform/global-settings`
- `/platform/system-health`
- `/super-admin/platform/tenants`
- `/super-admin/platform/plans`
- `/super-admin/platform/tenants/create`
- `/super-admin/platform/plans/create`
- `/super-admin/platform/tenants/edit`
- `/super-admin/platform/plans/edit`
- `/super-admin/users/create`
- `/super-admin/users/{id}/edit`

### Key localization fixes

- Fixed French mojibake / broken accent rendering in `lang/fr/ui.php` across:
  - users / teams forms
  - AI settings
  - audit log
  - platform tenants / plans / billing / global settings / system health
  - WhatsApp instance create/edit labels and checklist text
- Added/normalized validation field names in `lang/fr/validation.php` for form errors like `app_name`.
- Kept controller flash / validation messages routed through locale keys.

### Users create/edit form changes

- `/tenant-admin/users/create`
  - `Affecter à des équipes` was moved into the main `Informations du compte` card.
  - The old checkbox list was replaced with the shared Select2-style multi-select used elsewhere.
  - The temporary password helper text now uses translations.
- `/tenant-admin/users/{id}/edit`
  - Team membership uses the same Select2-style multi-select.
  - The impersonate action now uses the shared confirmation modal instead of the browser alert.

### Sidebar / navigation behavior

- Sidebar active state now respects the current route prefix (`admin`, `tenant_admin`, `super_admin`, `supervisor`, `agent`) so the correct item stays highlighted on deeper pages.
- The sidebar scrolls the active item into view and focuses it on load / navigation.
- Dark sidebar scrollbar styling was tightened to match the rest of the app.

### Instance create/edit changes

- `/tenant-admin/instances/create` and `/tenant-admin/instances/{id}/edit` now use translated French labels instead of broken text.
- Team assignment on instances uses the shared Select2-style dropdown and can render above the card when space is tight.
- Instance status labels on the index page are translated dynamically.
- Instance delete action was added to the index rows.

### WhatsApp test limitation discovered

- The WhatsApp gateway is returning `connected`, but inbound messages do not reach `/conversations` if the webhook points to `127.0.0.1`.
- The gateway needs a public webhook base URL, not localhost.
- The app now supports configuring a public webhook base URL via `WHATSAPP_WEBHOOK_BASE_URL`.
- For local testing, use a tunnel such as ngrok or Cloudflare Tunnel and reconnect the instance after changing the webhook base URL.

### Current known status on instances

- `Connectée` + `Aucun numéro` was fixed by syncing `ownerJid` / `number` / `me.jid` from the gateway payload.
- QR generation was simplified to avoid repeated polling loops.
- Remote gateway deletion and local deletion are both wired for old instances.

### Validation already performed

- `php -l` passed on the touched Blade views and locale files.
- The new create view for `/tenant-admin/users/create` parses correctly after the layout change.
