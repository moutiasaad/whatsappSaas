# wavadesk — Ad Creative Brief

> Source of truth: colors from `resources/views/landing.blade.php`, copy from `lang/en/landing.php`,
> logo geometry from `public/images/`. Claims cross-checked against the technical audit of 2026-09-07.

---

## 1. Product

**wavadesk** (always lowercase) — a multi-tenant SaaS platform that turns WhatsApp into a proper
customer-support desk. Businesses connect their WhatsApp Business numbers by QR scan; incoming
messages land in a shared team pool where agents claim, reply and close them, with an AI assistant
that auto-answers from the company's own knowledge base.

- **Tagline (from the footer):** "AI-powered WhatsApp customer support."
- **Meta description:** "SaaS platform for WhatsApp customer support with AI Support, multi-team routing and analytics."

**Audience:** small and mid-size support teams, e-commerce operations and agencies already doing
customer service over WhatsApp on personal phones, with no queue, no history and no oversight.

**Core pain it removes:** one phone, one person, no record. Messages get missed, two agents answer
the same customer, and nobody can see what was said.

---

## 2. Brand assets

### Logo

`public/images/wavadesk-icon.svg` — a rounded square (16/64 corner radius) containing a single
continuous sine wave drawn in a 5.5pt round-cap stroke, with a filled dot terminating each end:
low-left in deep teal, high-right in bright teal. The "wave" is the name.

Variants on disk:

| File | Description |
|---|---|
| `wavadesk-icon.svg` | White fill, teal stroke — primary |
| `wavadesk-icon-gradient.svg` | Teal gradient fill, white wave |
| `wavadesk-icon-dark.svg` | Dark ground |
| `wavadesk-icon-teal.svg` | Solid teal |
| `wavadesk-lockup-light.svg` / `-dark.svg` | Horizontal icon + wordmark |
| `wavadesk-icon-*-1024.png` | 1024px raster of each variant |

### Colors — decide this before generating anything

The repo currently carries **two different brand palettes**:

| Role | Teal (logo, favicon, app icons) | Emerald (landing page CSS) |
|---|---|---|
| Primary | `#0f7e7a` | `#10b981` |
| Accent / light | `#15b6a8` | `#059669` |
| Deep | `#0a5e5b` | `#047857` |

Neutrals shared by both:

| Token | Hex |
|---|---|
| Text | `#0f172a` |
| Muted | `#64748b` |
| Border | `#e2e8f0` |
| Page background | `#f8fafc` |
| Dark ground | `#0d1117` |
| Dark raised | `#161b22` |

**Recommendation: use teal.** It matches the logo, and green-on-WhatsApp reads as WhatsApp's own
brand rather than yours. Flag the divergence as a site fix for later.

### Typography

- **Outfit** (300–900) — Latin
- **Cairo** — Arabic
- ~~Sora~~ — appears only in the lockup SVG and nowhere else in the product. Treat it as a mistake;
  set the wordmark in **Outfit 700/800**.

### Locales

English, French, Arabic. Arabic is RTL with full `dir="rtl"` support in the app, so any ad set
should have an Arabic version with mirrored layout and Cairo type.

---

## 3. Message hierarchy

### Claims that are safe to advertise

Implemented and verified in the audit:

- **AI replies from your knowledge base** — three modes (suggestion, autonomous, hybrid) with
  escalation keywords that hand off to a human.
- **Team routing with a shared pool** — each number maps to a team; conversations queue and agents
  claim them. The claim is atomically locked, so two agents can never take the same chat.
- **Connect by QR in under a minute.**
- **Full conversation history and audit log** — every action recorded with user, timestamp and target.
- **Four roles** — Super Admin, Admin, Supervisor, Agent, each with its own panel.
- **Real-time** — live queue, typing indicators, notifications.

### Claims to keep OUT of these ads

The audit found four headline marketing claims the product does not currently deliver. Running paid
acquisition on them would drive spend to a broken experience.

1. **"Start free trial" / "No card required"** — the free trial is non-functional. Trial tenants are
   rejected on all 36 subscription-gated API routes, so a trial signup can log in but cannot use the
   product at all. This is the single biggest blocker; **trial-led ads should wait** until it is fixed.
2. **"Multi-instance" / "Unlimited WhatsApp numbers"** — the instance limit is hardcoded to one,
   regardless of plan. The app's own error message reads "Your account is limited to one WhatsApp instance."
3. **"Save 20%" / annual pricing** — annual billing is not implemented, and the pricing page currently
   displays the annual price divided by twelve under a "/ year" label.
4. **Specific plan limits** (":n users", ":n conversations/month") — none are enforced, so do not build
   tier-comparison creative around them.

Until those land, anchor the ads on **AI auto-reply + shared team inbox + full history**, and use a
soft CTA ("See how it works", "Book a demo") rather than "Start free trial".

---

## 4. Creative direction

**Tone:** calm, operational, competent. This is infrastructure for a support team, not a growth-hack
toy. Avoid exclamation marks; avoid "revolutionary". The existing copy voice is plain and
declarative — match it.

**Visual motif:** the wave from the logo is the strongest asset available. Use it as a connective
line — a message arriving on the left, resolving into a reply on the right, the wave carrying it
across. The two terminal dots read naturally as "customer" and "resolved".

**Recurring device:** a WhatsApp-style chat bubble pair, since the landing page already ships one.
Real sample copy from the repo, reusable verbatim:

> **Customer:** "Hello, I'd like to know your delivery times?"
>
> **AI:** "Hi! Our standard delivery is 3 to 5 business days. For urgent orders, we offer express
> delivery within 24h. 📦"

**Avoid:** stock photos of headset call-centre agents, and anything that could read as an official
WhatsApp/Meta product. wavadesk connects to WhatsApp, it is not endorsed by it — keep the green away
from Meta's exact `#25D366` and do not reproduce their logo.

---

## 5. Artboards to produce

| Format | Size | Purpose |
|---|---|---|
| Square social | 1080 × 1080 | Feed post — one claim + chat bubble |
| Story / Reel | 1080 × 1920 | Vertical, wave as full-height spine |
| Landscape | 1200 × 628 | Link preview / Meta feed |
| Leaderboard | 728 × 90 | Display |
| Wide skyscraper | 300 × 600 | Display |

### Three concepts

**1. "Every conversation, in one place."**
Dark `#0d1117` ground, the wave in teal carrying three chat bubbles left to right, logo bottom-left,
soft CTA bottom-right.

**2. "Your AI already knows the answer."**
Light `#f8fafc` ground, the real delivery-times exchange rendered as bubbles, AI reply badged,
knowledge-base card behind it.

**3. "Two agents. One customer. Never again."**
The pool/claim mechanic — a queue of chats with one being claimed. Speaks directly to the pain and is
a claim the code genuinely backs.

---

## 6. Open decisions

1. **Teal vs emerald** — recommend teal (matches the logo).
2. **Hold trial-led creative** until the free-trial blocker is fixed, or ship with a soft CTA now.
