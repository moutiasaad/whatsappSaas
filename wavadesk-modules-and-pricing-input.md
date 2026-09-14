# wavadesk — Module Inventory & Pricing Input

> Purpose: hand this file to Claude (fresh session) and ask it to produce a pricing / packaging plan
> and an ads plan. Everything below was read directly out of the codebase on **2026-09-09**, not from
> marketing copy. Where a feature exists but is **not enforced**, it says so — do not build a price
> tier on an unenforced limit.

---

## 1. What the product is

**wavadesk** — a multi-tenant SaaS customer-support desk built on WhatsApp, plus a website live-chat
widget. A business (a "tenant") connects its WhatsApp Business number by scanning a QR code; incoming
messages land in a shared pool where agents claim, reply and close them. An AI assistant (Anthropic
Claude) can answer automatically from the tenant's own knowledge base, and hands off to a human on
escalation keywords.

| Concern | Value |
|---|---|
| Domain | https://wavadesk.com |
| Stack | Laravel 12 · MySQL · Blade + Alpine + Tailwind · queue/cache/session on `database` |
| Real-time | Laravel Reverb (Pusher protocol) over `wss://` |
| WhatsApp | Self-hosted CodeChat gateway (Baileys) on our own droplet — **not** the Meta Cloud API |
| AI | Anthropic Claude via `anthropic-ai/sdk` |
| Payments | Stripe Checkout **and** PayPal (both wired), currency **USD** |
| Languages | English, French, Arabic (Arabic full RTL) |
| Roles | Super Admin (platform operator) · Admin (tenant owner) · Supervisor · Agent |

Target buyer: small and mid-size support teams, e-commerce operations and agencies already doing
customer service over WhatsApp on personal phones — no queue, no history, no oversight.

Notable cost/positioning fact: because the WhatsApp gateway is **self-hosted Baileys, not the Meta
Cloud API**, there is **no per-conversation Meta fee** to pass through. Margin is driven by server
capacity (one live WhatsApp session per tenant is a persistent Node process) and Claude token spend.

---

## 2. The module catalogue (what a plan can switch on/off)

These are the exact 11 entitlements defined in `config/plan_modules.php`. The super admin ticks them
per plan; the `module:` middleware (`app/Http/Middleware/CheckPlanModule.php`) enforces them server-side
on every route, not just by hiding sidebar links. A plan row with `modules = NULL` predates this list
and grants everything.

| # | Key | Name | Group | What the tenant gets | Notes for pricing |
|---|---|---|---|---|---|
| 1 | `whatsapp` | **WhatsApp Desk** | channels | QR-connect a WhatsApp Business number, shared inbox, pool → claim → reply → close, conversation history, customer records | **Always on — cannot be switched off.** This is the product. |
| 2 | `webchat` | **Website Live Chat** | channels | Embeddable JS widget (`public/webchat/widget.js`), per-tenant theming (colour, position, launcher icon/text, bubble style, branding toggle), welcome message, suggested questions, pre-chat email capture, offline message, topics, multi-language, domain allowlist (fail-closed) | Second channel. Natural first upsell — same inbox, no extra WhatsApp session cost. |
| 3 | `ai_agent` | **AI Auto-Reply** | automation | Claude answers from the knowledge base. 4 modes: `off` / `suggestion` (drafts only) / `autonomous` (sends) / `hybrid` (per-conversation). Per-channel toggles (WhatsApp / webchat), reply language, custom system prompt, escalation keywords (default: manager, refund, complaint, lawsuit), `reply_when_claimed` toggle, monthly token quota | **The only module with real marginal cost.** Quota is enforced (`AiSettings::hasQuota()`); plan supplies `ai_token_quota`, seeded into the tenant. Meter this. |
| 4 | `knowledge_base` | **Knowledge Base** | automation | Per-tenant entries of type `faq` / `info` / `product`, JSON bulk import + template, used to build the AI system prompt | Sold with AI; near-zero marginal cost. |
| 5 | `saved_replies` | **Saved Replies** | automation | Canned responses with shortcuts, tenant-wide or personal scope, ordered | Cheap; good "team productivity" tier filler. |
| 6 | `reservations` | **Reservation Bot** | modules | Full WhatsApp appointment booking: tappable list/button flow (date → period → slot → name → notes → confirm), availability-slot manager with bulk generation across a date range, per-tenant trigger keywords (`حجز`, `book`, `booking`, `réservation`), cancel keywords, reservation status management | A vertical product on its own (clinics, salons, garages, restaurants). Strong candidate for an add-on price rather than a tier. |
| 7 | `otp_service` | **OTP over WhatsApp** | modules | API service to send one-time codes over the tenant's own WhatsApp number: configurable code length (4/6/8), TTL 1–60 min, message template with `{code}`, integration snippets | Developer/API buyer. Volume-priced naturally. Competes with SMS OTP on cost — a real wedge. |
| 8 | `teams` | **Teams & Routing** | workspace | Teams, membership, per-team WhatsApp instance assignment, team-scoped conversation pools, supervisor oversight | Gate for the "multi-agent" story. |
| 9 | `reports` | **Reports & Analytics** | insights | KPIs (total/closed conversations, resolution rate, avg first-response time, avg resolution time, inbound/outbound counts), team leaderboard, agent leaderboard, daily volume, hourly heatmap, state breakdown, AI-vs-agent split, date-range + timezone aware | Classic higher-tier feature. |
| 10 | `audit_log` | **Audit Log** | insights | Every action recorded with actor, timestamp, target; impersonation logged separately | Business/compliance tier. |
| 11 | `api_access` | **External API** | insights | Static `X-Api-Key` (`wvd_` + 48 chars, regenerated from Profile). Endpoints: `POST /api/send` (direct send to any number), `GET /api/instance`, `GET /api/instance/status`, `POST /api/instance/connect`, `POST /api/instance/disconnect`, `POST /api/conversations/start` (outbound conversation). Postman collection + `public/docs/api.html` shipped | Developer tier / agency resell. Already used by a live companion project. |

