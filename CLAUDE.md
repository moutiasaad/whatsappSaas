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
