# Facebook Messenger — Meta app setup

Wavadesk's Messenger integration is additive: it lives alongside WhatsApp
and web-chat, gated by the `messenger` plan module. This doc is the
Meta-side checklist — every step you do at `developers.facebook.com` and
in the Meta app dashboard before the integration can accept a real Page.

The code side lives in `app/Models/Messenger/`, `app/Services/Messenger/`,
`app/Http/Controllers/Webhooks/MessengerWebhookController.php`, and
`app/Http/Controllers/Admin/MessengerPageController.php`.

---

## Phase A — Create the Meta app (~10 min)

1. Go to <https://developers.facebook.com> and log in with a personal
   Facebook account. That account becomes the app's owner; you can add
   more admins later.
2. Top-right → **My Apps** → **Create App**.
3. **Use case**: pick **Other**, then **Business**. Never pick "Consumer"
   — the Messenger product isn't listed there.
4. **App display name**: `Wavadesk` (or whatever end users will see on
   the OAuth consent screen).
5. **App contact email**: an inbox you actually read — Meta uses this
   for policy notices and warnings.
6. **Business Account**: pick one if you have one. If not, skip; you can
   attach one later via **App Settings → Business Account**. You need
   one before App Review (see Phase E).
7. Click **Create app**. Meta shows the app dashboard.

Copy from the app dashboard header:
- **App ID** (public). Paste into `.env` on the core box as `META_APP_ID`.
- **App Secret** (private — click Show, enter password). Paste into
  `.env` as `META_APP_SECRET`. Treat this like a database password.

---

## Phase B — Add products (~5 min)

In the left sidebar → **Add Products**:

1. **Messenger** → Set up.
2. **Facebook Login for Business** → Set up.
   - When it asks for a **redirect URI**, add
     `https://app.wavadesk.com/tenant-admin/messenger/oauth/callback`
     (the route the connect flow POSTs the code back to — Phase 5 of
     the code integration adds it).

The Facebook Login product is what lets a tenant admin's browser show
the "Continue with Facebook" popup and grant your app access to their
Pages. Without it, the connect button 500s.

---

## Phase C — Configure Messenger settings (~3 min)

App dashboard → **Messenger → Settings**.

### Webhooks

- **Callback URL**: `https://app.wavadesk.com/webhooks/messenger`
- **Verify Token**: the same string you generate for `.env`
  `META_WEBHOOK_VERIFY_TOKEN`. Generate one on the core box:
  ```bash
  php -r "echo bin2hex(random_bytes(24));"
  ```
  Paste that value into BOTH places. If they don't match byte-for-byte
  Meta's initial GET verification returns 403 and the webhook stays
  disabled.
- **Subscribe** to fields: `messages`, `messaging_postbacks`,
  `message_deliveries`, `message_reads`, `message_echoes`.
  (`message_echoes` shows messages you send from Meta's own Page inbox
  so wavadesk stays in sync when an agent goes off-platform.)

### App Roles → Testers

Until App Review approves the app, only accounts you explicitly add here
can connect a Page. Add yourself under **Roles → Roles → Add People →
Tester** (using your Facebook profile). Accept the invite email.

---

## Phase D — Set required permissions (~2 min)

App dashboard → **App Review → Permissions and Features**. These four
must be requested (each shows a "Request Advanced Access" button):

| Permission | Why wavadesk needs it |
|---|---|
| `pages_show_list` | List the tenant's Pages during the connect flow. |
| `pages_manage_metadata` | Subscribe the app to a Page's webhooks. |
| `pages_messaging` | Read incoming messages and send replies. |
| `business_management` | Only if Pages are owned through a Business Manager (recommended for real tenants). |

You can only request these AFTER Business Verification is complete
(next phase).

---

## Phase E — Business Verification (5-14 days waiting)

App dashboard → **Settings → Business Verification**.

Required documents (start collecting NOW):

- **Business registration certificate** (CR, K-bis, articles of
  incorporation — whichever your jurisdiction issues).
- **Tax ID** (VAT number, EIN, etc.).
- **Verifiable business phone** (Meta calls it during review).
- **Verifiable business email** on the same domain as your website.
- **Business address** matching the registration document.
- **A live website at the same domain** as your business email (Meta
  crawls it and rejects if it 404s or looks like a template site).

Meta's median processing time: **3-7 days**. Worst case: 2 weeks with
back-and-forth on document quality. Do this in parallel with the code
work.

---

## Phase F — App Review (5-14 days waiting)

After Business Verification is approved, request each permission from
Phase D. For each you upload:

1. **A screencast** (2-5 minutes each) showing wavadesk actually using
   the permission end-to-end:
   - User clicks Connect Page in wavadesk
   - Facebook OAuth popup appears
   - User selects a Page and grants permissions
   - User sends a message from a personal FB account to the Page
   - The message appears in wavadesk's inbox
   - Wavadesk agent replies from the inbox
   - The reply arrives back in Messenger

2. **A written explanation** of why each permission is needed.

Meta reviewers reject on any missing step. Record the screencast AFTER
the connect flow works locally — the connect flow ships in Phase 5 of
the code integration.

---

## While you wait: internal testing works today

You do NOT need any of Phases D-F to test the integration yourself.
Any Facebook account you add as an App Tester (Phase C) can go through
the connect flow, and any Page they own can send/receive messages
through your app.

That's how we'll dogfood the integration in this session: you add
yourself as a tester, connect your business Page, and we exercise the
flow with your personal Facebook account as the "customer" sending
messages in.

---

## What to send back to Claude

Once Phases A-C are done, paste back:
1. `META_APP_ID` value (safe to share here — it's public)
2. Confirmation `META_APP_SECRET` and `META_WEBHOOK_VERIFY_TOKEN` are in
   the core box's `.env` (DON'T paste the values themselves)
3. Confirmation the webhook Callback URL in Phase C is set (Meta won't
   accept it until the code endpoint exists; that's Phase 3 of the code
   plan)

Then I ship Phases 3-5 of the code integration and we go live for
internal testing.
