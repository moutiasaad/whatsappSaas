@extends('layouts.admin')

@section('title', __('ui.get_started.title'))

@section('breadcrumb')
    <span>{{ __('ui.get_started.breadcrumb') }}</span>
@endsection

@push('styles')
<style>
/* ════════════════════════════════════════════════════════════════
   GET STARTED — the three things a new workspace has to do, in the
   order they depend on each other. Scoped under .gs; logical
   properties throughout, since this panel is served in Arabic too.
════════════════════════════════════════════════════════════════ */
.gs{
  --teal:#0f7e7a;--teal-l:#15b6a8;--teal-50:#ecf7f6;--teal-100:#d6efed;
  --bd:#e6ebf0;--bd-2:#cbd5e1;--soft:#f7f9fa;--soft-2:#eef2f5;
  --mut:#64748b;--mut-2:#94a3b8;--txt:#0f172a;
  --amber:#d97706;--amber-50:#fef3e2;--amber-100:#fcd9a4;--wa:#25a35a;
}

/* ─── header ─── */
.gs-head{display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
.gs-head h1{font-size:26px;font-weight:700;letter-spacing:-.03em;margin:0}
.gs-head p{font-size:14px;color:var(--mut);margin:5px 0 0;max-width:62ch}
.gs-head .m{flex:1;min-width:260px}
.gs-ring{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--bd);border-radius:14px;padding:11px 16px 11px 12px}
.gs-ring .t{font-size:13.5px;font-weight:700}
.gs-ring .s{font-size:12px;color:var(--mut)}

.gs-grid{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:22px;align-items:start}
.gs-steps{display:flex;flex-direction:column;gap:14px;min-width:0}