---

## 3. Always-included core (every plan, not gated)

Not sellable as tiers, but this is what "the base product" actually contains — useful for the value
story in ads:

- **Unified inbox** — WhatsApp and webchat threads in one list, tabs, counts, search, live thread view.
- **Conversations** — pool / claimed / closed states, atomic claim (two agents can never take the same chat), reassign, close, per-conversation AI toggle, delivery status per message (pending/sent/delivered/read/failed).
- **Customers** — auto-created contact records, phone masking for WhatsApp `@lid` privacy IDs, per-customer conversation history and filters.
- **Archive** — searchable closed-conversation archive across both channels.
- **Dashboard** — tenant-scoped KPIs.
- **Real-time** — live queue updates, typing indicators, in-app notifications over Reverb WebSockets.
- **Roles & permissions** — 4 roles, each with its own panel and route prefix; middleware-enforced.
- **Impersonation** — admin can log in as one of their users (logged).
- **Notifications** — in-app notification centre; admins can push notifications to their users.
- **Profile & security** — password change, email-change with verification, API key regeneration.
- **Localization** — EN / FR / AR with full RTL.
- **Legal pages** — DB-backed terms/privacy per locale.
- **Billing self-service** — plan view, payment history, invoices/receipts list, upgrade flow.

---

## 4. Platform (operator) side — not sold, but shapes what we can sell

Super-admin control plane with its own 10 permission slugs (`config/super_admin_permissions.php`):
platform tenants, plans, system health, legal pages, all-tenant conversations, all-tenant customers,
reports, audit log, billing, notifications. Super admins can be created with a restricted subset.

**Plans are fully editable from the UI** — name, monthly price, annual price, user limit, instance
limit, conversation limit, AI included, AI token quota, trial on/off + length, the 11 module ticks,
and a manual pick of which attributes the public pricing card advertises
(`config/plan_landing_attributes.php`: trial, max_users, max_instances, max_conversations, ai_quota,
plus any granted module). **So any pricing structure proposed can be implemented without code changes,
as long as it only uses the levers in §5.**

---

## 5. Pricing levers — what is actually enforced today

This is the most important table in the file. Do not price on a lever marked ❌.

| Lever | Column | Enforced? | Detail |
|---|---|---|---|
| Module entitlements (all 11) | `plans.modules` | ✅ | Middleware-enforced on every route, 403/redirect if not in plan |
| Seat / user limit | `plans.max_users` | ✅ | `TenantQuota::assertCanCreateUser()`; default 5 if unset |
| AI token quota | `plans.ai_token_quota` | ✅ | Seeded into `ai_settings.monthly_token_quota`; AI stops when exhausted. `NULL` = unlimited, `0` = blocked |
| Free trial (per plan) | `trial_enabled`, `trial_days` | ✅ | Expired trials are blocked by `CheckSubscription`; `tenants.trialed_plan_ids` stops re-trialing the same plan |
| Monthly price | `price_monthly` | ✅ | Stripe + PayPal checkout both use it |
| WhatsApp numbers / instances | `plans.max_instances` | ❌ | **Hardcoded to 1 per tenant regardless of plan** (`TenantQuota::INSTANCE_LIMIT = 1`). Column is stored and validated but deliberately not read. Multi-number is a build, not a config change |
| Conversations per month | `max_conversations_per_month` | ❌ | Stored and shown, never checked anywhere in the app |
| Annual billing | `price_annual` | ⚠️ | Landing page has the monthly/annual toggle and shows the annual price, but **checkout always charges `price_monthly`**. Annual is not wired end-to-end |

