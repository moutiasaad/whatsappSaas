# Replicating this stack on another server

**Start here → [`RUNBOOK.md`](RUNBOOK.md).**

If you are Claude Code CLI freshly opened on a new server after `git clone`:
read `RUNBOOK.md` in full, then work its steps in order. It is written to be
executed, not just read.

## Script or markdown? — both, and here is the split

A shell script alone cannot do this job. Roughly a third of the work needs a
decision or a look at the box: which php-fpm socket path this distro uses,
whether Cloudflare sits in front, whether TLS comes from certbot or a panel,
and above all whether this is a cutover or a parallel copy — that last one
decides whether starting the gateway logs your live customers out of WhatsApp.
A script that guesses at those is a script that breaks production quietly.

A markdown runbook alone is the opposite problem: dumping two databases,
rebuilding the gateway from a bundle and reconciling file ownership are long,
exact, and boring — precisely the things a human retypes wrong at 2am.

So the deterministic parts are scripted and the judgement parts are documented:

| File | Runs where | Does |
|---|---|---|
| `export-bundle.sh` | **old** server, as root | Packages everything git does not carry: both `.env`s, the MariaDB dump, the Postgres dump, git bundles of **both** repos (each running an unpushed commit) plus their uncommitted edits, `instances/` (Baileys auth), tenant uploads, and the live Apache/Supervisor config. |
| `import-bundle.sh` | **new** server, as root | Restores all of it, creates both databases and their users, rebuilds both apps' dependencies. Idempotent; starts no services on purpose. |
| `RUNBOOK.md` | read by you or Claude Code | Provisioning, the cutover-vs-parallel decision, domain/`.env` rewrite, vhosts, supervisor, an ordered verification sequence, and a table of every trap this stack has actually hit. |
| `templates/` | copied on the new server | Supervisor programs for the workers, scheduler, Reverb and the gateway; the Apache proxy snippets for `wss://` and for `:8084`. |

## The short version

```bash
# on the OLD server
bash deploy/replicate/export-bundle.sh

# move it across (through your laptop — the servers need no link)
scp root@OLD:/root/wavadesk-migration/wavadesk-bundle-*.tar.gz .
scp wavadesk-bundle-*.tar.gz root@NEW:/root/

# on the NEW server
git clone https://github.com/moutiasaad/whatsappSaas.git /www/wwwroot/public/wavadesk.com
bash /www/wwwroot/public/wavadesk.com/deploy/replicate/import-bundle.sh /root/wavadesk-bundle-*.tar.gz
#   ↑ add --parallel if the OLD server is staying live (skips instances/)
# then RUNBOOK.md from step 4 — domains, vhosts, services, verification
```

## Three things that bite before anything else

1. **You cannot clone your way to a working gateway.** The WhatsApp gateway's
   `origin` now points at `moutiasaad/whatsappBoot`, which **is still empty** —
   nothing has been pushed to it yet. The copy in production carries a commit
   that exists nowhere else — the native_flow fix that makes interactive buttons
   and lists render as tappable — plus about nine modified files on top.
   `export-bundle.sh` captures that and records the real origin URL; cloning
   either remote today throws the fix away and the breakage is silent.
2. **The app runs an unpushed branch too.** Production `wavadesk.com` sits on
   `feat/marketing-i18n-mobile-nav-paypal-cards`, two commits ahead of
   `origin/main` and on no remote branch at all. A plain `git clone` gives you
   `main` and silently drops them — including this runbook. `import-bundle.sh`
   step 0 fetches the bundled history and checks out the exact commit; the clone
   below is only there to give it a repo to fetch into.
3. **One WhatsApp pairing, one live socket.** Both servers restoring the same
   `instances/` will fight over every connection and knock each other offline —
   including the old, live one. Step 0 of the runbook makes you choose cutover
   or parallel before this can happen; in parallel, pass `--parallel` to
   `import-bundle.sh` so the new gateway never gets the credentials at all.

## Related

- `deploy/README.md` — the push-to-`main` auto-deploy pipeline, once the new box is live.
- `RUN_PRODUCTION.md` — the older runbook for **tshlbot**, a different app on the same server. Not applicable here.
