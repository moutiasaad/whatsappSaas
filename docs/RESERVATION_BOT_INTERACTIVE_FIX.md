# Reservation Bot — Auto-Reply & Clickable Lists: Root Causes & Fixes

_Date: 2026-06-10_

## Symptoms reported

1. Sending the trigger word (`book` / `حجز`) produced **no auto-reply at all**.
2. After that was fixed, the bot replied but as **plain numbered text** instead of a
   **tappable list/buttons** the customer can click.

Neither symptom was a single bug — there were **four distinct problems** stacked on top of
each other. Each had to be peeled back to reach the real one.

---

## Problem 1 — The WhatsApp gateway was completely down

**What:** The self-hosted gateway (`xapi-prod-v1.wavadesk.com`, a CodeChat/Baileys Node app on
port `8084`) had crashed. Every request returned **HTTP 503**, so no inbound WhatsApp webhook
ever reached the app — the `book` message never arrived, which is why there was no log of it.

**Fix:** Restarted the gateway under pm2 and persisted it:

```bash
cd /www/wwwroot/public/xapi-prod-v1.wavadesk.com
npx pm2 start ecosystem.config.mjs   # build (tsc) + node dist/src/main.js
npx pm2 save
```

> Note: pm2 boot-persistence (`pm2 startup`) is **not** configured, so a server reboot will kill
> it again. Worth setting up.

---

## Problem 2 — The WhatsApp session was dead / wouldn't survive a restart

**What:** Even with the gateway up, instance `13`'s socket was stuck in `connecting` (live
`connectionState = connecting`, while the stored status falsely showed `ONLINE`). The gateway DB
confirmed **zero messages received since 15:38**. Network egress to WhatsApp was fine (proving it
was not a connectivity issue) — the linked-device session was simply invalid.

**Key gateway flaw discovered:** this gateway **loses its live WhatsApp link on every restart**
and does not cleanly auto-reconnect from saved credentials — it needs a **fresh QR re-scan**.

**Fix / recovery procedure (repeatable):**

```
DELETE /instance/logout/{name}     # clear the wedged session
pm2 restart "CodeChat Api"         # reset the in-memory waMonitor to a clean 'close' state
GET    /instance/connect/{name}    # now mints a fresh QR (base64)
```

Then re-scan from the business phone: **WhatsApp → Linked Devices → Link a device**.
(During this process the app re-created the instance under new IDs: 13 → 14 → 15.)

---

## Problem 3 — `@lid` resolution caused duplicate workers to swallow the job

**What:** Real customers now arrive with a privacy `@lid` JID instead of a phone number (see
`keyLid` in the payload). The old code tried to resolve `@lid → phone` via a gateway lookup that
**always failed and added a ~13s blocking HTTP call** to every inbound message. That latency
pushed the queue job past the database queue's `retry_after`, so a **second worker picked up the
same job**, hit the dedup check, and returned early — the reservation bot never got to reply.

**Fix (`app/Jobs/ProcessIncomingMessage.php`):**
- Stopped resolving `@lid`; the gateway accepts `@lid` as a send recipient, so it works end-to-end.
- Added `keyLid` to the sender-extraction paths.
- Wrapped `broadcast()` in try/catch so a down realtime broadcaster can't abort inbound handling.

---

## Problem 4 (the real one) — Interactive messages didn't render as tappable

This was the actual "I want a clickable list" problem.

**What we proved by live testing:**
- The **old** self-hosted gateway sent interactive messages that WhatsApp **received but rendered
  as plain text** (real numbers), or **dropped entirely** (`@lid` recipients) — even though the
  gateway returned HTTP `201`.
- The hosted SaaS gateway `api.whatstshl.online` (which Sultankoo uses, same project but a
  **newer build**) renders them correctly. So it was **not** a universal WhatsApp limitation — it
  was a **gateway encoding problem**.