/* ─── step card ─── */
.st{background:#fff;border:1px solid var(--bd);border-radius:16px;transition:border-color .15s,box-shadow .15s;min-width:0}
.st.open{border-color:var(--teal-100);box-shadow:0 18px 40px -26px rgba(15,126,122,.35)}
.sh{display:flex;align-items:center;gap:14px;padding:18px 20px;width:100%;text-align:start;background:none;border:none;cursor:pointer;font-family:inherit;color:inherit}
.num{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;font-size:15px;font-weight:800;flex-shrink:0;background:var(--soft-2);color:var(--mut)}
.st.open .num{background:var(--teal);color:#fff}
.st.done .num{background:var(--teal-50);color:var(--teal)}
.sh .m{flex:1;min-width:0}
.sh .h{font-size:16px;font-weight:700;letter-spacing:-.015em;display:block}
.sh .d{font-size:12.5px;color:var(--mut);margin-top:2px;display:block}
.sh .chev{color:var(--mut-2);transition:transform .18s;flex-shrink:0;display:grid}
.st.open .sh .chev{transform:rotate(180deg)}
.sb{padding:0 20px 20px;padding-inline-start:72px}
.pill{font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:4px 9px;border-radius:6px;white-space:nowrap;flex-shrink:0}
.pill.req{background:var(--amber-50);color:var(--amber)}
.pill.ok{background:var(--teal-50);color:var(--teal)}
.pill.lk{background:var(--soft-2);color:var(--mut)}

/* ─── step 1 ─── */
.two{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:24px;align-items:center}
.howto{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:13px;counter-reset:h}
.howto li{display:flex;gap:11px;font-size:13.5px;line-height:1.5;counter-increment:h}
.howto li::before{content:counter(h);width:22px;height:22px;border-radius:7px;background:var(--teal-50);color:var(--teal);font-size:12px;font-weight:800;display:grid;place-items:center;flex-shrink:0}
.qr{position:relative;border:1px solid var(--bd);border-radius:14px;padding:14px;background:#fff}
.qr img{display:block;width:100%;height:auto;border-radius:4px}
.qr-skel{width:100%;aspect-ratio:1;border-radius:10px;background:linear-gradient(100deg,var(--soft) 30%,#eef2f5 50%,var(--soft) 70%);background-size:220% 100%;animation:gs-sh 1.4s linear infinite;display:grid;place-items:center;color:var(--mut-2);font-size:12px;text-align:center;padding:12px}
@keyframes gs-sh{from{background-position:180% 0}to{background-position:-40% 0}}
.qrmeta{font-size:11.5px;color:var(--mut);text-align:center;margin-top:8px}
.acts{display:flex;gap:9px;margin-top:18px;flex-wrap:wrap;align-items:center}
.link{font-size:13px;font-weight:600;color:var(--mut);background:none;border:none;cursor:pointer;font-family:inherit}
.link:hover{color:var(--txt)}
.okrow{display:flex;align-items:center;gap:13px;background:var(--teal-50);border:1px solid var(--teal-100);border-radius:13px;padding:14px 16px}
.okrow .ic{width:38px;height:38px;border-radius:11px;background:var(--wa);display:grid;place-items:center;flex-shrink:0;color:#fff;font-size:19px}
.okrow .m{flex:1;min-width:0}
.okrow .t{font-size:14px;font-weight:700}
.okrow .s{font-size:12.5px;color:var(--mut);margin-top:1px}
.okrow .num-txt{direction:ltr;unicode-bidi:isolate}

/* ─── step 2 ─── */
.tabs{display:inline-flex;background:var(--soft-2);border-radius:10px;padding:3px;gap:2px;margin-bottom:14px}
.tabs button{padding:7px 13px;border-radius:8px;font-size:12.5px;font-weight:600;color:var(--mut);background:none;border:none;cursor:pointer;font-family:inherit}
.tabs button.on{background:#fff;color:var(--txt);box-shadow:0 1px 3px rgba(13,20,23,.1)}
.qq{display:flex;flex-direction:column;gap:12px}
.qi{border:1px solid var(--bd);border-radius:13px;padding:14px 15px;transition:border-color .15s}
.qi.ok{border-color:var(--teal-100)}
.qi .ql{display:flex;align-items:center;gap:10px;font-size:13.5px;font-weight:700;margin-bottom:10px}
.qi .qn{width:22px;height:22px;border-radius:7px;background:var(--soft-2);color:var(--mut);font-size:12px;font-weight:800;display:grid;place-items:center;flex-shrink:0}
.qi.ok .qn{background:var(--teal);color:#fff}
.gs-inp{width:100%;height:42px;border:1px solid var(--bd);border-radius:10px;padding:0 13px;font-size:14px;outline:none;transition:.15s;color:var(--txt);background:#fff;font-family:inherit}
.gs-inp:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.sug{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
.chipb{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:6px 11px;border-radius:999px;border:1px dashed var(--bd-2);color:var(--txt);background:#fff;transition:.12s;cursor:pointer;font-family:inherit}
.chipb:hover{border-color:var(--teal);color:var(--teal);background:var(--teal-50)}
.chipb.used{border-style:solid;border-color:var(--teal-100);background:var(--teal-50);color:var(--teal)}
.sug .chipb{font-size:12px;padding:5px 10px}
textarea.kb{width:100%;min-height:190px;border:1px solid var(--bd);border-radius:12px;padding:13px 14px;font-size:13.5px;line-height:1.6;resize:vertical;outline:none;font-family:inherit;color:var(--txt)}
textarea.kb:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.count{font-size:12.5px;color:var(--mut);display:flex;align-items:center;gap:7px}
.count b{color:var(--txt)}
.meter{height:6px;border-radius:99px;background:var(--soft-2);overflow:hidden;margin-top:10px}
.meter i{display:block;height:100%;border-radius:99px;background:var(--teal);transition:width .4s}
.lockp{display:flex;gap:14px;align-items:flex-start;background:var(--soft);border:1px solid var(--bd);border-radius:13px;padding:17px}
.lockp .ic{width:40px;height:40px;border-radius:11px;background:#fff;border:1px solid var(--bd);display:grid;place-items:center;flex-shrink:0;color:var(--mut);font-size:19px}
.lockp .t{font-size:14.5px;font-weight:700}
.lockp .s{font-size:13px;color:var(--mut);margin-top:4px;line-height:1.55;max-width:56ch}

/* ─── step 3 ─── */
.modes{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.mode{border:1.5px solid var(--bd);border-radius:13px;padding:14px;text-align:start;transition:.13s;background:#fff;cursor:pointer;font-family:inherit;color:inherit}
.mode:hover{border-color:var(--bd-2)}
.mode.on{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.mode .t{font-size:14px;font-weight:700;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.mode .s{font-size:12px;color:var(--mut);margin-top:5px;line-height:1.45;display:block}
.lbl2{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);margin:18px 0 9px}
.kw{display:flex;gap:6px;flex-wrap:wrap}
.kw span{font-size:12.5px;font-weight:600;background:var(--amber-50);color:#92400e;border:1px solid var(--amber-100);padding:5px 10px;border-radius:7px}
.actrow{display:flex;align-items:center;gap:14px;margin-top:18px;padding:15px 16px;border-radius:13px;border:1px solid var(--bd);background:var(--soft)}
.actrow.on{background:var(--teal-50);border-color:var(--teal-100)}
.actrow .m{flex:1;min-width:0}
.actrow .t{font-size:14px;font-weight:700}
.actrow .s{font-size:12.5px;color:var(--mut);margin-top:2px}
.sw{width:42px;height:24px;border-radius:999px;background:var(--bd-2);position:relative;flex-shrink:0;transition:.18s;border:none;cursor:pointer;padding:0}
.sw::after{content:"";position:absolute;top:3px;inset-inline-start:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.18s;box-shadow:0 1px 3px rgba(13,20,23,.2)}
.sw.on{background:var(--teal)}
html:not([dir="rtl"]) .sw.on::after{transform:translateX(18px)}
html[dir="rtl"] .sw.on::after{transform:translateX(-18px)}

/* ─── side ─── */
.gs-side{position:sticky;top:16px;display:flex;flex-direction:column;gap:14px}
.sc{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:18px}
.sc h3{font-size:14.5px;font-weight:700;margin:0 0 4px;letter-spacing:-.01em}
.sc p{font-size:12.5px;color:var(--mut);margin:0;line-height:1.55}
.chain{display:flex;flex-direction:column;margin-top:15px}
.cn{display:flex;gap:12px;align-items:flex-start;position:relative;padding-bottom:16px}
.cn:last-child{padding-bottom:0}
.cn:not(:last-child)::after{content:"";position:absolute;inset-inline-start:15px;top:32px;bottom:2px;width:2px;background:var(--bd)}
.cn.ok:not(:last-child)::after{background:var(--teal-100)}
.cn .d{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;background:var(--soft-2);color:var(--mut);font-size:15px}
.cn.ok .d{background:var(--teal);color:#fff}
.cn .t{font-size:13px;font-weight:700;margin-top:2px}
.cn .s{font-size:12px;color:var(--mut);margin-top:1px;line-height:1.45}
.sclinks{display:flex;flex-direction:column;gap:2px;margin-top:12px}
.sclinks a{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:600;color:var(--txt);padding:8px 10px;border-radius:9px;transition:.12s}
.sclinks a:hover{background:var(--soft-2)}
.sclinks a i{color:var(--mut)}

/* ─── ready banner ─── */
.ready{display:flex;align-items:center;gap:16px;background:linear-gradient(100deg,var(--teal-50),#fff 70%);border:1px solid var(--teal-100);border-radius:16px;padding:20px 22px;flex-wrap:wrap}
.ready .ic{width:46px;height:46px;border-radius:13px;background:var(--teal);display:grid;place-items:center;flex-shrink:0;color:#fff;font-size:24px}
.ready .m{flex:1;min-width:180px}
.ready .t{font-size:17px;font-weight:700;letter-spacing:-.015em}
.ready .s{font-size:13px;color:var(--mut);margin-top:2px}

@media (max-width:1080px){
  .gs-grid{grid-template-columns:minmax(0,1fr)}
  .gs-side{position:static}
}
@media (max-width:900px){
  .sb{padding:0 16px 18px}
  .sh{padding:15px 16px}
  .two{grid-template-columns:1fr}
  .qr{max-width:240px;margin-inline:auto}
  .modes{grid-template-columns:1fr}
  .gs-head h1{font-size:21px}
}
@media (max-width:480px){
  .acts .btn{flex:1 1 100%}
}
</style>
@endpush

@section('content')
@php
    $prefix   = auth()->user()->routeNamePrefix();
    $has      = fn (string $n) => \Illuminate\Support\Facades\Route::has($prefix . '.' . $n);
    $sug      = fn (string $k) => array_filter(array_map('trim', explode('|', __('ui.get_started.' . $k))));

    $questions = [
        ['key' => 'business', 'q' => __('ui.get_started.q_business'), 'ph' => __('ui.get_started.q_business_ph'), 'sug' => []],
        ['key' => 'delivery', 'q' => __('ui.get_started.q_delivery'), 'ph' => __('ui.get_started.q_delivery_ph'), 'sug' => $sug('sug_delivery')],
        ['key' => 'returns',  'q' => __('ui.get_started.q_returns'),  'ph' => __('ui.get_started.q_returns_ph'),  'sug' => $sug('sug_returns')],
        ['key' => 'hours',    'q' => __('ui.get_started.q_hours'),    'ph' => __('ui.get_started.q_hours_ph'),    'sug' => $sug('sug_hours')],
    ];

    $modes = ['suggestion', 'hybrid', 'autonomous'];

    $gsI18n = [
        'failed'  => __('ui.get_started.step1_failed'),
        'blocked' => __('ui.get_started.step1_blocked'),
        'saved'   => __('ui.get_started.saved'),
        'quick'   => __('ui.get_started.kb_count_quick'),
        'paste'   => __('ui.get_started.kb_count_paste'),
        'empty'   => __('ui.get_started.kb_count_empty'),
        'modes'   => [
            'suggestion' => __('ui.get_started.mode_suggestion'),
            'hybrid'     => __('ui.get_started.mode_hybrid'),
            'autonomous' => __('ui.get_started.mode_autonomous'),
        ],
    ];
@endphp

<div class="gs" x-data="getStarted()" x-cloak>

    <div class="gs-head">
        <div class="m">
            <h1>{{ __('ui.get_started.heading', ['app' => config('app.name', 'Wavadesk'), 'name' => $tenant->name]) }}</h1>
            <p>{{ __('ui.get_started.subtitle') }}</p>
        </div>

        <div class="gs-ring">
            {{-- Ring is drawn from `done`, so it moves the moment a step does. --}}
            <svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true">
                <circle cx="23" cy="23" r="19" stroke="#eef2f5" stroke-width="5" fill="none"></circle>
                <circle cx="23" cy="23" r="19" stroke="#0f7e7a" stroke-width="5" fill="none" stroke-linecap="round"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="circumference * (1 - done / 3)"
                        transform="rotate(-90 23 23)" style="transition:stroke-dashoffset .5s"></circle>
                <text x="23" y="27.5" text-anchor="middle" font-size="12.5" font-weight="800" fill="#0f172a"
                      x-text="done + '/3'"></text>
            </svg>
            <div>
                <div class="t" x-text="done === 3 ? @js(__('ui.get_started.progress_done')) : @js(__('ui.get_started.progress_title'))"></div>
                <div class="s" x-text="done === 3
                    ? @js(__('ui.get_started.progress_ready'))
                    : @js(__('ui.get_started.steps_left')).replace(':n', 3 - done)"></div>
            </div>
        </div>

        {{-- Skip is a plain form: leaving setup should work with JS broken. --}}
        <form method="POST" action="{{ route($prefix . '.onboarding.skip') }}">
            @csrf
            <button type="submit" class="btn btn-outline">
                <i class="ri-close-line"></i> {{ __('ui.get_started.skip_all') }}
            </button>
        </form>
    </div>

    <div class="gs-grid">
        <div class="gs-steps">

            {{-- ═══ everything done ═══ --}}
            <template x-if="done === 3">
                <div class="ready">
                    <span class="ic"><i class="ri-check-line"></i></span>
                    <div class="m">
                        <div class="t">{{ __('ui.get_started.ready_title') }}</div>
                        <div class="s">{{ __('ui.get_started.ready_desc') }}</div>
                    </div>
                    @if($has('users.index'))
                        <a href="{{ route($prefix . '.users.index') }}" class="btn btn-outline">{{ __('ui.get_started.ready_team') }}</a>
                    @endif
                    @if($has('inbox.index'))
                        <a href="{{ route($prefix . '.inbox.index') }}" class="btn btn-primary">{{ __('ui.get_started.ready_inbox') }}</a>
                    @endif
                </div>
            </template>

            {{-- Linked but deliberately without the AI: say that plainly rather
                 than leaving a half-finished checklist nagging. --}}
            <template x-if="done < 3 && wa.status === 'connected' && kbSkipped && kb.count === 0">
                <div class="ready" style="background:#fff;border-color:var(--bd)">
                    <span class="ic" style="background:var(--soft-2);color:var(--mut)"><i class="ri-check-line"></i></span>
                    <div class="m">
                        <div class="t">{{ __('ui.get_started.partial_title') }}</div>
                        <div class="s">{{ __('ui.get_started.partial_desc') }}</div>
                    </div>
                    <button type="button" class="btn btn-outline" @click="kbSkipped = false; open = 2">{{ __('ui.get_started.kb_add_now') }}</button>
                    @if($has('inbox.index'))
                        <a href="{{ route($prefix . '.inbox.index') }}" class="btn btn-primary">{{ __('ui.get_started.ready_inbox') }}</a>
                    @endif
                </div>
            </template>

            {{-- ═══════════ STEP 1 — WhatsApp ═══════════ --}}
            <section class="st" :class="{ open: open === 1, done: step1 }">
                <button type="button" class="sh" @click="toggle(1)">
                    <span class="num">
                        <template x-if="step1"><i class="ri-check-line"></i></template>
                        <template x-if="!step1"><span>1</span></template>
                    </span>
                    <span class="m">
                        <span class="h">{{ __('ui.get_started.step1_title') }}</span>
                        <span class="d">{{ __('ui.get_started.step1_desc') }}</span>
                    </span>
                    <span class="pill" :class="step1 ? 'ok' : 'req'"
                          x-text="step1 ? @js(__('ui.get_started.done')) : @js(__('ui.get_started.required'))"></span>
                    <span class="chev"><i class="ri-arrow-down-s-line"></i></span>
                </button>

                <div class="sb" x-show="open === 1" x-collapse>
                    {{-- connected --}}
                    <template x-if="wa.status === 'connected'">
                        <div>
                            <div class="okrow">
                                <span class="ic"><i class="ri-check-line"></i></span>
                                <div class="m">
                                    <div class="t num-txt" x-text="prettyNumber || @js(__('ui.get_started.step1_syncing'))"></div>
                                    <div class="s">{{ __('ui.get_started.step1_connected_desc') }}</div>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm" @click="unlink()" :disabled="wa.busy">
                                    {{ __('ui.get_started.step1_disconnect') }}
                                </button>
                            </div>
                            <div class="acts">
                                <button type="button" class="btn btn-primary" @click="open = 2">{{ __('ui.get_started.step1_continue') }}</button>
                            </div>
                        </div>
                    </template>

                    {{-- not connected: the QR is the page --}}
                    <template x-if="wa.status !== 'connected'">
                        <div class="two">
                            <div>
                                <ol class="howto">
                                    <li><span>{{ __('ui.get_started.step1_how1') }}</span></li>
                                    <li><span>{{ __('ui.get_started.step1_how2') }}</span></li>
                                    <li><span>{{ __('ui.get_started.step1_how3') }}</span></li>
                                </ol>
                                <div class="acts">
                                    <button type="button" class="btn btn-outline" @click="startWa()" :disabled="wa.busy">
                                        <i :class="wa.busy ? 'ri-loader-4-line ri-spin' : 'ri-refresh-line'"></i>
                                        <span x-text="wa.qr ? @js(__('ui.get_started.step1_refresh')) : @js(__('ui.get_started.step1_start'))"></span>
                                    </button>
                                    <button type="button" class="link" @click="open = 2">{{ __('ui.get_started.step1_later') }}</button>
                                </div>
                                <div class="count" style="margin-top:12px" x-show="wa.error" x-text="wa.error"></div>
                            </div>
                            <div>
                                <div class="qr">
                                    <template x-if="wa.qr">
                                        <img :src="qrSrc" alt="{{ __('ui.get_started.step1_title') }}">
                                    </template>
                                    <template x-if="!wa.qr">
                                        <div class="qr-skel" x-text="wa.busy || wa.pairing
                                            ? @js(__('ui.get_started.step1_preparing'))
                                            : @js(__('ui.get_started.step1_start'))"></div>
                                    </template>
                                </div>
                                <div class="qrmeta" x-show="wa.qr">{{ __('ui.get_started.step1_waiting') }}</div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            {{-- ═══════════ STEP 2 — knowledge base ═══════════ --}}
            <section class="st" :class="{ open: open === 2, done: step2 }">
                <button type="button" class="sh" @click="toggle(2)">
                    <span class="num">
                        <template x-if="step2"><i class="ri-check-line"></i></template>
                        <template x-if="!step2"><span>2</span></template>
                    </span>
                    <span class="m">
                        <span class="h">{{ __('ui.get_started.step2_title') }}</span>
                        <span class="d">{{ __('ui.get_started.step2_desc') }}</span>
                    </span>
                    <span class="pill" :class="step2 ? 'ok' : (kbSkipped ? 'lk' : 'req')"
                          x-text="step2 ? @js(__('ui.get_started.done'))
                                  : (kbSkipped ? @js(__('ui.get_started.skipped')) : @js(__('ui.get_started.required')))"></span>
                    <span class="chev"><i class="ri-arrow-down-s-line"></i></span>
                </button>

                <div class="sb" x-show="open === 2" x-collapse>
                    {{-- saved --}}
                    <template x-if="kb.count > 0">
                        <div>
                            <div class="okrow" style="background:#fff;border-color:var(--bd)">
                                <span class="ic" style="background:var(--teal)"><i class="ri-book-2-line"></i></span>
                                <div class="m">
                                    <div class="t" x-text="@js(__('ui.get_started.kb_saved')).replace(':n', kb.count)"></div>
                                    <div class="s" x-text="kb.count < 5 ? @js(__('ui.get_started.kb_saved_thin')) : @js(__('ui.get_started.kb_saved_good'))"></div>
                                    <div class="meter"><i :style="`width:${Math.min(100, Math.round(kb.count / 10 * 100))}%`"></i></div>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm" @click="kb.count = 0">{{ __('ui.get_started.kb_add_more') }}</button>
                            </div>
                            <div class="acts">
                                <button type="button" class="btn btn-primary" @click="open = 3">{{ __('ui.get_started.kb_continue') }}</button>
                            </div>
                        </div>
                    </template>

                    {{-- skipped --}}
                    <template x-if="kb.count === 0 && kbSkipped">
                        <div class="lockp">
                            <span class="ic"><i class="ri-book-2-line"></i></span>
                            <div>
                                <div class="t">{{ __('ui.get_started.kb_skipped_title') }}</div>
                                <div class="s">{{ __('ui.get_started.kb_skipped_desc') }}</div>
                                <div style="margin-top:13px">
                                    <button type="button" class="btn btn-primary btn-sm" @click="kbSkipped = false">{{ __('ui.get_started.kb_add_now') }}</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- the form --}}
                    <template x-if="kb.count === 0 && !kbSkipped">
                        <div>
                            <div class="tabs">
                                <button type="button" :class="{ on: kb.tab === 'quick' }" @click="kb.tab = 'quick'">{{ __('ui.get_started.tab_quick') }}</button>
                                <button type="button" :class="{ on: kb.tab === 'paste' }" @click="kb.tab = 'paste'">{{ __('ui.get_started.tab_paste') }}</button>
                            </div>

                            <div x-show="kb.tab === 'quick'">
                                <p class="count" style="margin:-4px 0 14px">{{ __('ui.get_started.quick_hint') }}</p>
                                <div class="qq">
                                    @foreach($questions as $i => $q)
                                        <div class="qi" :class="{ ok: (kb.q.{{ $q['key'] }} || '').trim() !== '' }">
                                            <div class="ql">
                                                <span class="qn" x-text="(kb.q.{{ $q['key'] }} || '').trim() !== '' ? '✓' : '{{ $i + 1 }}'"></span>
                                                {{ $q['q'] }}
                                            </div>
                                            <input type="text" class="gs-inp" maxlength="2000"
                                                   x-model="kb.q.{{ $q['key'] }}"
                                                   placeholder="{{ $q['ph'] }}">
                                            @if($q['sug'])
                                                <div class="sug">
                                                    @foreach($q['sug'] as $s)
                                                        <button type="button" class="chipb"
                                                                @click="kb.q.{{ $q['key'] }} = @js($s)">{{ $s }}</button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    {{-- payments: multi-select, so chips are the whole control --}}
                                    <div class="qi" :class="{ ok: kb.q.payments.length > 0 }">
                                        <div class="ql">
                                            <span class="qn" x-text="kb.q.payments.length ? '✓' : '5'"></span>
                                            {{ __('ui.get_started.q_payments') }}
                                        </div>
                                        <div class="sug" style="margin-top:0">
                                            @foreach($sug('sug_payments') as $p)
                                                <button type="button" class="chipb"
                                                        :class="{ used: kb.q.payments.includes(@js($p)) }"
                                                        @click="togglePayment(@js($p))">
                                                    <span x-show="kb.q.payments.includes(@js($p))">✓</span>{{ $p }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div x-show="kb.tab === 'paste'">
                                <textarea class="kb" x-model="kb.faq" maxlength="20000"
                                          placeholder="{{ __('ui.get_started.paste_ph') }}"></textarea>
                                <p class="count" style="margin-top:9px">{{ __('ui.get_started.paste_hint') }}</p>
                            </div>

                            <div class="acts">
                                <button type="button" class="btn btn-primary" @click="saveKb()" :disabled="kb.busy || !kbReady">
                                    <i :class="kb.busy ? 'ri-loader-4-line ri-spin' : 'ri-book-2-line'"></i>
                                    <span x-text="kb.tab === 'quick' ? @js(__('ui.get_started.kb_save_quick')) : @js(__('ui.get_started.kb_save_paste'))"></span>
                                </button>
                                <span class="count" x-html="kbCountLabel"></span>
                                <button type="button" class="link" style="margin-inline-start:auto" @click="skipKb()">{{ __('ui.get_started.kb_skip') }}</button>
                            </div>
                            <div class="count" style="margin-top:10px" x-show="kb.error" x-text="kb.error"></div>
                        </div>
                    </template>
                </div>
            </section>

            {{-- ═══════════ STEP 3 — AI agent ═══════════ --}}
            <section class="st" :class="{ open: open === 3, done: step3 }">
                <button type="button" class="sh" @click="toggle(3)">
                    <span class="num">
                        <template x-if="step3"><i class="ri-check-line"></i></template>
                        <template x-if="!step3 && kb.count === 0"><i class="ri-lock-line"></i></template>
                        <template x-if="!step3 && kb.count > 0"><span>3</span></template>
                    </span>
                    <span class="m">
                        <span class="h">{{ __('ui.get_started.step3_title') }}</span>
                        <span class="d">{{ __('ui.get_started.step3_desc') }}</span>
                    </span>
                    <span class="pill" :class="step3 ? 'ok' : (kb.count === 0 ? 'lk' : 'req')"
                          x-text="step3 ? @js(__('ui.get_started.done'))
                                  : (kb.count === 0 ? @js(__('ui.get_started.needs_step_2')) : @js(__('ui.get_started.required')))"></span>
                    <span class="chev"><i class="ri-arrow-down-s-line"></i></span>
                </button>

                <div class="sb" x-show="open === 3" x-collapse>
                    @if(!$canUseAi)
                        <div class="lockp">
                            <span class="ic"><i class="ri-lock-line"></i></span>
                            <div>
                                <div class="t">{{ __('ui.get_started.ai_no_plan') }}</div>
                                @if($has('billing.index'))
                                    <div style="margin-top:13px">
                                        <a href="{{ route($prefix . '.billing.index') }}" class="btn btn-primary btn-sm">{{ __('ui.sidebar.billing') }}</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <template x-if="kb.count === 0">
                            <div class="lockp">
                                <span class="ic"><i class="ri-lock-line"></i></span>
                                <div>
                                    <div class="t">{{ __('ui.get_started.ai_locked_title') }}</div>
                                    <div class="s">{{ __('ui.get_started.ai_locked_desc') }}</div>
                                    <div style="margin-top:13px">
                                        <button type="button" class="btn btn-primary btn-sm" @click="open = 2">{{ __('ui.get_started.ai_goto_kb') }}</button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="kb.count > 0">
                            <div>
                                <div class="modes">
                                    @foreach($modes as $m)
                                        <button type="button" class="mode" :class="{ on: ai.mode === @js($m) }" @click="pickMode(@js($m))">
                                            <span class="t">
                                                {{ __('ui.get_started.mode_' . $m) }}
                                                @if($m === 'suggestion')
                                                    <span class="pill ok" style="font-size:9.5px;padding:3px 7px">{{ __('ui.get_started.recommended') }}</span>
                                                @endif
                                            </span>
                                            <span class="s">{{ __('ui.get_started.mode_' . $m . '_desc') }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="count" style="margin-top:10px;color:#92400e" x-show="ai.mode === 'autonomous'">
                                    {{ __('ui.get_started.auto_tip') }}
                                </div>

                                <div class="lbl2">{{ __('ui.get_started.handover') }}</div>
                                <div class="kw">
                                    @foreach(['human', 'agent', 'supervisor'] as $kw)
                                        <span>{{ $kw }}</span>
                                    @endforeach
                                </div>

                                <div class="actrow" :class="{ on: step3 }">
                                    <div class="m">
                                        <div class="t" x-text="step3 ? @js(__('ui.get_started.ai_on')) : @js(__('ui.get_started.ai_off'))"></div>
                                        <div class="s" x-text="step3
                                            ? @js(__('ui.get_started.ai_on_desc')).replace(':n', kb.count).replace(':mode', modeLabel)
                                            : @js(__('ui.get_started.ai_off_desc'))"></div>
                                    </div>
                                    <button type="button" class="sw" :class="{ on: step3 }" role="switch"
                                            :aria-checked="step3 ? 'true' : 'false'" @click="toggleAi()" :disabled="ai.busy"></button>
                                </div>
                                <div class="count" style="margin-top:10px" x-show="ai.error" x-text="ai.error"></div>
                            </div>
                        </template>
                    @endif
                </div>
            </section>
        </div>

        {{-- ═══════════ SIDE ═══════════ --}}
        <aside class="gs-side">
            <div class="sc">
                <h3>{{ __('ui.get_started.why_title') }}</h3>
                <p>{{ __('ui.get_started.why_desc') }}</p>
                <div class="chain">
                    <div class="cn" :class="{ ok: step1 }">
                        <span class="d"><i :class="step1 ? 'ri-check-line' : 'ri-whatsapp-line'"></i></span>
                        <div>
                            <div class="t">{{ __('ui.get_started.chain1') }}</div>
                            <div class="s">{{ __('ui.get_started.chain1_desc') }}</div>
                        </div>
                    </div>
                    <div class="cn" :class="{ ok: step2 }">
                        <span class="d"><i :class="step2 ? 'ri-check-line' : 'ri-book-2-line'"></i></span>
                        <div>
                            <div class="t">{{ __('ui.get_started.chain2') }}</div>
                            <div class="s">{{ __('ui.get_started.chain2_desc') }}</div>
                        </div>
                    </div>
                    <div class="cn" :class="{ ok: step3 }">
                        <span class="d"><i :class="step3 ? 'ri-check-line' : 'ri-flashlight-line'"></i></span>
                        <div>
                            <div class="t">{{ __('ui.get_started.chain3') }}</div>
                            <div class="s">{{ __('ui.get_started.chain3_desc') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sc">
                <h3>{{ __('ui.get_started.full_title') }}</h3>
                <p>{{ __('ui.get_started.full_desc') }}</p>
                <div class="sclinks">
                    @if($has('instances.index'))
                        <a href="{{ route($prefix . '.instances.index') }}"><i class="ri-whatsapp-line"></i>{{ __('ui.sidebar.whatsapp_instances') }}</a>
                    @endif
                    @if($has('knowledge.index'))
                        <a href="{{ route($prefix . '.knowledge.index') }}"><i class="ri-book-2-line"></i>{{ __('ui.sidebar.knowledge_base') }}</a>
                    @endif
                    @if($has('ai-settings.index'))
                        <a href="{{ route($prefix . '.ai-settings.index') }}"><i class="ri-flashlight-line"></i>{{ __('ui.sidebar.ai_agent') }}</a>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
function getStarted() {
    return {
        /* Server truth at render time. Each step's "done" is read from the row
           it writes, never from a progress flag, so finishing one on its own
           page counts here too. */
        wa: {
            id:      @json($instance?->id),
            status:  @json($instance?->status ?? null),
            phone:   @json($instance?->phone_number ?? null),
            qr:      null,
            busy:    false,
            pairing: false,
            error:   '',
        },
        kb: {
            tab:   'quick',
            q:     { business: '', delivery: '', returns: '', hours: '', payments: [] },
            faq:   '',
            count: @json($kbCount),
            busy:  false,
            error: '',
        },
        ai: { mode: @json($aiMode), busy: false, error: '' },

        kbSkipped: @json($kbSkipped),
        open: 1,
        poll: null,
        circumference: 2 * Math.PI * 19,

        urls: {
            create:   @json(route($prefix . '.instances.store')),
            knowledge:@json(route($prefix . '.onboarding.knowledge')),
            kbSkip:   @json(route($prefix . '.onboarding.knowledge.skip')),
            ai:       @json(route($prefix . '.onboarding.ai')),
        },
        defaultName: @json($defaultInstanceName),
        tenantId:    @json(auth()->user()->tenant_id),
        i18n: @json($gsI18n),

        /* ── derived ─────────────────────────────────────────────────── */
        get step1() { return this.wa.status === 'connected'; },
        get step2() { return this.kb.count > 0; },
        get step3() { return this.ai.mode && this.ai.mode !== 'off'; },
        get done()  { return [this.step1, this.step2, this.step3].filter(Boolean).length; },

        get prettyNumber() {
            const d = String(this.wa.phone || '').replace(/\D/g, '');
            return d ? '+' + d : '';
        },

        get qrSrc() {
            if (!this.wa.qr) return '';
            return this.wa.qr.startsWith('data:image/')
                ? this.wa.qr
                : 'data:image/png;base64,' + String(this.wa.qr).replace(/\s+/g, '');
        },

        /* How many answers are on screen right now, in whichever tab. */
        get kbAnswers() {
            if (this.kb.tab === 'paste') return (this.kb.faq.match(/^\s*Q\s*:/gim) || []).length;
            const q = this.kb.q;
            return ['business', 'delivery', 'returns', 'hours'].filter(k => (q[k] || '').trim()).length
                + (q.payments.length ? 1 : 0);
        },
        get kbReady() { return this.kbAnswers > 0; },
        get kbCountLabel() {
            const n = this.kbAnswers;
            if (!n && this.kb.tab === 'paste') return this.i18n.empty;
            const tpl = this.kb.tab === 'quick' ? this.i18n.quick : this.i18n.paste;
            return tpl.replace(':n', '<b>' + n + '</b>');
        },
        get modeLabel() { return this.i18n.modes[this.ai.mode] || this.ai.mode; },

        /* ── lifecycle ───────────────────────────────────────────────── */
        init() {
            // Open the first thing still to do, so the page starts where the
            // work is rather than always at step 1.
            this.open = !this.step1 ? 1 : (!this.step2 && !this.kbSkipped ? 2 : (!this.step3 ? 3 : 0));
            this.wa.pairing = ['qr_pending', 'connecting'].includes(this.wa.status);

            if (this.wa.id && !this.step1 && this.wa.status !== 'banned') {
                this.startWa(true);
            }
            this.subscribe();
        },

        subscribe() {
            if (!window.Echo || !this.tenantId) return;
            window.Echo.private(`tenant.${this.tenantId}.instances`)
                .listen('.instance.status.changed', (e) => {
                    if (this.wa.id && e.id !== this.wa.id) return;
                    this.applyWa({ status: e.status, phone_number: e.phone_number });
                });
        },

        toggle(n) { this.open = this.open === n ? 0 : n; },

        headers(json) {
            const h = { 'Accept': 'application/json' };
            if (json) h['Content-Type'] = 'application/json';
            const t = document.querySelector('meta[name=csrf-token]');
            if (t) h['X-CSRF-TOKEN'] = t.content;
            return h;
        },

        /* ── step 1 ──────────────────────────────────────────────────── */
        async startWa(silent = false) {
            if (this.wa.busy) return;
            this.wa.busy = true;
            this.wa.error = '';

            try {
                // No instance yet: create one under the workspace name. Through
                // the web endpoint, which also mints the webhook secret and
                // registers the gateway webhook.
                if (!this.wa.id) {
                    const res = await fetch(this.urls.create, {
                        method: 'POST', credentials: 'same-origin',
                        headers: this.headers(true),
                        body: JSON.stringify({ name: this.defaultName || 'WhatsApp' }),
                    });
                    if (!res.ok) throw new Error('create');
                    const data = await res.json();
                    this.wa.id = data.id;
                    this.wa.status = data.status || 'disconnected';
                }

                const res = await fetch(`/api/instances/${this.wa.id}/connect`, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'connect');

                if (data.qr_code) this.wa.qr = data.qr_code;
                if (data.status)  this.wa.status = data.status;
                this.wa.pairing = true;
                this.startPoll(3000);
            } catch (e) {
                this.wa.pairing = false;
                if (!silent) this.wa.error = this.i18n.failed;
            } finally {
                this.wa.busy = false;
            }
        },

        async checkWa() {
            if (!this.wa.id) return;
            try {
                const res = await fetch(`/api/instances/${this.wa.id}/status`, {
                    credentials: 'same-origin', headers: this.headers(),
                });
                const data = await res.json().catch(() => ({}));

                // The server refuses a number already linked to another
                // workspace and tears its own side down — say so instead of
                // spinning on a code that will never be accepted.
                if (res.status === 409 && data.code === 'phone_already_used') {
                    this.applyWa({ status: 'disconnected', phone_number: null });
                    this.wa.qr = null;
                    this.wa.pairing = false;
                    this.stopPoll();
                    this.wa.error = data.message || this.i18n.failed;
                    return;
                }
                if (res.ok) this.applyWa(data);
            } catch { /* transient — the next tick retries */ }
        },

        applyWa(data) {
            if (data.status) this.wa.status = data.status;
            if ('phone_number' in data) this.wa.phone = data.phone_number;
            if (data.qr_code) this.wa.qr = data.qr_code;

            if (this.wa.status === 'banned') {
                this.wa.error = this.i18n.blocked;
                this.wa.pairing = false;
                this.stopPoll();
                return;
            }

            if (this.wa.status === 'connected') {
                this.wa.qr = null;
                this.wa.pairing = false;
                this.stopPoll();
                this.startPoll(30000);
                if (this.open === 1) this.open = this.step2 ? 3 : 2;
                window.showToast?.('success', this.i18n.saved);
            }
        },

        async unlink() {
            if (!this.wa.id || this.wa.busy) return;
            this.wa.busy = true;
            try {
                await fetch(`/api/instances/${this.wa.id}/logout`, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(),
                });
                this.applyWa({ status: 'disconnected', phone_number: null });
                this.wa.qr = null;
                this.stopPoll();
            } finally {
                this.wa.busy = false;
            }
        },

        startPoll(every) {
            this.stopPoll();
            this.poll = setInterval(() => this.checkWa(), every);
        },
        stopPoll() {
            if (this.poll) clearInterval(this.poll);
            this.poll = null;
        },

        /* ── step 2 ──────────────────────────────────────────────────── */
        togglePayment(p) {
            const i = this.kb.q.payments.indexOf(p);
            if (i < 0) this.kb.q.payments.push(p); else this.kb.q.payments.splice(i, 1);
        },

        async saveKb() {
            if (this.kb.busy || !this.kbReady) return;
            this.kb.busy = true;
            this.kb.error = '';
            try {
                const res = await fetch(this.urls.knowledge, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(true),
                    body: JSON.stringify({
                        mode: this.kb.tab,
                        business: this.kb.q.business,
                        delivery: this.kb.q.delivery,
                        returns:  this.kb.q.returns,
                        hours:    this.kb.q.hours,
                        payments: this.kb.q.payments,
                        faq:      this.kb.faq,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || '');

                this.kb.count = data.count;
                this.kbSkipped = false;
                this.open = 3;
                window.showToast?.('success', this.i18n.saved);
            } catch (e) {
                this.kb.error = e.message || this.i18n.failed;
            } finally {
                this.kb.busy = false;
            }
        },

        async skipKb() {
            this.kbSkipped = true;
            this.open = this.step1 ? 0 : 1;
            try {
                await fetch(this.urls.kbSkip, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(),
                });
            } catch { /* a skip that did not persist just shows again */ }
        },

        /* ── step 3 ──────────────────────────────────────────────────── */
        pickMode(mode) {
            const wasOn = this.step3;
            this.ai.mode = mode;
            if (wasOn) this.saveAi(mode);   // already live: switch mode for real
        },

        toggleAi() { this.saveAi(this.step3 ? 'off' : (this.ai.mode === 'off' ? 'suggestion' : this.ai.mode)); },

        async saveAi(mode) {
            if (this.ai.busy) return;
            this.ai.busy = true;
            this.ai.error = '';
            try {
                const res = await fetch(this.urls.ai, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(true),
                    body: JSON.stringify({ mode }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || '');
                this.ai.mode = data.mode;
                window.showToast?.('success', this.i18n.saved);
            } catch (e) {
                this.ai.error = e.message || this.i18n.failed;
            } finally {
                this.ai.busy = false;
            }
        },
    };
}
</script>
@endsection
