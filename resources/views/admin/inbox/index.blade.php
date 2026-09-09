@extends('layouts.admin')

@section('title', __('ui.inbox_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.inbox_page.title') }}</span>
@endsection

@push('styles')
<style>
/* ════════════════════════════════════════════════════════════════
   UNIFIED INBOX — wavadesk inbox design
   WhatsApp + Live Chat in one list. Scoped under .ubx so the shared
   admin styles (.row, .list, .btn, .search …) are never touched.
════════════════════════════════════════════════════════════════ */
main.page-content { padding: 0 !important; }

.ubx{
  --teal:#0f7e7a;--teal-l:#15b6a8;--teal-d:#0a5e5b;--teal-50:#ecf7f6;--teal-100:#d6efed;
  --ink:#0d1417;--txt:#0f172a;--mut:#64748b;--mut-2:#94a3b8;
  --bd:#e6ebf0;--bd-2:#cbd5e1;--soft:#f7f9fa;--soft-2:#eef2f5;
  --wa:#25a35a;--wa-50:#e9f7ee;--lc:#4f6bed;--lc-50:#eef1fe;
  --amber:#d97706;--amber-50:#fef3e2;
  --list:352px;--info:300px;
  display:grid;grid-template-columns:var(--list) minmax(0,1fr) var(--info);
  height:calc(100vh - var(--topbar-height, 64px));
  background:var(--soft);color:var(--txt);font-size:14px;line-height:1.5;overflow:hidden;
}
.ubx button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}
.ubx ::-webkit-scrollbar{width:9px;height:9px}
.ubx ::-webkit-scrollbar-thumb{background:#cfd8de;border-radius:9px;border:2px solid transparent;background-clip:content-box}
.ubx ::-webkit-scrollbar-thumb:hover{background:#b6c2cb;background-clip:content-box}
.ubx ::-webkit-scrollbar-track{background:transparent}

/* ─────────── LIST ─────────── */
.ubx .list{background:#fff;border-right:1px solid var(--bd);display:flex;flex-direction:column;min-height:0;overflow:hidden}
.ubx .lh{padding:16px 16px 0;flex-shrink:0}
.ubx .lh .t{display:flex;align-items:center;gap:9px;margin-bottom:13px}
.ubx .lh h1{font-size:19px;font-weight:700;letter-spacing:-.02em;margin:0}
.ubx .lh .badge{font-size:11px;font-weight:700;background:var(--teal-50);color:var(--teal);padding:3px 8px;border-radius:999px}
.ubx .lh .grow{margin-inline-start:auto;display:flex;gap:6px;align-items:center}
.ubx .wsdot{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--mut)}
.ubx .wsdot i{width:6px;height:6px;border-radius:50%;background:var(--teal-l)}
.ubx .wsdot.off i{background:var(--mut-2)}
.ubx .iconbtn{width:30px;height:30px;border-radius:8px;display:grid;place-items:center;color:var(--mut);transition:.12s;flex-shrink:0}
.ubx .iconbtn:hover{background:var(--soft-2);color:var(--txt)}
.ubx .search{position:relative;margin-bottom:12px}
.ubx .search svg{position:absolute;inset-inline-start:11px;top:50%;transform:translateY(-50%);color:var(--mut-2);pointer-events:none}
.ubx .search input{width:100%;height:38px;border-radius:10px;border:1px solid var(--bd);background:var(--soft);padding:0 12px 0 34px;font-size:13.5px;outline:none;transition:.15s;color:var(--txt);font-family:inherit}
html[dir="rtl"] .ubx .search input{padding:0 34px 0 12px}
.ubx .search input::placeholder{color:var(--mut-2)}
.ubx .search input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(15,126,122,.1)}

/* channel switcher */
.ubx .chans{display:flex;gap:6px;margin-bottom:11px}
.ubx .chan{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;height:34px;border-radius:9px;border:1px solid var(--bd);font-size:12.5px;font-weight:600;color:var(--mut);transition:.12s;background:#fff;white-space:nowrap;padding:0 6px}
.ubx .chan:hover{border-color:var(--bd-2);color:var(--txt)}
.ubx .chan.on{background:var(--ink);border-color:var(--ink);color:#fff}
.ubx .chan .d{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.ubx .chan .d.wa{background:var(--wa)}
.ubx .chan .d.lc{background:var(--lc)}
.ubx .chan .d.all{background:linear-gradient(135deg,var(--wa) 50%,var(--lc) 50%)}

.ubx .tabs{display:flex;gap:2px;border-bottom:1px solid var(--bd);margin:0 -16px;padding:0 16px;overflow-x:auto}
.ubx .tab{padding:9px 11px;font-size:13px;font-weight:600;color:var(--mut);border-bottom:2px solid transparent;margin-bottom:-1px;transition:.12s;display:flex;align-items:center;gap:6px;white-space:nowrap}
.ubx .tab:hover{color:var(--txt)}
.ubx .tab.on{color:var(--teal);border-bottom-color:var(--teal)}
.ubx .tab .n{font-size:10.5px;font-weight:700;background:var(--soft-2);color:var(--mut);padding:1px 6px;border-radius:999px}
.ubx .tab.on .n{background:var(--teal-50);color:var(--teal)}

.ubx .rows{flex:1;overflow-y:auto;min-height:0}
.ubx .row{display:flex;gap:11px;padding:13px 16px;border-bottom:1px solid #f1f5f7;cursor:pointer;transition:.1s;position:relative;width:100%;text-align:start;align-items:flex-start}
.ubx .row:hover{background:var(--soft)}
.ubx .row.on{background:var(--teal-50)}
.ubx .row.on::before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;width:3px;background:var(--teal)}
.ubx .row .avw{display:block;position:relative;flex-shrink:0}
.ubx .row .av{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;background:var(--teal)}
.ubx .row .ch{position:absolute;inset-inline-end:-2px;bottom:-2px;width:17px;height:17px;border-radius:50%;border:2px solid #fff;display:grid;place-items:center}
.ubx .row.on .ch{border-color:var(--teal-50)}
.ubx .ch.wa{background:var(--wa)}
.ubx .ch.lc{background:var(--lc)}
.ubx .row .m{display:block;flex:1;min-width:0}
.ubx .row .l1{display:flex;align-items:baseline;gap:8px}
.ubx .row .nm{font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;min-width:0}
.ubx .row .tm{font-size:11px;color:var(--mut-2);flex-shrink:0;font-variant-numeric:tabular-nums}
.ubx .row .pv{display:block;font-size:12.5px;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.ubx .row.unread .nm{font-weight:700}
.ubx .row.unread .pv{color:var(--txt);font-weight:500}
.ubx .row .l3{display:flex;align-items:center;gap:6px;margin-top:6px;flex-wrap:wrap}
.ubx .st{font-size:10px;font-weight:700;letter-spacing:.05em;padding:2.5px 7px;border-radius:5px;text-transform:uppercase}
.ubx .st.pending{background:var(--amber-50);color:var(--amber)}
.ubx .st.assigned{background:var(--teal-50);color:var(--teal)}
.ubx .st.bot{background:var(--lc-50);color:var(--lc)}
.ubx .st.ai{background:rgba(21,182,168,.14);color:var(--teal-d)}
.ubx .st.closed{background:var(--soft-2);color:var(--mut)}
.ubx .asg{font-size:11px;color:var(--mut);display:inline-flex;align-items:center;gap:4px;margin-inline-start:auto}
.ubx .asg .a{width:17px;height:17px;border-radius:50%;background:var(--teal-d);color:#fff;font-size:8.5px;font-weight:700;display:grid;place-items:center}
.ubx .unreadpill{background:var(--teal);color:#fff;font-size:10px;font-weight:700;min-width:17px;height:17px;padding:0 5px;border-radius:999px;display:grid;place-items:center}
.ubx .empty{padding:46px 22px;text-align:center;color:var(--mut-2);font-size:13.5px;display:flex;flex-direction:column;align-items:center;gap:11px}

/* ─────────── THREAD ─────────── */
.ubx .thread{background:var(--soft);display:flex;flex-direction:column;min-height:0;overflow:hidden}
.ubx .th{background:#fff;border-bottom:1px solid var(--bd);padding:11px 18px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.ubx .th .back{display:none}
.ubx .th .avw{position:relative;flex-shrink:0}
.ubx .th .av{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;background:var(--teal)}
.ubx .th .ch{position:absolute;inset-inline-end:-2px;bottom:-2px;width:17px;height:17px;border-radius:50%;border:2px solid #fff;display:grid;place-items:center}
.ubx .th .m{flex:1;min-width:0}
.ubx .th .n{font-size:15px;font-weight:700;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ubx .th .s{font-size:12px;color:var(--mut);display:flex;align-items:center;gap:6px;margin-top:1px;flex-wrap:wrap}
.ubx .th .acts{display:flex;gap:6px;align-items:center}
.ubx .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:35px;padding:0 14px;border-radius:9px;font-size:13px;font-weight:600;transition:.13s;white-space:nowrap}
.ubx .btn.p{background:var(--teal);color:#fff}
.ubx .btn.p:hover{background:var(--teal-d)}
.ubx .btn.g{background:#fff;color:var(--txt);border:1px solid var(--bd)}
.ubx .btn.g:hover{border-color:var(--bd-2);background:var(--soft)}
.ubx .btn.dgr{background:#fff;color:#dc2626;border:1px solid #fecaca}
.ubx .btn.dgr:hover{background:#fef2f2}
.ubx .btn:disabled{opacity:.55;cursor:not-allowed}

.ubx .msgs{flex:1;overflow-y:auto;padding:22px 24px;display:flex;flex-direction:column;min-height:0}
/* few messages shouldn't leave a hole above the composer — sit them on the bottom */
.ubx .msgs > *:first-child{margin-top:auto}
.ubx .grp{display:flex;gap:9px;margin-top:11px;max-width:66%}
.ubx .grp.out{margin-inline-start:auto;flex-direction:row-reverse}
.ubx .grp .gav{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:10.5px;font-weight:700;color:#fff;flex-shrink:0;align-self:flex-end;margin-bottom:2px;background:var(--teal)}
.ubx .grp.out .gav{background:var(--teal-d)}
.ubx .grp .bubs{display:flex;flex-direction:column;gap:3px;min-width:0}
.ubx .grp.out .bubs{align-items:flex-end}
.ubx .who{font-size:11px;font-weight:600;color:var(--mut);margin-bottom:3px;padding:0 3px}
.ubx .grp.out .who{text-align:end}
/* Bubbles: block-level, text-align start explicitly so BiDi from customer
   names/tenant locale can't drag outbound text to the wrong edge. Padding
   bumped so the copy has breathing room and the timestamp doesn't crowd
   the last word. */
.ubx .bub{display:block;padding:11px 15px;border-radius:15px;font-size:14px;line-height:1.5;text-align:start;position:relative;overflow-wrap:anywhere;white-space:pre-wrap;width:fit-content;max-width:100%}
.ubx .bub.in{background:#fff;border:1px solid var(--bd);border-end-start-radius:5px}
.ubx .bub.out{background:var(--teal);color:#fff;border-end-end-radius:5px}
.ubx .bub.in + .bub.in{border-end-start-radius:15px;border-start-start-radius:5px}
.ubx .bub.out + .bub.out{border-end-end-radius:15px;border-start-end-radius:5px}
.ubx .bub .tm{font-size:10.5px;opacity:.7;margin-top:4px;display:flex;align-items:center;gap:4px;justify-content:flex-end;font-variant-numeric:tabular-nums;line-height:1.2}
.ubx .bub.in .tm{color:var(--mut-2);opacity:1}
.ubx .sys{background:#fff;border:1px solid var(--bd);color:var(--mut);font-size:12px;font-weight:500;padding:6px 14px;border-radius:999px;margin:12px auto;display:flex;width:fit-content;max-width:100%;align-items:center;gap:7px;text-align:center}
.ubx .nothread{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:var(--mut-2);font-size:14px;padding:24px;text-align:center}
.ubx .nothread .ic{width:58px;height:58px;border-radius:50%;background:#fff;border:1px solid var(--bd);display:grid;place-items:center;color:var(--teal)}

/* composer */
.ubx .comp{background:#fff;border-top:1px solid var(--bd);padding:12px 18px 14px;flex-shrink:0}
.ubx .cbox{border:1px solid var(--bd);border-radius:12px;transition:.15s;background:#fff}
.ubx .cbox:focus-within{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.ubx .cbox textarea{width:100%;border:none;background:none;outline:none;resize:none;padding:11px 13px 4px;font-size:14px;line-height:1.5;color:var(--txt);max-height:130px;min-height:42px;display:block;font-family:inherit}
.ubx .cbox textarea::placeholder{color:var(--mut-2)}
.ubx .crow{display:flex;align-items:center;gap:3px;padding:5px 8px 7px}
.ubx .crow .sp{flex:1;font-size:11.5px;color:var(--mut-2)}
.ubx .send{width:34px;height:34px;border-radius:9px;background:var(--teal);color:#fff;display:grid;place-items:center;transition:.13s;flex-shrink:0}
.ubx .send:hover{background:var(--teal-d)}
.ubx .send:disabled{background:var(--bd-2);cursor:default}
html[dir="rtl"] .ubx .send svg{transform:scaleX(-1)}
.ubx .closed-bar{background:var(--soft-2);border:1px solid var(--bd);border-radius:12px;padding:13px;display:flex;align-items:center;justify-content:center;gap:9px;font-size:13.5px;color:var(--mut);font-weight:500;text-align:center}
.ubx .claim-bar{background:linear-gradient(180deg,var(--amber-50),#fff);border:1px solid #fcd9a4;border-radius:12px;padding:13px 15px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.ubx .claim-bar .m{flex:1;min-width:0}
.ubx .claim-bar .h{font-size:13.5px;font-weight:700;color:#92400e}
.ubx .claim-bar .s{font-size:12.5px;color:var(--amber);margin-top:1px}

/* AI state chip in the thread header */
.ubx .aichip{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap;border:1px solid transparent}
.ubx .aichip.on{background:var(--teal-50);color:var(--teal-d);border-color:var(--teal-100)}
.ubx .aichip.off{background:var(--amber-50);color:var(--amber);border-color:#fcd9a4}
.ubx .aichip button{font-size:11px;font-weight:700;text-decoration:underline;color:inherit;padding:0}

/* ─────────── INFO ─────────── */
.ubx .info{background:#fff;border-inline-start:1px solid var(--bd);display:flex;flex-direction:column;min-height:0;overflow:hidden}
.ubx .infoscroll{flex:1;overflow-y:auto;min-height:0}
.ubx .ihero{position:relative;padding:24px 20px 20px;text-align:center;border-bottom:1px solid var(--bd)}
.ubx .ihero .av{width:64px;height:64px;border-radius:50%;margin:0 auto;display:grid;place-items:center;font-size:21px;font-weight:700;color:#fff;background:var(--teal)}
.ubx .ihero .n{font-size:16.5px;font-weight:700;letter-spacing:-.01em;margin-top:11px}
.ubx .ihero .sub{font-size:12.5px;color:var(--mut);margin-top:2px;word-break:break-all}
.ubx .ihero .chip{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:999px;margin-top:10px}
.ubx .ihero .chip.wa{background:var(--wa-50);color:var(--wa)}
.ubx .ihero .chip.lc{background:var(--lc-50);color:var(--lc)}
.ubx .isec{padding:16px 20px}
.ubx .isec h4{font-size:10.5px;font-weight:700;letter-spacing:.13em;color:var(--mut-2);text-transform:uppercase;margin:0 0 11px}
.ubx .kv{display:flex;gap:10px;font-size:12.5px;padding:5px 0;align-items:flex-start}
.ubx .kv .k{color:var(--mut);flex-shrink:0;width:74px}
.ubx .kv .v{color:var(--txt);font-weight:500;flex:1;min-width:0;text-align:end;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ubx .infoclose{display:none}

/* ─────────── skeletons ─────────── */
.ubx .sk{background:linear-gradient(90deg,var(--soft-2) 25%,#f7f9fa 37%,var(--soft-2) 63%);background-size:400% 100%;animation:ubxsk 1.4s ease infinite;border-radius:6px;flex-shrink:0}
@keyframes ubxsk{0%{background-position:100% 50%}100%{background-position:0 50%}}
.ubx .skrow{display:flex;gap:11px;padding:13px 16px;border-bottom:1px solid #f1f5f7;align-items:flex-start}
.ubx .skrow .skav{width:40px;height:40px;border-radius:50%}
.ubx .skrow .skm{flex:1;min-width:0;display:flex;flex-direction:column;gap:7px;padding-top:3px}
.ubx .skth{display:flex;align-items:center;gap:12px;padding:11px 18px;background:#fff;border-bottom:1px solid var(--bd);flex-shrink:0}
.ubx .skth .skav{width:40px;height:40px;border-radius:50%}
.ubx .skth .skm{flex:1;min-width:0;display:flex;flex-direction:column;gap:7px}
.ubx .skmsgs{flex:1;padding:22px 24px;display:flex;flex-direction:column;gap:14px;overflow:hidden}
.ubx .skbub{height:44px;border-radius:15px;max-width:62%}
.ubx .skbub.out{margin-inline-start:auto}
.ubx .skcomp{background:#fff;border-top:1px solid var(--bd);padding:12px 18px 14px;flex-shrink:0}
.ubx .skcomp .skbox{height:74px;border-radius:12px}
@media (prefers-reduced-motion:reduce){.ubx .sk{animation:none}}

/* ─────────── close modal ─────────── */
.ubx-modal{position:fixed;inset:0;z-index:1200;display:grid;place-items:center;padding:20px;background:rgba(13,20,23,.5)}
.ubx-modal .box{background:#fff;border-radius:16px;width:100%;max-width:440px;padding:24px;box-shadow:0 30px 70px -30px rgba(13,20,23,.5)}
.ubx-modal h3{font-size:18px;font-weight:700;margin:0 0 6px;letter-spacing:-.02em;color:#0f172a}
.ubx-modal label{display:block;font-size:12.5px;font-weight:600;margin:14px 0 6px;color:#0f172a}
.ubx-modal input{width:100%;height:44px;border:1px solid #e6ebf0;border-radius:10px;padding:0 13px;font-size:14px;outline:none;font-family:inherit;color:#0f172a}
.ubx-modal input:focus{border-color:#0f7e7a;box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.ubx-modal .acts{display:flex;gap:8px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}

/* ─────────── responsive ─────────── */
@media (max-width:1180px){ .ubx{--list:300px;--info:270px} .ubx .msgs{padding:18px 16px} }
@media (max-width:1024px){
  .ubx{grid-template-columns:var(--list) minmax(0,1fr)}
  .ubx .info{position:fixed;top:0;inset-inline-end:0;bottom:0;width:min(320px,90vw);z-index:1100;transform:translateX(100%);transition:transform .24s cubic-bezier(.4,0,.2,1);box-shadow:-20px 0 50px -20px rgba(13,20,23,.25)}
  html[dir="rtl"] .ubx .info{transform:translateX(-100%)}
  .ubx .info.open{transform:translateX(0)}
  .ubx .infoclose{display:grid}
}
@media (max-width:820px){
  .ubx{grid-template-columns:1fr}
  .ubx .list,.ubx .thread{grid-column:1;grid-row:1}
  .ubx .list{border-right:none}
  .ubx .thread{display:none}
  .ubx.v-thread .list{display:none}
  .ubx.v-thread .thread{display:flex}
  .ubx .th .back{display:grid}
  .ubx .msgs{padding:16px 14px}
  .ubx .grp{max-width:86%}
  .ubx .comp{padding:10px 12px 12px}
  .ubx .lh{padding:14px 14px 0}
  .ubx .tabs{margin:0 -14px;padding:0 14px}
  .ubx .row{padding:12px 14px}
}
</style>
@endpush

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $i18n = __('ui.inbox_page');
    $jsLabels = [
        'tab_pending'     => __('ui.webchat_page.tab_pending'),
        'tab_mine'        => __('ui.webchat_page.tab_mine'),
        'tab_all'         => __('ui.webchat_page.tab_all'),
        'tab_closed'      => __('ui.webchat_page.tab_closed'),
        'status_pending'  => $i18n['status_pending'],
        'status_assigned' => $i18n['status_assigned'],
        'status_closed'   => $i18n['status_closed'],
        'status_bot'      => $i18n['status_bot'],
        'status_ai'       => $i18n['status_ai'],
        'claim_error'     => $i18n['claim_error'],
        'send_error'      => $i18n['send_error'],
        'action_error'    => $i18n['action_error'],
        'just_now'        => __('ui.webchat_page.just_now'),
    ];
@endphp

<div x-data="unifiedInbox()" x-init="init()" x-cloak class="ubx" :class="{ 'v-thread': mobileThread }">

    {{-- ═══════════ LIST ═══════════ --}}
    <section class="list">
        <div class="lh">
            <div class="t">
                <h1>{{ $i18n['title'] }}</h1>
                <span class="badge" x-text="rows.length"></span>
                <div class="grow">
                    <span class="wsdot" :class="{ 'off': !wsConnected }"><i></i></span>
                    <button type="button" class="iconbtn" @click="loadList()" title="{{ $i18n['title'] }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 11a8 8 0 10-2.3 5.7M20 5v6h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>

            <div class="search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="text" x-model="q" @input.debounce.350ms="loadList()" placeholder="{{ $i18n['search_placeholder'] }}">
            </div>

            {{-- channel switcher --}}
            @if($canUseWebChat)
            <div class="chans">
                <button type="button" class="chan" :class="{ 'on': channel === 'all' }" @click="setChannel('all')"><span class="d all"></span>{{ $i18n['all_channels'] }}</button>
                <button type="button" class="chan" :class="{ 'on': channel === 'whatsapp' }" @click="setChannel('whatsapp')"><span class="d wa"></span>{{ $i18n['whatsapp'] }}</button>
                <button type="button" class="chan" :class="{ 'on': channel === 'webchat' }" @click="setChannel('webchat')"><span class="d lc"></span>{{ $i18n['live_chat'] }}</button>
            </div>
            @endif

            <div class="tabs">
                <template x-for="t in tabs" :key="t">
                    <button type="button" class="tab" :class="{ 'on': tab === t }" @click="setTab(t)">
                        <span x-text="labels['tab_' + t]"></span>
                        <span class="n" x-text="counts[t] ?? 0"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="rows">
            <template x-if="loading">
                <div>
                    <template x-for="i in 7" :key="'sk' + i">
                        <div class="skrow">
                            <div class="sk skav"></div>
                            <div class="skm">
                                <div class="sk" style="width:54%;height:11px"></div>
                                <div class="sk" style="width:84%;height:10px"></div>
                                <div class="sk" style="width:32%;height:9px"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="!loading && rows.length === 0">
                <div class="empty">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    <span>{{ $i18n['no_conversations'] }}</span>
                </div>
            </template>

            <template x-for="r in (loading ? [] : rows)" :key="r.key">
                <button type="button" class="row" :class="{ 'on': activeKey === r.key, 'unread': r.unread > 0 }" @click="openRow(r)">
                    <span class="avw">
                        <span class="av" x-text="r.initials"></span>
                        <span class="ch" :class="r.channel === 'whatsapp' ? 'wa' : 'lc'" x-html="channelGlyph(r.channel)"></span>
                    </span>
                    <span class="m">
                        <span class="l1">
                            <span class="nm" x-text="r.name"></span>
                            <span class="tm" x-text="timeAgo(r.last_activity_at)"></span>
                        </span>
                        <span class="pv" x-text="r.preview || '{{ $i18n['no_messages'] }}'"></span>
                        <span class="l3">
                            <span class="st" :class="r.status" x-text="labels['status_' + r.status] ?? r.status"></span>
                            <template x-if="r.ai">
                                <span class="st ai" style="display:inline-flex;align-items:center;gap:3px">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/></svg>
                                    <span x-text="labels.status_ai"></span>
                                </span>
                            </template>
                            <template x-if="r.assignee">
                                <span class="asg">
                                    <span class="a" x-text="initials(r.assignee.name)"></span>
                                    <span x-text="r.assignee.id === myId ? '{{ $i18n['claimed_by_you'] }}' : r.assignee.name"></span>
                                </span>
                            </template>
                            <template x-if="r.unread > 0">
                                <span class="unreadpill" style="margin-inline-start:auto" x-text="r.unread"></span>
                            </template>
                        </span>
                    </span>
                </button>
            </template>
        </div>
    </section>

    {{-- ═══════════ THREAD ═══════════ --}}
    <section class="thread">
        <template x-if="threadLoading">
            <div style="display:flex;flex-direction:column;min-height:0;flex:1">
                <div class="skth">
                    <div class="sk skav"></div>
                    <div class="skm">
                        <div class="sk" style="width:150px;height:13px"></div>
                        <div class="sk" style="width:96px;height:10px"></div>
                    </div>
                </div>
                <div class="skmsgs">
                    <div class="sk skbub" style="width:52%"></div>
                    <div class="sk skbub out" style="width:44%"></div>
                    <div class="sk skbub" style="width:60%;height:62px"></div>
                    <div class="sk skbub out" style="width:38%"></div>
                </div>
                <div class="skcomp"><div class="sk skbox"></div></div>
            </div>
        </template>

        <template x-if="!activeKey && !threadLoading">
            <div class="nothread">
                <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
                <span>{{ $i18n['select_conversation'] }}</span>
            </div>
        </template>

        <template x-if="activeKey && !threadLoading && thread">
            <div style="display:flex;flex-direction:column;min-height:0;flex:1">
                <div class="th">
                    <button type="button" class="iconbtn back" @click="backToList()">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 5l-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <span class="avw">
                        <span class="av" x-text="thread.header.initials"></span>
                        <span class="ch" :class="thread.channel === 'whatsapp' ? 'wa' : 'lc'" x-html="channelGlyph(thread.channel)"></span>
                    </span>
                    <div class="m">
                        <div class="n" x-text="thread.header.name"></div>
                        <div class="s">
                            <span class="st" :class="thread.header.status" x-text="labels['status_' + thread.header.status] ?? thread.header.status"></span>
                            <template x-if="thread.header.assignee">
                                <span x-text="thread.header.assignee.id === myId ? '{{ $i18n['claimed_by_you'] }}' : '{{ $i18n['claimed_by'] }} ' + thread.header.assignee.name"></span>
                            </template>
                            <span x-show="thread.header.subtitle" x-text="thread.header.subtitle"></span>

                            {{-- Why the AI is or isn't answering this thread --}}
                            <template x-if="thread.ai && thread.ai.applies">
                                <span class="aichip" :class="thread.ai.eligible ? 'on' : 'off'">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/></svg>
                                    <span x-text="thread.ai.eligible
                                        ? '{{ $i18n['ai_active'] }}'
                                        : (thread.ai.reason === 'claimed' ? '{{ $i18n['ai_paused_claimed'] }}' : '{{ $i18n['ai_paused'] }}')"></span>
                                    <template x-if="!thread.ai.eligible && thread.ai.reason === 'suspended' && thread.ai.can_toggle">
                                        <button type="button" @click="resumeAi()" :disabled="busy">{{ $i18n['ai_resume'] }}</button>
                                    </template>
                                </span>
                            </template>
                        </div>
                    </div>
                    <div class="acts">
                        <template x-if="thread.can.release">
                            <button type="button" class="btn g" @click="release()" :disabled="busy">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 7h2a5 5 0 010 10h-2M9 17H7A5 5 0 017 7h2M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                {{ $i18n['release'] }}
                            </button>
                        </template>
                        <template x-if="thread.can.close">
                            <button type="button" class="btn dgr" @click="openClose()" :disabled="busy">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                                {{ $i18n['close'] }}
                            </button>
                        </template>
                        <button type="button" class="iconbtn" @click="infoOpen = !infoOpen" title="{{ $i18n['details'] }}">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5.5M12 7.6h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>

                <div class="msgs" x-ref="thread">
                    <template x-for="g in groups" :key="g.id">
                        <div>
                            <template x-if="g.type === 'sys'">
                                <div class="sys" x-text="g.body"></div>
                            </template>
                            <template x-if="g.type === 'msg'">
                                <div class="grp" :class="g.side === 'out' ? 'out' : ''">
                                    <div class="gav" x-text="initials(g.who)"></div>
                                    <div class="bubs">
                                        <div class="who" x-text="g.who"></div>
                                        <template x-for="(m, mi) in g.items" :key="m.id">
                                            <div class="bub" :class="g.side">
                                                <span x-text="(m.body || '').trim()"></span>
                                                <template x-if="mi === g.items.length - 1">
                                                    <div class="tm" x-text="formatTime(m.created_at)"></div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="thread.messages.length === 0">
                        <div class="sys">{{ $i18n['no_messages'] }}</div>
                    </template>
                </div>

                <div class="comp">
                    <template x-if="thread.header.status === 'closed'">
                        <div class="closed-bar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 118 0v3" stroke="currentColor" stroke-width="1.8"/></svg>
                            {{ $i18n['chat_closed'] }}
                        </div>
                    </template>

                    <template x-if="thread.header.status !== 'closed' && thread.can.claim">
                        <div class="claim-bar">
                            <div class="m">
                                <div class="h">{{ $i18n['claim'] }}</div>
                                <div class="s">{{ $i18n['status_pending'] }}</div>
                            </div>
                            <button type="button" class="btn p" @click="claim()" :disabled="busy">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ $i18n['claim_btn'] }}
                            </button>
                        </div>
                    </template>

                    <template x-if="thread.header.status !== 'closed' && !thread.can.claim && !thread.can.reply">
                        <div class="closed-bar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 118 0v3" stroke="currentColor" stroke-width="1.8"/></svg>
                            {{ $i18n['locked'] }}
                        </div>
                    </template>

                    <template x-if="thread.can.reply">
                        <div class="cbox">
                            <textarea x-model="composer" @keydown.enter.exact.prevent="send()" rows="1"
                                      placeholder="{{ $i18n['composer_placeholder'] }}"></textarea>
                            <div class="crow">
                                <span class="sp">↵ {{ $i18n['enter_to_send'] }}</span>
                                <button type="button" class="send" @click="send()" :disabled="!composer.trim() || sending">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </section>

    {{-- ═══════════ INFO ═══════════ --}}
    <aside class="info" :class="{ 'open': infoOpen }" x-show="thread && !threadLoading" x-cloak>
        <div class="infoscroll" x-show="thread">
            <div class="ihero">
                <button type="button" class="iconbtn infoclose" style="position:absolute;top:12px;inset-inline-end:12px" @click="infoOpen = false">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
                <div class="av" x-text="thread?.header.initials"></div>
                <div class="n" x-text="thread?.header.name"></div>
                <div class="sub" x-text="thread?.header.subtitle || ''"></div>
                <div class="chip" :class="thread?.channel === 'whatsapp' ? 'wa' : 'lc'">
                    <span x-text="thread?.channel === 'whatsapp' ? '{{ $i18n['whatsapp'] }}' : '{{ $i18n['live_chat'] }}'"></span>
                </div>
            </div>
            <div class="isec">
                <h4>{{ $i18n['details'] }}</h4>
                <template x-for="kv in (thread?.info ?? [])" :key="kv.k">
                    <div class="kv"><span class="k" x-text="kv.k"></span><span class="v" :title="kv.v" x-text="kv.v"></span></div>
                </template>
            </div>
        </div>
    </aside>

    {{-- ═══════════ close modal ═══════════ --}}
    <template x-if="closeModal">
        <div class="ubx-modal" @click.self="closeModal = false">
            <div class="box">
                <h3>{{ $i18n['close_confirm'] }}</h3>
                <label>{{ $i18n['close_title_label'] }}</label>
                <input type="text" x-model="closeTitle" maxlength="180">
                <div class="acts">
                    <button type="button" class="btn g" @click="closeModal = false">{{ __('ui.cancel') }}</button>
                    <button type="button" class="btn dgr" @click="doClose()" :disabled="busy">{{ $i18n['close'] }}</button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function unifiedInbox() {
    return {
        labels: @json($jsLabels),
        listUrl:    @json(route($panelPrefix . '.inbox.list')),
        threadTpl:  @json(route($panelPrefix . '.inbox.thread', ['channel' => '__CH__', 'ref' => '__REF__'])),
        webchatTpl: @json($canUseWebChat ? route($panelPrefix . '.webchat.conversations.index') : ''),
        myId:       @json((int) auth()->id()),
        myName:     @json((string) auth()->user()->name),

        tabs: ['pending', 'mine', 'all', 'closed'],
        channel: 'all',
        tab: 'pending',
        q: '',
        rows: [],
        counts: {},
        loading: false,

        activeKey: null,
        thread: null,
        threadLoading: false,
        composer: '',
        sending: false,
        busy: false,
        infoOpen: false,
        mobileThread: false,
        closeModal: false,
        closeTitle: '',
        wsConnected: false,
        _timer: null,

        init() {
            this.loadList();
            this._timer = setInterval(() => this.loadList(true), 15000);
            if (window._echoStateListeners) window._echoStateListeners.push((c) => { this.wsConnected = c; });
            this.wsConnected = !!window._echoConnected;
            window.addEventListener('beforeunload', () => clearInterval(this._timer));
        },

        // ── list ──
        setChannel(c) { if (this.channel === c) return; this.channel = c; this.clearThread(); this.loadList(); },
        setTab(t)     { if (this.tab === t) return; this.tab = t; this.clearThread(); this.loadList(); },

        clearThread() { this.activeKey = null; this.thread = null; this.mobileThread = false; this.infoOpen = false; },

        async loadList(silent = false) {
            if (!silent) this.loading = true;
            try {
                const url = new URL(this.listUrl, window.location.origin);
                url.searchParams.set('channel', this.channel);
                url.searchParams.set('tab', this.tab);
                if (this.q.trim()) url.searchParams.set('q', this.q.trim());
                const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                this.rows = data.data || [];
                this.counts = data.counts || {};
            } catch (e) {
                console.error('[inbox] list failed', e);
            } finally {
                if (!silent) this.loading = false;
            }
        },

        // ── thread ──
        async openRow(row) {
            this.mobileThread = true;
            if (this.activeKey === row.key) return;
            this.activeKey = row.key;
            this.thread = null;
            this.composer = '';
            this.threadLoading = true;
            try {
                await this.loadThread(row.channel, row.ref);
            } catch (e) {
                console.error('[inbox] thread failed', e);
                this.activeKey = null;
                this.mobileThread = false;
                window.showToast?.('error', this.labels.action_error);
            } finally {
                this.threadLoading = false;
            }
        },

        async loadThread(channel, ref) {
            const url = this.threadTpl.replace('__CH__', channel).replace('__REF__', encodeURIComponent(ref));
            const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            this.thread = await r.json();
            this.threadLoading = false;
            if (this.thread.can.reply) this.markRead();
            await this.$nextTick();
            this.scrollBottom();
        },

        // Best effort — a failed read receipt shouldn't disturb the thread.
        markRead() {
            const url = this.actionUrl('read');
            if (url) this.post(url).catch(() => {});
        },

        async reloadThread() {
            if (!this.thread) return;
            await this.loadThread(this.thread.channel, this.thread.ref);
        },

        // Consecutive messages from one sender collapse into a single group.
        get groups() {
            const GAP = 5 * 60 * 1000;
            const out = [];
            for (const m of (this.thread?.messages ?? [])) {
                if (m.kind === 'system') { out.push({ type: 'sys', id: m.id, body: (m.body || '').trim() }); continue; }
                const prev = out[out.length - 1];
                const near = prev && prev.type === 'msg' && prev.side === m.side && prev.who === m.who
                    && Math.abs(new Date(m.created_at) - new Date(prev.items[prev.items.length - 1].created_at)) < GAP;
                if (near) prev.items.push(m);
                else out.push({ type: 'msg', id: m.id, side: m.side, who: m.who || '', items: [m] });
            }
            return out;
        },

        // ── actions: each channel keeps its own endpoints ──
        actionUrl(what) {
            const t = this.thread;
            if (!t) return null;
            if (t.channel === 'whatsapp') return `/api/conversations/${t.ref}/${what}`;
            return this.webchatTpl + '/' + encodeURIComponent(t.ref) + (what === 'messages' ? '/messages' : '/' + what);
        },

        async post(url, body = {}) {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
        },

        async claim() {
            if (this.busy) return;
            this.busy = true;
            try {
                const r = await this.post(this.actionUrl('claim'));
                if (r.status === 409) { window.showToast?.('error', this.labels.claim_error); await this.loadList(true); return; }
                if (!r.ok) throw new Error('HTTP ' + r.status);
                await this.reloadThread();
                await this.loadList(true);
            } catch (e) {
                console.error('[inbox] claim failed', e);
                window.showToast?.('error', this.labels.claim_error);
            } finally { this.busy = false; }
        },

        // Hand the thread back to the AI (WhatsApp only — the flag is per conversation).
        async resumeAi() {
            if (this.busy || this.thread?.channel !== 'whatsapp') return;
            this.busy = true;
            try {
                const r = await this.post(`/api/conversations/${this.thread.ref}/toggle-ai`, { ai_suspended: false });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                await this.reloadThread();
            } catch (e) {
                console.error('[inbox] resume ai failed', e);
                window.showToast?.('error', this.labels.action_error);
            } finally { this.busy = false; }
        },

        async release() {
            if (this.busy) return;
            this.busy = true;
            try {
                const r = await this.post(this.actionUrl('release'));
                if (!r.ok) throw new Error('HTTP ' + r.status);
                await this.reloadThread();
                await this.loadList(true);
            } catch (e) {
                console.error('[inbox] release failed', e);
                window.showToast?.('error', this.labels.action_error);
            } finally { this.busy = false; }
        },

        openClose() { this.closeTitle = this.thread?.header?.name ? '' : ''; this.closeModal = true; },

        async doClose() {
            if (this.busy) return;
            this.busy = true;
            try {
                const r = await this.post(this.actionUrl('close'), { title: this.closeTitle.trim() });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.closeModal = false;
                await this.reloadThread();
                await this.loadList(true);
            } catch (e) {
                console.error('[inbox] close failed', e);
                window.showToast?.('error', this.labels.action_error);
            } finally { this.busy = false; }
        },

        async send() {
            const body = this.composer.trim();
            if (!body || this.sending || !this.thread?.can.reply) return;
            this.sending = true;
            try {
                const r = await this.post(this.actionUrl('messages'), { body });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.composer = '';
                await this.reloadThread();
                await this.loadList(true);
            } catch (e) {
                console.error('[inbox] send failed', e);
                window.showToast?.('error', this.labels.send_error);
            } finally { this.sending = false; }
        },

        // ── ui helpers ──
        backToList() { this.mobileThread = false; this.infoOpen = false; },

        channelGlyph(channel) {
            return channel === 'whatsapp'
                ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.15l-.3-.18-3.1.81.83-3.02-.2-.31A8.2 8.2 0 1112 20.2z"/><path d="M17.5 14.4c-.3-.15-1.75-.86-2-.96-.28-.1-.48-.15-.68.15s-.78.96-.95 1.16c-.18.2-.35.22-.65.07a8.2 8.2 0 01-2.4-1.48 9 9 0 01-1.67-2.07c-.17-.3 0-.46.13-.61.14-.14.3-.35.45-.53.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.68-1.6-.93-2.2-.24-.58-.49-.5-.67-.51h-.58c-.2 0-.53.07-.8.38-.28.3-1.05 1.02-1.05 2.5s1.07 2.9 1.22 3.1c.15.2 2.1 3.2 5.1 4.5.71.3 1.27.48 1.7.62.72.23 1.37.2 1.89.12.57-.09 1.75-.72 2-1.4.25-.7.25-1.28.17-1.4-.07-.13-.27-.2-.57-.35z"/></svg>'
                : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="4" width="19" height="13" rx="2.5" fill="#fff"/><path d="M8 20l3-3h2l-5 3z" fill="#fff"/><path d="M7 9h10M7 12.5h6" stroke="#4f6bed" stroke-width="1.8" stroke-linecap="round"/></svg>';
        },

        initials(name) {
            const n = (name || '').trim();
            if (!n) return '·';
            const p = n.split(/\s+/);
            return ((p[0]?.[0] || '') + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase() || '·';
        },

        timeAgo(iso) {
            if (!iso) return '';
            const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
            if (s < 60) return this.labels.just_now;
            if (s < 3600) return Math.floor(s / 60) + 'm';
            if (s < 86400) return Math.floor(s / 3600) + 'h';
            return Math.floor(s / 86400) + 'd';
        },

        formatTime(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
            catch { return ''; }
        },

        scrollBottom() {
            if (this.$refs.thread) this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight;
        },
    };
}
</script>
@endsection