**Root cause:** WhatsApp only renders native-flow interactive messages when the relay carries a
specific `biz → interactive → native_flow` node. Our gateway built the `interactiveMessage`
payload but sent it **without that node** (and without the `header` field), so WhatsApp silently
ignored the buttons and showed only the body text.

**Fix (gateway — `src/whatsapp/services/whatsapp.service.ts`):**

1. **Add the `header` field** to the interactive message:
   ```ts
   interactiveMessage: {
     header: { title: bm.title || '', hasMediaAttachment: false },
     body: { text: bodyText },
     footer: { text: bm.footer || '' },
     nativeFlowMessage: { buttons },
   }
   ```

2. **Attach the `additionalNodes` native_flow hint** when relaying an interactive message
   (this was the decisive fix):
   ```ts
   const hasInteractive = !!(
     m.message?.interactiveMessage ||
     m.message?.viewOnceMessage?.message?.interactiveMessage
   );
   const relayOptions: any = { messageId };
   if (hasInteractive) {
     relayOptions.additionalNodes = [{
       tag: 'biz', attrs: {},
       content: [{
         tag: 'interactive', attrs: { type: 'native_flow', v: '1' },
         content: [{ tag: 'native_flow', attrs: { name: 'mixed', v: '1' }, content: [] }],
       }],
     }];
   }
   await this.client.relayMessage(recipient, m.message, relayOptions);
   ```

3. **Rewrote `listMessage()`** to build a native-flow **`single_select`** interactive list
   (which renders) instead of the deprecated legacy `listMessage` proto (which WhatsApp no longer
   renders). Lists with more than 3 options now work.

**Result (verified live):** buttons and lists now arrive as **tappable** on both a real
`@s.whatsapp.net` number **and** an `@lid` recipient.

---

## App-side changes (wavadesk repo)

`app/Jobs/ProcessIncomingMessage.php`
- Recognize taps: extract the tapped id/rowId from
  `content.nativeFlowResponseMessage.paramsJson` and the iStoreBox/CodeChat variants
  (`buttonReply.id` / `listReply.rowId` first so numeric step-matching keeps working, then the
  `selectedDisplayText` / `buttonReply.displayText` / `listReply.title` display-text fallbacks).
- `@lid` + `keyLid` + best-effort broadcast fixes (Problem 3).

`app/Services/WhatsApp/Gateway/EvolutionApiClient.php`
- Added `sendButtons()` (native-flow quick-reply buttons, max 3).

`app/Services/Reservation/ReservationBotService.php`
- `sendList()` now sends the tappable native-flow list (text fallback on failure).
- The morning/afternoon step uses `sendButtons()`; dates and slots use `sendList()`.
- **Every reservation step is now clickable**, and a tap is parsed exactly as if the customer
  had typed the number.

---

## Why each "it's still broken" happened

| Attempt | Why it failed | Lesson |
|---|---|---|
| Restart workers only | Workers cache PHP classes in memory | Must `supervisorctl restart wavadesk:*` after code changes |
| Send to `@lid` | Old gateway dropped interactive to `@lid` entirely | The gateway build was the problem, not `@lid` |
| `header` field alone | Necessary but not sufficient | The `additionalNodes` node is the decisive piece |
| Each gateway code change | Gateway loses session on restart | Each rebuild costs one QR re-scan (until session-persistence is fixed) |

---

## Operational notes / follow-ups

- **Gateway restarts require a QR re-scan.** This is a gateway bug (session doesn't survive a
  restart). Fixing it would make deploys and uptime painless and stop the re-scan churn.
- **pm2 boot-persistence** (`pm2 startup`) should be configured so a reboot doesn't kill the bot.
- **Gateway code changes are uncommitted** in `xapi-prod-v1.wavadesk.com` (separate repo,
  `code-chat-br/whatsapp-api`). They are built and running but should be committed to your fork
  so a future `tsc` rebuild / redeploy doesn't lose them.
- **Translation** of the reservation bot (per-tenant locale + `reservation.php` lang files for
  `ar`/`en`/`fr`) is the next planned change.
