@extends('layouts.admin')

@section('title', __('ui.wa_connect.title'))

@section('breadcrumb')
    <span>{{ __('ui.wa_connect.breadcrumb') }}</span>
@endsection

@push('styles')
<style>
/* ════════════════════════════════════════════════════════════════
   WHATSAPP CONNECTION — one workspace, one number, one page.
   Everything scoped under .wacon so the shared admin styles are
   left alone. Logical properties throughout: this panel is served
   in Arabic too, and the whole page mirrors.
════════════════════════════════════════════════════════════════ */
.wacon{
  --wa:#25a35a;--wa-d:#128c4a;--wa-50:#e9f7ee;
  --bd:#e6ebf0;--soft:#f7f9fa;--mut:#64748b;--mut-2:#94a3b8;--txt:#0f172a;
  --amber:#d97706;--amber-50:#fef3e2;--red:#dc2626;--red-50:#fef2f2;
  max-width:1120px;
}

.wacon-head{display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.wacon-head h1{font-size:22px;font-weight:800;letter-spacing:-.02em;margin:0 0 4px}
.wacon-head p{margin:0;font-size:13.5px;color:var(--mut);max-width:56ch}
.wacon-pill{margin-inline-start:auto;display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 14px;border-radius:999px;font-size:12.5px;font-weight:700;border:1px solid var(--bd);background:#fff;color:var(--mut);white-space:nowrap}
.wacon-pill .dot{width:8px;height:8px;border-radius:50%;background:var(--mut-2);flex-shrink:0}
.wacon-pill.is-linked{background:var(--wa-50);border-color:#bfe6cd;color:var(--wa-d)}
.wacon-pill.is-linked .dot{background:var(--wa)}
.wacon-pill.is-pairing{background:var(--amber-50);border-color:#f3d9ab;color:#a15c06}
.wacon-pill.is-pairing .dot{background:var(--amber);animation:wacon-pulse 1.4s ease-in-out infinite}
.wacon-pill.is-blocked{background:var(--red-50);border-color:#f6c9c9;color:#b91c1c}
.wacon-pill.is-blocked .dot{background:var(--red)}
@keyframes wacon-pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.82)}}

.wacon-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:18px;align-items:start}
.wacon-card{background:#fff;border:1px solid var(--bd);border-radius:18px;padding:26px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.wacon-card h2{font-size:16px;font-weight:700;letter-spacing:-.01em;margin:0 0 6px}
.wacon-card .sub{font-size:13px;color:var(--mut);margin:0 0 20px;line-height:1.6}

/* ─────────── stage (QR / linked / offline) ─────────── */
.wacon-stage{display:flex;flex-direction:column;align-items:center;text-align:center;min-height:430px;justify-content:center}
.wacon-qr-frame{position:relative;background:#fff;border:1px solid var(--bd);border-radius:22px;padding:16px;box-shadow:0 18px 40px -24px rgba(15,23,42,.35)}
.wacon-qr-frame img{display:block;width:clamp(190px,44vw,252px);height:clamp(190px,44vw,252px);object-fit:contain;border-radius:6px}
/* Corner brackets — the "aim here" cue every scanner UI uses. */
.wacon-qr-frame::before,.wacon-qr-frame::after{content:'';position:absolute;width:26px;height:26px;border:3px solid var(--wa);border-radius:8px}
.wacon-qr-frame::before{inset-block-start:-4px;inset-inline-start:-4px;border-inline-end:none;border-block-end:none}
.wacon-qr-frame::after{inset-block-end:-4px;inset-inline-end:-4px;border-inline-start:none;border-block-start:none}
.wacon-qr-skeleton{width:clamp(190px,44vw,252px);height:clamp(190px,44vw,252px);border-radius:14px;background:linear-gradient(100deg,var(--soft) 30%,#eef2f5 50%,var(--soft) 70%);background-size:220% 100%;animation:wacon-shimmer 1.4s linear infinite;display:grid;place-items:center;color:var(--mut-2)}
@keyframes wacon-shimmer{from{background-position:180% 0}to{background-position:-40% 0}}

.wacon-orb{width:88px;height:88px;border-radius:50%;display:grid;place-items:center;font-size:38px;margin-bottom:18px;flex-shrink:0}
.wacon-orb.ok{background:var(--wa-50);color:var(--wa)}
.wacon-orb.off{background:var(--soft);color:var(--mut-2)}
.wacon-orb.bad{background:var(--red-50);color:var(--red)}
.wacon-stage h3{font-size:18px;font-weight:700;margin:0 0 6px;letter-spacing:-.01em}
.wacon-stage .stage-desc{font-size:13.5px;color:var(--mut);margin:0;max-width:40ch;line-height:1.6}
.wacon-number{font-size:22px;font-weight:800;letter-spacing:.01em;direction:ltr;unicode-bidi:isolate;margin-top:14px}
.wacon-stage-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:22px}

/* ─────────── steps ─────────── */
.wacon-steps{list-style:none;margin:0;padding:0;counter-reset:wacon}
.wacon-steps li{counter-increment:wacon;display:flex;gap:13px;padding:13px 0;border-block-end:1px solid var(--bd);font-size:13.5px;line-height:1.55}
.wacon-steps li:last-child{border-block-end:none;padding-block-end:0}
.wacon-steps li::before{content:counter(wacon);flex-shrink:0;width:26px;height:26px;border-radius:50%;background:var(--wa-50);color:var(--wa-d);font-size:12.5px;font-weight:800;display:grid;place-items:center}
.wacon-note{margin-top:16px;display:flex;gap:10px;align-items:flex-start;background:var(--soft);border-radius:12px;padding:12px 14px;font-size:12.5px;color:var(--mut);line-height:1.55}
.wacon-note i{color:var(--mut-2);font-size:15px;line-height:1.3}

/* ─────────── detail rows ─────────── */
.wacon-rows{display:flex;flex-direction:column;gap:2px}
.wacon-row{display:flex;align-items:center;gap:12px;justify-content:space-between;padding:12px 0;border-block-end:1px solid var(--bd);font-size:13.5px}
.wacon-row:last-child{border-block-end:none}
.wacon-row .k{color:var(--mut);flex-shrink:0}
.wacon-row .v{font-weight:600;text-align:end;word-break:break-word}
.wacon-row .v.num{direction:ltr;unicode-bidi:isolate}

.wacon-settings{margin-top:18px}
.wacon-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}

@media (max-width:900px){
  .wacon-grid{grid-template-columns:minmax(0,1fr)}
  .wacon-stage{min-height:0;padding-block:30px}
  .wacon-card{padding:20px;border-radius:16px}
  .wacon-pill{margin-inline-start:0}
  .wacon-head h1{font-size:19px}
}
@media (max-width:420px){
  .wacon-stage-actions .btn{flex:1 1 100%}
}
</style>
@endpush

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $inboxUrl    = \Illuminate\Support\Facades\Route::has($panelPrefix . '.inbox.index')
        ? route($panelPrefix . '.inbox.index')
        : null;

    // Built here rather than inline in the script: @json() takes a single
    // expression, not a multi-line array literal.
    $waI18n = [
        'linked'     => __('ui.wa_connect.linked_toast'),
        'unlinked'   => __('ui.wa_connect.unlinked'),
        'unlink_ask' => __('ui.wa_connect.unlink_confirm'),
        'refreshed'  => __('ui.wa_connect.status_refreshed'),
        'failed'     => __('ui.wa_connect.connect_failed'),
        'in_use'     => __('ui.wa_connect.phone_in_use'),
        'states'     => [
            'linked'  => __('ui.wa_connect.state_linked'),
            'pairing' => __('ui.wa_connect.state_pairing'),
            'offline' => __('ui.wa_connect.state_offline'),
            'blocked' => __('ui.wa_connect.state_blocked'),
        ],
    ];
@endphp

<div class="wacon" x-data="waConnect()" x-cloak>

    <div class="wacon-head">
        <div>
            <h1>{{ __('ui.wa_connect.title') }}</h1>
            <p>{{ __('ui.wa_connect.subtitle') }}</p>
        </div>
        <span class="wacon-pill" :class="pillClass" role="status" aria-live="polite">
            <span class="dot"></span><span x-text="pillLabel"></span>
        </span>
    </div>

    <div class="wacon-grid">

        {{-- ═══════════ STAGE — the one thing to do right now ═══════════ --}}
        <section class="wacon-card wacon-stage">

            {{-- Nothing created yet. One button: no name to invent, no form to
                 fill — the workspace name is already a fine label and it stays
                 editable below once the connection exists. --}}
            <template x-if="stage === 'none'">
                <div>
                    <div class="wacon-orb off"><i class="ri-whatsapp-line"></i></div>
                    <h3>{{ __('ui.wa_connect.none_title') }}</h3>
                    <p class="stage-desc">{{ __('ui.wa_connect.none_desc') }}</p>
                    <div class="wacon-stage-actions">
                        @if($canCreateInstance ?? true)
                            <button type="button" class="btn btn-primary" @click="startLinking()" :disabled="busy">
                                <i class="ri-qr-scan-2-line" :class="{ 'ri-loader-4-line ri-spin': busy }"></i>
                                {{ __('ui.wa_connect.none_cta') }}
                            </button>
                        @else
                            <p class="stage-desc">{{ __('ui.wa_connect.limit_reached') }}</p>
                        @endif
                    </div>
                </div>
            </template>

            {{-- The QR itself. The gateway does not hand back a code in the
                 connect response — it arrives on the qrcode.updated webhook a
                 second or two later — so a skeleton holds the same footprint
                 and the poll swaps the image in without the layout jumping. --}}
            <template x-if="stage === 'qr'">
                <div>
                    <h3>{{ __('ui.wa_connect.qr_title') }}</h3>
                    <p class="stage-desc" style="margin-bottom:20px">{{ __('ui.wa_connect.qr_desc') }}</p>

                    <div style="display:flex;justify-content:center">
                        <div class="wacon-qr-frame" x-show="hasQr">
                            <img :src="qrSrc" alt="{{ __('ui.wa_connect.qr_title') }}">
                        </div>
                        <div class="wacon-qr-skeleton" x-show="!hasQr">
                            <div>
                                <div class="spinner" style="width:2rem;height:2rem;border-width:3px;margin:0 auto .75rem"></div>
                                <div style="font-size:12.5px">{{ __('ui.wa_connect.qr_waiting') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="wacon-stage-actions">
                        <button type="button" class="btn btn-outline" @click="refreshQr()" :disabled="busy">
                            <i class="ri-refresh-line" :class="{ 'ri-loader-4-line ri-spin': busy }"></i>
                            {{ __('ui.wa_connect.qr_refresh') }}
                        </button>
                    </div>
                </div>
            </template>

            {{-- Linked. --}}
            <template x-if="stage === 'linked'">
                <div>
                    <div class="wacon-orb ok"><i class="ri-checkbox-circle-fill"></i></div>
                    <h3>{{ __('ui.wa_connect.linked_title') }}</h3>
                    <p class="stage-desc">{{ __('ui.wa_connect.linked_desc') }}</p>
                    <div class="wacon-number" x-text="prettyNumber || '{{ __('ui.wa_connect.field_syncing') }}'"></div>
                    <div class="wacon-stage-actions">
                        @if($inboxUrl)
                            <a href="{{ $inboxUrl }}" class="btn btn-primary">
                                <i class="ri-inbox-line"></i> {{ __('ui.wa_connect.open_inbox') }}
                            </a>
                        @endif
                        <button type="button" class="btn btn-outline" @click="refreshStatus(true)" :disabled="busy">
                            <i class="ri-refresh-line" :class="{ 'ri-loader-4-line ri-spin': busy }"></i>
                            {{ __('ui.wa_connect.refresh') }}
                        </button>
                    </div>
                </div>
            </template>

            {{-- Linked once, not any more: the phone was unlinked, went flat or
                 lost its session. Say so plainly and offer the one way back. --}}
            <template x-if="stage === 'offline'">
                <div>
                    <div class="wacon-orb off"><i class="ri-plug-line"></i></div>
                    <h3>{{ __('ui.wa_connect.offline_title') }}</h3>
                    <p class="stage-desc">{{ __('ui.wa_connect.offline_desc') }}</p>
                    <div class="wacon-stage-actions">
                        <button type="button" class="btn btn-primary" @click="refreshQr()" :disabled="busy">
                            <i class="ri-qr-scan-2-line" :class="{ 'ri-loader-4-line ri-spin': busy }"></i>
                            {{ __('ui.wa_connect.offline_cta') }}
                        </button>
                    </div>
                </div>
            </template>

            {{-- Banned by WhatsApp: a QR cannot fix it, so don't offer one. --}}
            <template x-if="stage === 'blocked'">
                <div>
                    <div class="wacon-orb bad"><i class="ri-forbid-2-line"></i></div>
                    <h3>{{ __('ui.wa_connect.blocked_title') }}</h3>
                    <p class="stage-desc">{{ __('ui.wa_connect.blocked_desc') }}</p>
                </div>
            </template>
        </section>

        {{-- ═══════════ SIDE — how to scan, or what is linked ═══════════ --}}
        <aside class="wacon-card">
            <template x-if="stage !== 'linked'">
                <div>
                    <h2>{{ __('ui.wa_connect.steps_title') }}</h2>
                    <p class="sub">{{ __('ui.wa_connect.qr_desc') }}</p>
                    <ol class="wacon-steps">
                        <li>{{ __('ui.wa_connect.step_1') }}</li>
                        <li>{{ __('ui.wa_connect.step_2') }}</li>
                        <li>{{ __('ui.wa_connect.step_3') }}</li>
                        <li>{{ __('ui.wa_connect.step_4') }}</li>
                    </ol>
                    <div class="wacon-note">
                        <i class="ri-information-line"></i>
                        <span>{{ __('ui.wa_connect.step_note') }}</span>
                    </div>
                </div>
            </template>

            <template x-if="stage === 'linked'">
                <div>
                    <h2>{{ __('ui.wa_connect.title') }}</h2>
                    <p class="sub">{{ __('ui.wa_connect.linked_desc') }}</p>
                    <div class="wacon-rows">
                        <div class="wacon-row">
                            <span class="k">{{ __('ui.wa_connect.field_number') }}</span>
                            <span class="v num" x-text="prettyNumber || '{{ __('ui.wa_connect.field_syncing') }}'"></span>
                        </div>
                        <div class="wacon-row">
                            <span class="k">{{ __('ui.wa_connect.field_name') }}</span>
                            <span class="v" x-text="name"></span>
                        </div>
                        <div class="wacon-row">
                            <span class="k">{{ __('ui.wa_connect.field_team') }}</span>
                            <span class="v">{{ $instance?->team?->name ?: __('ui.wa_connect.field_no_team') }}</span>
                        </div>
                        <div class="wacon-row">
                            <span class="k">{{ __('ui.wa_connect.field_last_message') }}</span>
                            <span class="v">{{ $instance?->last_message_at?->diffForHumans() ?: __('ui.wa_connect.never') }}</span>
                        </div>
                    </div>
                    <div class="wacon-note" style="margin-top:18px">
                        <i class="ri-information-line"></i>
                        <span>{{ __('ui.wa_connect.step_note') }}</span>
                    </div>
                    <button type="button" class="btn btn-outline" style="width:100%;margin-top:16px"
                            @click="unlink()" :disabled="busy">
                        <i class="ri-link-unlink" :class="{ 'ri-loader-4-line ri-spin': busy }"></i>
                        {{ __('ui.wa_connect.unlink') }}
                    </button>
                </div>
            </template>
        </aside>
    </div>

    {{-- ═══════════ SETTINGS — only once there is something to name ═══════════ --}}
    @if($instance)
    <section class="wacon-card wacon-settings">
        <h2>{{ __('ui.wa_connect.settings_title') }}</h2>
        <p class="sub">{{ __('ui.wa_connect.settings_desc') }}</p>

        <form method="POST" action="{{ route($panelPrefix . '.instances.update', $instance->id) }}">
            @csrf
            @method('PUT')
            <div class="wacon-form-grid">
                <div class="form-group" style="margin:0">
                    <label class="form-label" for="wacon_name">{{ __('ui.wa_connect.field_name') }}</label>
                    <input type="text" id="wacon_name" name="name" maxlength="100" required
                           value="{{ old('name', $instance->name) }}"
                           class="form-control @error('name') error @enderror" x-model="name">
                    @error('name') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label" for="wacon_team">{{ __('ui.wa_connect.field_team') }}</label>
                    <select id="wacon_team" name="team_id" class="form-control @error('team_id') error @enderror">
                        <option value="">{{ __('ui.wa_connect.field_no_team') }}</option>
                        @foreach($teams ?? [] as $team)
                            <option value="{{ $team->id }}" @selected(old('team_id', $instance->team_id) == $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                    @error('team_id') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div style="margin-top:18px">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.wa_connect.save') }}
                </button>
            </div>
        </form>
    </section>
    @endif
</div>

<script>
function waConnect() {
    return {
        /* Server truth at render time; every field below is kept current by the
           Reverb broadcast, with a poll as the fallback for a tab that never
           got the socket. */
        instanceId: @json($instance?->id),
        status:     @json($instance?->status ?? null),
        phone:      @json($instance?->phone_number ?? null),
        name:       @json($instance?->name ?? ''),
        qr:         @json($instance?->qr_code ?? null),

        tenantId:   @json(auth()->user()->tenant_id),
        createUrl:  @json(route($panelPrefix . '.instances.store')),
        defaultName:@json($defaultInstanceName ?? ''),
        i18n:       @json($waI18n),

        busy: false,
        poll: null,
        /* True from the moment a code is asked for until it is scanned or the
           request fails — what separates "no phone linked" from "code on
           screen", which the status column alone cannot say. */
        pairing: false,

        /* ── derived state ─────────────────────────────────────────────── */

        /* One of: none | qr | linked | offline | blocked. The whole page is a
           function of this, which is why nothing here is a boolean soup of
           showQr / isConnecting / hasError flags. */
        get stage() {
            if (!this.instanceId)              return 'none';
            if (this.status === 'connected')   return 'linked';
            if (this.status === 'banned')      return 'blocked';
            if (this.pairing)                  return 'qr';
            return 'offline';
        },

        get hasQr()   { return !!this.qr; },
        get qrSrc()   {
            if (!this.qr) return '';
            return this.qr.startsWith('data:image/')
                ? this.qr
                : 'data:image/png;base64,' + String(this.qr).replace(/\s+/g, '');
        },

        /* Digits only, then one leading + — the gateway hands the number back
           in several shapes (with @s.whatsapp.net, with or without the plus). */
        get prettyNumber() {
            if (!this.phone) return '';
            const d = String(this.phone).replace(/\D/g, '');
            return d ? '+' + d : '';
        },

        get pillLabel() {
            const s = this.stage;
            if (s === 'linked')  return this.i18n.states.linked;
            if (s === 'qr')      return this.i18n.states.pairing;
            if (s === 'blocked') return this.i18n.states.blocked;
            return this.i18n.states.offline;
        },

        get pillClass() {
            const s = this.stage;
            return s === 'linked'  ? 'is-linked'
                 : s === 'qr'      ? 'is-pairing'
                 : s === 'blocked' ? 'is-blocked' : '';
        },

        /* ── lifecycle ─────────────────────────────────────────────────── */

        init() {
            this.pairing = ['qr_pending', 'connecting'].includes(this.status);
            this.subscribe();

            /* This page exists to get a phone linked, so a workspace that is
               not linked lands straight on a live code rather than on a button
               that produces one. Already linked, or banned (where a code fixes
               nothing), and it just watches. */
            if (this.instanceId && this.status !== 'connected' && this.status !== 'banned') {
                this.refreshQr(true);
            } else if (this.instanceId) {
                this.startPoll(15000);
            }

            /* Back-button into a cached page can show a state that has since
               changed — re-read it rather than trusting the bfcache. */
            window.addEventListener('pageshow', (e) => { if (e.persisted) this.refreshStatus(); });
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && this.stage !== 'linked') this.refreshStatus();
            });
        },

        subscribe() {
            if (!window.Echo || !this.tenantId) return;
            window.Echo.private(`tenant.${this.tenantId}.instances`)
                .listen('.instance.status.changed', (e) => {
                    if (this.instanceId && e.id !== this.instanceId) return;
                    this.apply({ status: e.status, phone_number: e.phone_number });
                });
        },

        /* ── actions ───────────────────────────────────────────────────── */

        headers(json) {
            const h = { 'Accept': 'application/json' };
            if (json) h['Content-Type'] = 'application/json';
            const t = document.querySelector('meta[name=csrf-token]');
            if (t) h['X-CSRF-TOKEN'] = t.content;
            return h;
        },

        /* No instance yet: create one under the workspace name, then go
           straight for the code. Two calls, one click. */
        async startLinking() {
            if (this.busy) return;
            this.busy = true;
            try {
                const res = await fetch(this.createUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: this.headers(true),
                    body: JSON.stringify({ name: this.defaultName || 'WhatsApp' }),
                });
                if (!res.ok) throw new Error('create failed');
                const data = await res.json();
                this.instanceId = data.id;
                this.name       = data.name || this.defaultName;
                this.status     = data.status || 'disconnected';
            } catch (e) {
                window.showToast?.('error', this.i18n.failed);
                this.busy = false;
                return;
            }
            this.busy = false;
            await this.refreshQr(true);
        },

        /* Ask the gateway to (re)open a session and hand back a code. */
        async refreshQr(silent = false) {
            if (!this.instanceId || this.busy) return;
            this.busy = true;
            this.pairing = true;
            try {
                const res  = await fetch(`/api/instances/${this.instanceId}/connect`, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'connect failed');
                if (data.qr_code) this.qr = data.qr_code;
                if (data.status)  this.status = data.status;
                this.startPoll(3000);
            } catch (e) {
                this.pairing = false;
                if (!silent) window.showToast?.('error', this.i18n.failed);
            } finally {
                this.busy = false;
            }
        },

        async refreshStatus(announce = false) {
            if (!this.instanceId) return;
            if (announce) this.busy = true;
            try {
                const res  = await fetch(`/api/instances/${this.instanceId}/status`, {
                    credentials: 'same-origin', headers: this.headers(),
                });
                const data = await res.json().catch(() => ({}));

                /* The server tears the session down when the scanned number is
                   already linked to another workspace — say which, plainly,
                   instead of leaving the code spinning forever. */
                if (res.status === 409 && data.code === 'phone_already_used') {
                    this.apply({ status: 'disconnected', phone_number: null });
                    this.pairing = false;
                    this.stopPoll();
                    window.showToast?.('error', data.message || this.i18n.in_use);
                    return;
                }
                if (!res.ok) return;

                this.apply(data);
                if (announce) window.showToast?.('success', this.i18n.refreshed);
            } catch { /* transient — the next tick retries */ }
            finally { if (announce) this.busy = false; }
        },

        async unlink() {
            if (!this.instanceId || this.busy) return;
            if (!window.confirm(this.i18n.unlink_ask)) return;
            this.busy = true;
            try {
                const res = await fetch(`/api/instances/${this.instanceId}/logout`, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(),
                });
                if (!res.ok) throw new Error();
                this.apply({ status: 'disconnected', phone_number: null });
                this.qr = null;
                this.pairing = false;
                this.stopPoll();
                window.showToast?.('success', this.i18n.unlinked);
            } catch {
                window.showToast?.('error', this.i18n.failed);
            } finally {
                this.busy = false;
            }
        },

        /* ── shared state transition ───────────────────────────────────── */

        apply(data) {
            const wasLinked = this.status === 'connected';

            if (data.status) this.status = data.status;
            if ('phone_number' in data) this.phone = data.phone_number;
            /* A code only ever replaces a code — never blank one out mid-scan
               because a status poll happened not to carry one. */
            if (data.qr_code) this.qr = data.qr_code;

            if (this.status === 'connected') {
                this.pairing = false;
                this.qr      = null;
                this.stopPoll();
                this.startPoll(30000);
                if (!wasLinked) window.showToast?.('success', this.i18n.linked);
            }
        },

        startPoll(every) {
            this.stopPoll();
            this.poll = setInterval(() => this.refreshStatus(), every);
        },

        stopPoll() {
            if (this.poll) clearInterval(this.poll);
            this.poll = null;
        },
    };
}
</script>
@endsection