**Consequences for the pricing plan:**
1. Tiering must be built on **modules + seats + AI tokens**, which all work today.
2. "More WhatsApp numbers on higher tiers" cannot be sold yet — either scope the build or leave it out.
3. "Save 20% annually" cannot be sold yet — either scope the build or leave it out.
4. Conversation-volume tiers are marketing-only until enforcement is added.

---

## 6. Plans currently in the database (placeholders, to be replaced)

| ID | Name | Monthly | Annual | Users | Instances | Conv/mo | AI tokens | Trial | Modules |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Starter | $0 | $0 | 5 | 2 | 997 | 100,000 | 7 d | all 11 |
| 2 | Growth | $79 | $790 | 20 | 5 | 5,000 | 1 | 7 d | all 11 |
| 3 | test | $150 | $160 | 10 | 190 | 5,000 | — | 7 d | all 11 |

These are test rows, not a considered price list. Every plan currently grants every module, which
means **there is no packaging yet** — that is what needs designing.

---

## 7. Cost drivers (for margin work)

| Driver | Shape | Notes |
|---|---|---|
| Claude tokens | Per AI reply, variable | The only true usage cost. Quota already meters it. Knowledge-base size inflates the system prompt on every call |
| WhatsApp session | Per connected number, persistent | Self-hosted Baileys process on our droplet — RAM/CPU, not a per-message fee. Reconnects need a QR re-scan |
| Reverb WebSockets | Per concurrent agent | Own server process |
| Queue workers | Per message throughput | `database` queue driver |
| DB + storage | Per conversation/message retained | Grows with history retention |
| Payment fees | Stripe / PayPal % | USD |
| Meta / WhatsApp fees | **None** | Not on the Cloud API — a genuine cost advantage over Cloud-API-based competitors |

---

## 8. Known gaps to respect in ads copy

Verified against the audit of 2026-09-07 and re-checked in code on 2026-09-09:

- ✅ **Fixed since the audit:** the free trial now works per plan, and module entitlements are enforced.
- ❌ **Still true:** one WhatsApp number per tenant, regardless of plan → do not advertise "unlimited
  numbers" or "multi-instance".
- ❌ **Still true:** annual billing is not charged → do not advertise "save X% yearly".
- ❌ **Still true:** conversation-per-month limits are not enforced → do not build tier-comparison
  creative around ":n conversations/month".
- ⚠️ AI `suggestion` mode drafts replies but there is **no approve-and-send UI yet** — the draft is
  stored, not surfaced as a one-click send.
- ⚠️ Reservation bot is Arabic-first by default; multilingual bot copy is a pending TODO.
- ⚠️ Not endorsed by Meta/WhatsApp — creative must not read as an official WhatsApp product, and must
  avoid Meta's exact green `#25D366`.

Brand palette: teal `#0f7e7a` / `#15b6a8` / `#0a5e5b` (logo), neutrals `#0f172a`, `#64748b`, `#e2e8f0`,
`#f8fafc`, dark `#0d1117`. Type: Outfit (Latin), Cairo (Arabic). Tagline in use: *"AI-powered WhatsApp
customer support."* A fuller creative brief already exists at `wavadesk-ad-brief.md`.

---

## 9. What I want back

Using only the levers in §5 and respecting §8:

1. **A packaging & pricing plan** — 3 to 4 tiers, with the exact module ticks per tier, seat limits,
   AI token quotas, trial length and monthly USD price. Say which module belongs in which tier and why.
   Call out anything better sold as a per-tenant add-on (Reservations and OTP are candidates).
2. **Price positioning** — benchmark against comparable WhatsApp/shared-inbox tools, and justify the
   entry price given there is no Meta per-conversation fee to pass on.
3. **Which unenforced lever to build first** if it materially improves the pricing story
   (multi-number vs annual billing vs conversation metering) — one recommendation, with the reasoning.
4. **An ads plan to launch** — channels, budget split, audiences, the offer/CTA per tier, and 3 to 5
   creative angles tied to modules that actually ship. Markets are English, French and Arabic
   (Arabic RTL); the product is priced in USD.
