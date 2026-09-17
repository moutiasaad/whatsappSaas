---
title: "Wavadesk — Meta App Review Submission Guide"
subtitle: "Step-by-step to take the Facebook Messenger integration live"
author: "Wavadesk"
date: "2026-09-17"
---

# Wavadesk — Meta App Review Submission Guide

**Purpose**: this is the one-time submission that unlocks the Facebook Messenger integration for every Wavadesk customer. Once Meta approves the four permissions listed here, any Facebook user in the world can connect their Facebook Page to Wavadesk in 30 seconds — no verification on their side.

**Estimated total time**: 3–4 weeks end-to-end. Most of that is Meta's own processing time. Your active time is roughly 4–6 hours across the whole process, mostly in the first sitting.

**Prerequisites (before you open this guide)**:

- The Wavadesk Meta app exists at `developers.facebook.com` (App ID `1016732254755210`).
- The Messenger integration is deployed and internally tested end-to-end.
- You have admin access to the Wavadesk Facebook Page.
- You have your business registration documents ready.

---

# Section 1 — How Meta's model works

**One-line summary**: You verify Wavadesk once. Your customers verify nothing.

Think of it like installing a Slack app on a workspace — Slack reviewed the app once, and any workspace can install it. Same shape for Meta:

- **Wavadesk** = one Meta app with one App ID.
- **You** do Meta Business Verification + App Review once.
- **Any Facebook user in the world** can then OAuth into their own Facebook Page and start receiving messages in their Wavadesk inbox in 30 seconds.
- **No verification per-tenant.** No documents. No paperwork on their side.

## What each side does

| Party | Task | Frequency |
|---|---|---|
| **Wavadesk (you)** | Business Verification + App Review on ONE Meta app | Once, at launch |
| **Each tenant/customer** | Click *Connect a Facebook Page* + grant permissions in the OAuth popup | 30 seconds, per Page |

The rest of this document is the "once, at launch" work.

---

# Section 2 — Business Verification (Phase 1)

**Time**: ~30 minutes of your active work, then 3–7 days waiting for Meta.

**Why first**: Meta refuses to accept App Review submissions until Business Verification is approved. Do this before anything else.

## What you need to gather (before you open Meta's website)

Put these files in one folder on your desktop so you're not scrambling mid-form:

| Item | Notes |
|---|---|
| Business registration certificate | Moroccan RC / K-bis / articles of incorporation. PDF or clear photo. The business name here MUST match what you type in the Meta form character-for-character. |
| Tax ID document | VAT number, tax registration certificate, or equivalent. |
| Photo of a utility bill or bank statement | Optional but strongly recommended — makes the address verification pass on first try. |
| Wavadesk logo | 1024×1024 PNG, transparent or solid background. |

## Prepare your Facebook Page

Meta rejects apps whose linked Facebook Page looks empty or fake. Open your Wavadesk Page and make sure these are done:

- [ ] Profile picture: Wavadesk logo (square).
- [ ] Cover photo: any banner (product screenshot or brand hero image, 1640×720 works).
- [ ] Fill the **About** section with a short description of what Wavadesk is.
- [ ] Post at least 3 real posts, spaced across a few hours:
  - Post 1: *"Introducing Wavadesk — WhatsApp + Live Chat + Messenger in one inbox"* with a screenshot of your inbox.
  - Post 2: A product feature (AI auto-reply, the Messenger integration itself, whatever's most visually compelling).
  - Post 3: Anything human — a hiring announcement, a customer story, a behind-the-scenes photo.
- [ ] Set the Page's **Category** to `Software company` or `Product/Service`.

## Create your Business Portfolio

Skip this section if you already have a Business Portfolio at `business.facebook.com`.

1. Go to `https://business.facebook.com`.
2. Top-right → **Create Account** (or whatever the current equivalent is labelled).
3. Fill in:
   - **Business name**: your registered business name, exactly as on the RC.
   - **Your name**: your legal name.
   - **Business email**: something on your own domain (e.g. `contact@wavadesk.com`). Meta rejects `@gmail.com`, `@outlook.com`, etc. If you don't have a mailbox on `wavadesk.com` yet, create one before submitting this form.
4. Confirm the email via the link Meta sends.

## Start Business Verification

1. In Business Manager (`business.facebook.com`) → left sidebar → **Settings** (gear icon at the bottom).
2. Under Security → **Security Center**.
3. Find the **Business Verification** section → click **Start**.
4. Fill in the form:
   - **Legal business name**: EXACTLY as it appears on your business registration certificate. Meta compares the spelling, punctuation, and capitalisation character-by-character.
   - **Address**: EXACTLY as on the registration certificate.
   - **Phone number**: a working number Meta will call. Use a Moroccan mobile you'll answer within 5 minutes.
   - **Website**: `https://wavadesk.com`.
   - **Business email**: same as in the previous step.
5. Upload:
   - Registration certificate (mandatory).
   - Utility bill or bank statement matching the address (optional but recommended).
6. Choose verification method: **Phone call**. Meta will call the number you provided and read a code.
7. Click **Submit**.
8. Meta shows: *"We're reviewing your submission"*.

**Now you wait 3–7 business days.** Meta emails a decision. Nothing to do on your side during the wait.

## What if Meta rejects Business Verification

Common rejection reasons and fixes:

| Rejection message | Fix |
|---|---|
| "Business name doesn't match the document" | Retype it exactly as on the certificate — including special characters, spacing, and case. |
| "Document is unclear/expired" | Scan or photograph the certificate again in good light. Make sure it's a current, valid version. |
| "Address doesn't match" | Match the certificate address exactly. Add a utility bill for the same address. |
| "Phone verification failed" | Meta tried to call, you didn't pick up (or the code was misheard). Retry with a phone in a quiet room. |

You can resubmit unlimited times. Each retry restarts the 3–7 day clock.

## Link the verified Business to the Wavadesk Meta app

Once Meta emails *"Your business is verified"*:

1. Go to `https://developers.facebook.com/apps` → click **Wavadesk**.
2. Left sidebar → **App Settings** → **Basic**.
3. Scroll to the **Business Account** section → click **Add**.
4. Pick your verified Business Portfolio from the dropdown → **Confirm**.
5. Save at the bottom of the page.

You're now cleared to move to Phase 2.

---

# Section 3 — Prepare the Meta app (Phase 2)

**Time**: 5–10 minutes.

Meta refuses App Review submissions when required fields are missing on the app's Basic Settings page. Do these all in one sitting.

## Complete Basic Settings

`developers.facebook.com/apps` → **Wavadesk** → left sidebar → **App Settings** → **Basic**.

Fill or upload each item:

| Field | Value |
|---|---|
| **App icon** | Click upload → pick your 1024×1024 Wavadesk PNG. |
| **Display name** | `Wavadesk` |
| **App domains** | `wavadesk.com,app.wavadesk.com` (comma-separated, no spaces) |
| **Contact email** | `contact@wavadesk.com` (must be on your own domain) |
| **Privacy Policy URL** | `https://wavadesk.com/legal/privacy` |
| **Terms of Service URL** | `https://wavadesk.com/legal/terms` |
| **User Data Deletion URL** | `https://wavadesk.com/legal/privacy` (or add a dedicated `/legal/data-deletion` page and use that) |
| **Category** | `Business and Pages` |

Click **Save Changes** at the bottom.

## Verify the OAuth redirect URI

Same dashboard → left sidebar → **Facebook Login for Business** → **Settings**.

- **Valid OAuth Redirect URIs**: `https://app.wavadesk.com/tenant-admin/messenger/oauth/callback`

Save.

If this URL isn't whitelisted, the Connect a Facebook Page flow will fail with "redirect URI not whitelisted" during App Review testing, and Meta will reject.

---

# Section 4 — Create reviewer test credentials

**Time**: ~10 minutes.

Meta reviewers need to log in as a Wavadesk tenant admin and reproduce the connect flow. Create a dedicated tenant they can safely use.

## Steps

1. Log into `app.wavadesk.com/admin-control-panel` as super-admin.
2. Create a new tenant:
   - Name: `Meta Reviewer Test`
   - Plan: `Growth` (or whichever plan has the `messenger` module ticked)
3. Under Users, create an admin user for that tenant:
   - **Email**: `reviewer@wavadesk.com` (create this mailbox first if it doesn't exist — Meta may verify)
   - **Password**: generate a 20-character random password
4. **Test the login yourself in an incognito window** to confirm it works. Meta reviewers WILL test this before anything else; if the login fails, they reject the whole submission without looking at the rest.
5. Save the password in your password manager. You'll paste it into Meta's App Review form in Section 6.

---

# Section 5 — Record the screencast

**Time**: 30–60 minutes (setup + one or two takes).

**One screencast covers all four permissions.** Upload once, attach the same link to each permission's submission.

## Technical specs

- Resolution: 1080p (1920×1080) or higher.
- Length: 2–4 minutes total.
- Audio: your voice narrating in English. No background music.
- Software: OBS Studio (free, cross-platform) or Loom.
- Hosting: YouTube as **Unlisted**, or Google Drive with link-sharing enabled. Do NOT upload directly to Meta — they want a URL.

## Script (2:20 to 2:35 total)

Follow this second-by-second. Practise once before recording.

| Timestamp | On-screen action | Narration |
|---|---|---|
| 0:00 – 0:15 | Show `wavadesk.com` marketing homepage. | "Wavadesk is a shared inbox for small businesses. It handles WhatsApp, website live-chat, and — what I'll demonstrate today — Facebook Messenger." |
| 0:15 – 0:25 | Log into `app.wavadesk.com/login` using the reviewer credentials from Section 4. | "I'm now logged in as the merchant admin — the person running a small business who wants to route their Facebook Messenger through Wavadesk." |
| 0:25 – 0:35 | Left sidebar → **Facebook Messenger** → **Settings**. | "This is the Page-connection screen. No Facebook Page has been connected yet." |
| 0:35 – 0:55 | Click **Connect a Facebook Page**. Facebook OAuth popup opens. Grant all requested permissions. | "Wavadesk requests four permissions: pages_messaging, pages_manage_metadata, pages_show_list, and pages_read_engagement. I grant all four." |
| 0:55 – 1:10 | If Page picker appears, pick a Page. Otherwise, land directly on the confirmation screen. | "If the merchant manages multiple Pages they pick one — otherwise the flow completes automatically. The Page is now Live in Wavadesk." |
| 1:10 – 1:25 | Open Messenger on your phone or `messenger.com` in another browser. Send a message to the connected Page. | "Now I'll simulate a customer on Messenger sending a question to this business." |
| 1:25 – 1:40 | Back to Wavadesk → **Inbox** → click **Messenger** channel tab. The message appears within ~2 seconds. Point at the visitor's name + avatar. | "The message lands in the inbox in real time. The visitor's real Facebook name and avatar come from pages_read_engagement." |
| 1:40 – 1:55 | Click the conversation → **Claim** → type a reply → **Send**. | "The agent claims the conversation, replies, sent." |
| 1:55 – 2:10 | Switch back to Messenger (phone or browser). Reply arrives within a second. | "The reply arrives back on the customer's Messenger app instantly, from the Page." |
| 2:10 – 2:25 | Back in Wavadesk → click **Close** on the conversation. | "The agent closes the conversation. That's the full round-trip." |
| 2:25 – end | Stop recording. | (silence) |

## Upload

1. YouTube: log in → **Upload** → set visibility to **Unlisted** → wait for processing → copy the URL.
2. Google Drive alternative: upload the .mp4 → right-click → Share → **Anyone with the link can view** → copy link.

Test the link works in an incognito browser before pasting it into Meta.

---

# Section 6 — Submit each permission for Advanced Access

**Time**: ~20 minutes.

## Getting to the submission page

1. `developers.facebook.com/apps` → **Wavadesk** → left sidebar → **App Review** → **Permissions and Features**.
2. You'll see a searchable list of every Meta permission. Filter or search for each of the four permissions below and click **Request Advanced Access** on each.

## The four permissions to request

| Permission | Why Wavadesk needs it |
|---|---|
| `pages_messaging` | Receive customer messages on the tenant's Facebook Page and send replies from Wavadesk's inbox on the tenant's behalf. |
| `pages_manage_metadata` | Subscribe the tenant's Page to webhook events so incoming messages route to Wavadesk in real time. |
| `pages_show_list` | Show the tenant admin the list of Pages they own so they can pick which one to connect. |
| `pages_read_engagement` | Read the Page's basic profile (name, avatar, category) for display in the tenant's Wavadesk settings. |

## For each permission, Meta asks two things

### Question A: "How does your app use this permission?"

Copy-paste the corresponding paragraph verbatim.

**`pages_messaging`**:

> Wavadesk is a shared-inbox customer-support platform for small businesses. Merchant admins connect their own Facebook Pages to Wavadesk. When a customer messages that Page on Messenger, Wavadesk receives the message via webhook and displays it in the merchant's shared inbox alongside their other support channels (WhatsApp, website live-chat). Agents at the merchant reply from the Wavadesk inbox, and the reply is sent back to the customer through Meta's Send API using this permission.

**`pages_manage_metadata`**:

> After a merchant admin connects their Facebook Page during OAuth, Wavadesk uses pages_manage_metadata to call POST /{page-id}/subscribed_apps — subscribing the Page to messages, messaging_postbacks, message_deliveries, message_reads, and message_echoes events. Without this permission, incoming messages never reach the merchant's Wavadesk inbox.

**`pages_show_list`**:

> A merchant admin may own several Facebook Pages. During the Connect a Facebook Page flow at /tenant-admin/messenger/settings, Wavadesk calls GET /me/accounts and displays the returned Pages in a radio picker so the admin can select which Page they want Wavadesk to manage. Without this permission the picker cannot render.

**`pages_read_engagement`**:

> Wavadesk reads basic Page metadata (name, avatar, category) to display in the connected-pages settings screen so the tenant can identify which Page is which. No engagement data (posts, comments, likes) is read.

### Question B: "Provide detailed steps for how to test this permission"

Paste the same block for all four permissions — Meta reviewers use one login and follow one flow to see all four permissions in use:

```
TEST CREDENTIALS

Wavadesk tenant admin login:
    URL: https://app.wavadesk.com/login
    Email: reviewer@wavadesk.com
    Password: <the 20-character password you generated in Section 4>

STEPS TO REPRODUCE

1. Log into https://app.wavadesk.com/login with the credentials above.
2. In the left sidebar, click "Facebook Messenger" -> "Settings".
3. Click the "Connect a Facebook Page" button.
4. In the Facebook OAuth popup, grant all four requested permissions.
5. If your Facebook account manages multiple Pages, pick any Page
   in the radio picker. If you manage one Page, the flow auto-completes.
6. The Page is now marked "Live" on the Wavadesk settings page.
7. From any Messenger client (messenger.com or the mobile app), send
   a message to the connected Page. Any text.
8. Back in Wavadesk, go to sidebar -> "Inbox" -> click the Messenger
   channel tab.
9. The message appears in the thread within 2 seconds.
10. Click the conversation, click "Claim", type a reply, click "Send".
11. The reply arrives back in Messenger within 2 seconds.

For any question during review, please contact: contact@wavadesk.com
```

### Question C: Attach the screencast

Paste the YouTube Unlisted link (or Google Drive shareable link) from Section 5 into the video field for each permission.

## Save each permission

After filling A + B + C for a permission, click **Save**.

Repeat for the remaining three permissions.

## Submit the whole batch

Once all four are queued (each shows "Ready to submit"), scroll to the top of **App Review → Permissions and Features** and click **Submit for Review**.

Meta shows a summary → click **Submit**.

You'll see: *"Your submission is being reviewed"*.

---

# Section 7 — Wait for Meta

**Time**: 5–14 business days per permission. Sometimes faster on retries.

Meta reviews each permission separately. You get an email per decision.

## Possible outcomes per permission

| Outcome | What it means | What to do |
|---|---|---|
| **Approved** | Real users can now use that permission via your app immediately. | Nothing. Move on to the others. |
| **Rejected** | The email lists a specific reason. | Fix, resubmit that one permission alone (don't re-run the whole batch). |
| **Needs more info** | Meta asks a clarifying question. | Reply within the deadline they give. |

## The most common rejection reasons

| Reason | Fix |
|---|---|
| "Test credentials do not work" | Log in with the credentials yourself in an incognito browser. If they don't work, create a fresh user + password and update the review form. Resubmit. |
| "Video does not show the permission being used" | Re-record the specific segment where that permission is used. Narrate the permission name explicitly. Re-upload, update the video link. Resubmit. |
| "Facebook Page appears inactive or empty" | Add profile picture, cover photo, About text, 3+ posts (see Section 2). Resubmit. |
| "App is empty / does not appear to be a real product" | Fill any missing field on App Settings → Basic (see Section 3). Resubmit. |
| "Business Verification not complete" | Complete or reconfirm Business Verification (Section 2), then resubmit App Review. |
| "Permission not needed for stated use case" | Rewrite the "how you use it" paragraph to be more specific about the code paths that call the API and what would break without it. Resubmit. |

If a rejection message is unclear, paste the exact wording to your development team and they can help decode Meta's boilerplate.

---

# Section 8 — After all four are approved

## Flip the app to Live mode

1. Meta app dashboard → top bar → toggle **Development** → **Live**.
2. Meta shows a confirmation dialog listing what changes → confirm.

Real Facebook users can now connect their Pages to Wavadesk through the Connect a Facebook Page flow, with zero verification on their side.

## Test one real customer flow before marketing

Before you announce Messenger to your customer base:

1. Ask one friendly customer to try connecting their Page.
2. Walk with them through the flow (screen share or phone call).
3. Confirm messages arrive in their Wavadesk inbox.
4. Confirm their replies arrive back in Messenger.

If anything is off, fix before announcing.

## Add Messenger to your paid plans

Wavadesk gates the Messenger module per-plan. Grant it on the plans where you want to sell it:

- Super-admin → **Platform** → **Plans** → edit each plan → tick **Facebook Messenger** in the modules list → **Save**.

A tenant on a plan without the `messenger` module ticked won't see Messenger in their sidebar or inbox.

## Update your marketing site

The landing page's feature list can now include Facebook Messenger. Update:

- `wavadesk.com` hero / features section
- `wavadesk.com/features/*` (add a Messenger feature page if you have per-feature SEO pages)
- Pricing page: note which plans include Messenger

---

# Section 9 — Realistic timeline for the whole submission

```
Day 0     : Start Business Verification (Section 2). Prep documents.
Day 3-7   : Meta approves Business Verification (or rejects — fix + retry).
Day 8     : Complete Sections 3, 4, 5, 6. Record screencast, submit App Review.
Day 10-22 : Meta App Review decisions (per permission, staggered).
Day 22+   : App goes Live. Announce Messenger to customers.
```

**Total: about 3-4 weeks for the first submission.** Retries are faster because most of the docs are already accepted.

---

# Section 10 — Rate limits and other things worth knowing

## Rate limits are on your app, not per-tenant

Meta's default limits:

- ~200 API calls per user per hour.
- ~4800 calls per Page per hour.

Wavadesk's send + subscribe activity fits comfortably in these limits at reasonable scale. If your total across all tenants approaches the limit, Meta lets you request a bump — file a support ticket with your usage data.

## Some Meta features DO require per-tenant Business Verification

The following are NOT part of the base Messenger permissions and would require each tenant to complete their own Business Verification:

- Messenger commerce (checkout inside Messenger)
- Marketing broadcast messages
- Recurring notifications

**Basic message send/receive (what Wavadesk does today) does NOT require this.** You're clear to launch without asking tenants for anything beyond an OAuth click.

## Token expiration

Page access tokens Wavadesk stores DO NOT expire on their own, provided they were minted from a long-lived user token (which Wavadesk does in the OAuth flow). They only get invalidated when:

- The Facebook user changes their password.
- The Facebook user revokes app access from `facebook.com/settings/apps`.
- The Facebook user's account is disabled by Meta.

When any of the above happens, Meta returns error code 190 on the next API call. Wavadesk's MessengerService catches this and marks the Page disconnected with reason `token_invalidated_190`, prompting the tenant to reconnect via the same OAuth flow.

---

# Appendix A — What Wavadesk shipped for the Messenger integration

For your records + to hand to a technical reviewer if Meta ever asks.

The implementation covers seven phases, all deployed to production and tested end-to-end:

1. **Foundation** — plan module registration (`config/plan_modules.php`), Meta service config, `messenger_pages` table, setup documentation.
2. **Data model** — `messenger_conversations` + `messenger_messages` tables mirroring the WebChat pattern with Messenger-specific fields (PSID, 24-hour messaging window, contact profile snapshot).
3. **Webhook receive** — signed webhook endpoint at `/api/webhooks/messenger` with HMAC verification, dispatches an ingest job per entry.
4. **Send API** — `MessengerService` wraps Meta's Graph API for send, subscribe, and profile fetch, with 24-hour window enforcement and token-invalidation handling.
5. **Self-serve connect UI** — OAuth flow at `/tenant-admin/messenger/settings`, exchanges user token for long-lived + Page tokens, auto-subscribes the app to Page webhooks, supports multi-Page picker and disconnect.
6. **Inbox integration** — Messenger conversations appear in `/tenant-admin/inbox` alongside WhatsApp and website live-chat, with a channel filter, real-time updates via Laravel Reverb (Phase 6.1), and the same claim/release/close/reply actions.
7. **AI auto-reply** — Anthropic Claude answers Messenger visitors using the tenant's knowledge base, with escalation-keyword handoff to human agents.

---

# Appendix B — Contact for problems

If you get stuck at any step, gather:

1. A screenshot of what Meta is showing you.
2. The exact rejection email if Meta rejected something.
3. Which section of this document you were on.

Send to the Wavadesk technical team.
