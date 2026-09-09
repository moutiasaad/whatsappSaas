@extends('layouts.admin')

@section('title', __('ui.webchat_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.webchat_page.title') }}</span>
@endsection

@push('styles')
<style>
/* ════════════════════════════════════════════════════════════════
   LIVE CHAT INBOX — wavadesk inbox design
   Everything is scoped under .wcx so the shared admin styles
   (.row, .list, .btn, .search …) are never touched.
════════════════════════════════════════════════════════════════ */
main.page-content { padding: 0 !important; }

.wcx{
  --teal:#0f7e7a;--teal-l:#15b6a8;--teal-d:#0a5e5b;--teal-50:#ecf7f6;--teal-100:#d6efed;
  --ink:#0d1417;--txt:#0f172a;--mut:#64748b;--mut-2:#94a3b8;
  --bd:#e6ebf0;--bd-2:#cbd5e1;--soft:#f7f9fa;--soft-2:#eef2f5;
  --lc:#4f6bed;--lc-50:#eef1fe;--amber:#d97706;--amber-50:#fef3e2;
  --list:340px;--info:300px;
  display:grid;grid-template-columns:var(--list) minmax(0,1fr) var(--info);
  height:calc(100vh - var(--topbar-height, 64px));
  background:var(--soft);color:var(--txt);font-size:14px;line-height:1.5;overflow:hidden;
}
.wcx button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}
.wcx ::-webkit-scrollbar{width:9px;height:9px}
.wcx ::-webkit-scrollbar-thumb{background:#cfd8de;border-radius:9px;border:2px solid transparent;background-clip:content-box}
.wcx ::-webkit-scrollbar-thumb:hover{background:#b6c2cb;background-clip:content-box}
.wcx ::-webkit-scrollbar-track{background:transparent}

/* ─────────── LIST ─────────── */
.wcx .list{background:#fff;border-right:1px solid var(--bd);display:flex;flex-direction:column;min-height:0;overflow:hidden}
.wcx .lh{padding:16px 16px 0;flex-shrink:0}
.wcx .lh .t{display:flex;align-items:center;gap:9px;margin-bottom:13px}
.wcx .lh h1{font-size:19px;font-weight:700;letter-spacing:-.02em;margin:0}
.wcx .lh .badge{font-size:11px;font-weight:700;background:var(--teal-50);color:var(--teal);padding:3px 8px;border-radius:999px}
.wcx .lh .grow{margin-inline-start:auto;display:flex;gap:4px;align-items:center}
.wcx .wsdot{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--mut)}
.wcx .wsdot i{width:6px;height:6px;border-radius:50%;background:var(--teal-l)}
.wcx .wsdot.off i{background:var(--mut-2)}
.wcx .iconbtn{width:30px;height:30px;border-radius:8px;display:grid;place-items:center;color:var(--mut);transition:.12s;flex-shrink:0}
.wcx .iconbtn:hover{background:var(--soft-2);color:var(--txt)}
.wcx .search{position:relative;margin-bottom:12px}
.wcx .search svg{position:absolute;inset-inline-start:11px;top:50%;transform:translateY(-50%);color:var(--mut-2);pointer-events:none}
.wcx .search input{width:100%;height:38px;border-radius:10px;border:1px solid var(--bd);background:var(--soft);padding:0 12px 0 34px;font-size:13.5px;outline:none;transition:.15s;color:var(--txt);font-family:inherit}
html[dir="rtl"] .wcx .search input{padding:0 34px 0 12px}
.wcx .search input::placeholder{color:var(--mut-2)}
.wcx .search input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.wcx .tabs{display:flex;gap:2px;border-bottom:1px solid var(--bd);margin:0 -16px;padding:0 16px}
.wcx .tab{padding:9px 11px;font-size:13px;font-weight:600;color:var(--mut);border-bottom:2px solid transparent;margin-bottom:-1px;transition:.12s;display:flex;align-items:center;gap:6px;white-space:nowrap}
.wcx .tab:hover{color:var(--txt)}
.wcx .tab.on{color:var(--teal);border-bottom-color:var(--teal)}
.wcx .tab .n{font-size:10.5px;font-weight:700;background:var(--teal-50);color:var(--teal);padding:1px 6px;border-radius:999px}
.wcx .rows{flex:1;overflow-y:auto;min-height:0}
.wcx .row{display:flex;gap:11px;padding:13px 16px;border-bottom:1px solid #f1f5f7;cursor:pointer;transition:.1s;position:relative;width:100%;text-align:start;align-items:flex-start}
.wcx .row:hover{background:var(--soft)}
.wcx .row.on{background:var(--teal-50)}
.wcx .row.on::before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;width:3px;background:var(--teal)}
.wcx .row .avw{display:block;position:relative;flex-shrink:0}
.wcx .row .av{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;background:var(--teal)}
.wcx .row .ch{position:absolute;inset-inline-end:-2px;bottom:-2px;width:17px;height:17px;border-radius:50%;border:2px solid #fff;display:grid;place-items:center;background:var(--lc)}
.wcx .row.on .ch{border-color:var(--teal-50)}
.wcx .row .m{display:block;flex:1;min-width:0}
.wcx .row .l1{display:flex;align-items:baseline;gap:8px}
.wcx .row .nm{font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;min-width:0}
.wcx .row .tm{font-size:11px;color:var(--mut-2);flex-shrink:0;font-variant-numeric:tabular-nums}
.wcx .row .pv{display:block;font-size:12.5px;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.wcx .row .l3{display:flex;align-items:center;gap:6px;margin-top:6px;flex-wrap:wrap}
.wcx .st{font-size:10px;font-weight:700;letter-spacing:.05em;padding:2.5px 7px;border-radius:5px;text-transform:uppercase}
.wcx .st.pending{background:var(--amber-50);color:var(--amber)}
.wcx .st.assigned{background:var(--teal-50);color:var(--teal)}
.wcx .st.bot{background:var(--lc-50);color:var(--lc)}
.wcx .st.closed{background:var(--soft-2);color:var(--mut)}
.wcx .asg{font-size:11px;color:var(--mut);display:inline-flex;align-items:center;gap:4px;margin-inline-start:auto}
.wcx .asg .a{width:17px;height:17px;border-radius:50%;background:var(--teal-d);color:#fff;font-size:8.5px;font-weight:700;display:grid;place-items:center}
.wcx .empty{padding:46px 22px;text-align:center;color:var(--mut-2);font-size:13.5px;display:flex;flex-direction:column;align-items:center;gap:11px}

/* ─────────── THREAD ─────────── */
.wcx .thread{background:var(--soft);display:flex;flex-direction:column;min-height:0;overflow:hidden}
.wcx .th{background:#fff;border-bottom:1px solid var(--bd);padding:11px 18px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.wcx .th .back{display:none}
.wcx .th .avw{position:relative;flex-shrink:0}
.wcx .th .av{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;background:var(--teal)}
.wcx .th .ch{position:absolute;inset-inline-end:-2px;bottom:-2px;width:17px;height:17px;border-radius:50%;border:2px solid #fff;display:grid;place-items:center;background:var(--lc)}
.wcx .th .m{flex:1;min-width:0}
.wcx .th .n{font-size:15px;font-weight:700;letter-spacing:-.01em;display:flex;align-items:center;gap:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wcx .th .s{font-size:12px;color:var(--mut);display:flex;align-items:center;gap:6px;margin-top:1px}
.wcx .th .acts{display:flex;gap:6px;align-items:center}
.wcx .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:35px;padding:0 14px;border-radius:9px;font-size:13px;font-weight:600;transition:.13s;white-space:nowrap}
.wcx .btn.p{background:var(--teal);color:#fff}
.wcx .btn.p:hover{background:var(--teal-d)}
.wcx .btn.g{background:#fff;color:var(--txt);border:1px solid var(--bd)}
.wcx .btn.g:hover{border-color:var(--bd-2);background:var(--soft)}
.wcx .btn.dgr{background:#fff;color:#dc2626;border:1px solid #fecaca}
.wcx .btn.dgr:hover{background:#fef2f2}
.wcx .btn:disabled{opacity:.55;cursor:not-allowed}

.wcx .msgs{flex:1;overflow-y:auto;padding:22px 24px;display:flex;flex-direction:column;min-height:0}
/* few messages shouldn't leave a hole above the composer — sit them on the bottom */
.wcx .msgs > *:first-child{margin-top:auto}
.wcx .grp{display:flex;gap:9px;margin-top:11px;max-width:74%}
.wcx .grp.out{margin-inline-start:auto;flex-direction:row-reverse}
.wcx .grp .gav{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:10.5px;font-weight:700;color:#fff;flex-shrink:0;align-self:flex-end;margin-bottom:2px;background:var(--teal)}
.wcx .grp.out .gav{background:var(--teal-d)}
.wcx .grp .bubs{display:flex;flex-direction:column;gap:3px;min-width:0}
.wcx .grp.out .bubs{align-items:flex-end}
.wcx .who{font-size:11px;font-weight:600;color:var(--mut);margin-bottom:3px;padding:0 3px}
.wcx .grp.out .who{text-align:end}
.wcx .bub{padding:9px 13px;border-radius:15px;font-size:14px;line-height:1.48;position:relative;overflow-wrap:anywhere;white-space:normal;width:fit-content;max-width:100%}
.wcx .bub > span{white-space:pre-wrap}
.wcx .bub.in{background:#fff;border:1px solid var(--bd);border-end-start-radius:5px}
.wcx .bub.out{background:var(--teal);color:#fff;border-end-end-radius:5px}
.wcx .bub.in + .bub.in{border-end-start-radius:15px;border-start-start-radius:5px}
.wcx .bub.out + .bub.out{border-end-end-radius:15px;border-start-end-radius:5px}
.wcx .bub .tm{font-size:10.5px;opacity:.6;margin-top:2px;display:flex;align-items:center;gap:4px;justify-content:flex-end;font-variant-numeric:tabular-nums;line-height:1.2}
.wcx .bub.in .tm{color:var(--mut-2);opacity:1}
.wcx .sys{background:#fff;border:1px solid var(--bd);color:var(--mut);font-size:12px;font-weight:500;padding:6px 14px;border-radius:999px;margin:12px auto;display:flex;width:fit-content;max-width:100%;align-items:center;gap:7px;text-align:center}
.wcx .nothread{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:var(--mut-2);font-size:14px;padding:24px;text-align:center}
.wcx .nothread .ic{width:58px;height:58px;border-radius:50%;background:#fff;border:1px solid var(--bd);display:grid;place-items:center;color:var(--teal)}

/* composer */
.wcx .comp{background:#fff;border-top:1px solid var(--bd);padding:12px 18px 14px;flex-shrink:0}
.wcx .cbox{border:1px solid var(--bd);border-radius:12px;transition:.15s;background:#fff}
.wcx .cbox:focus-within{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.wcx .cbox textarea{width:100%;border:none;background:none;outline:none;resize:none;padding:11px 13px 4px;font-size:14px;line-height:1.5;color:var(--txt);max-height:130px;min-height:42px;display:block;font-family:inherit}
.wcx .cbox textarea::placeholder{color:var(--mut-2)}
.wcx .crow{display:flex;align-items:center;gap:3px;padding:5px 8px 7px}
.wcx .crow .sp{flex:1;font-size:11.5px;color:var(--mut-2)}
.wcx .send{width:34px;height:34px;border-radius:9px;background:var(--teal);color:#fff;display:grid;place-items:center;transition:.13s;flex-shrink:0}
.wcx .send:hover{background:var(--teal-d)}
.wcx .send:disabled{background:var(--bd-2);cursor:default}
html[dir="rtl"] .wcx .send svg{transform:scaleX(-1)}
.wcx .closed-bar{background:var(--soft-2);border:1px solid var(--bd);border-radius:12px;padding:13px;display:flex;align-items:center;justify-content:center;gap:9px;font-size:13.5px;color:var(--mut);font-weight:500;text-align:center}
.wcx .claim-bar{background:linear-gradient(180deg,var(--amber-50),#fff);border:1px solid #fcd9a4;border-radius:12px;padding:13px 15px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.wcx .claim-bar .m{flex:1;min-width:0}
.wcx .claim-bar .h{font-size:13.5px;font-weight:700;color:#92400e}
.wcx .claim-bar .s{font-size:12.5px;color:var(--amber);margin-top:1px}

/* ─────────── INFO ─────────── */
.wcx .info{background:#fff;border-inline-start:1px solid var(--bd);display:flex;flex-direction:column;min-height:0;overflow:hidden}
.wcx .infoscroll{flex:1;overflow-y:auto;min-height:0}
.wcx .ihero{position:relative;padding:24px 20px 20px;text-align:center;border-bottom:1px solid var(--bd)}
.wcx .ihero .av{width:64px;height:64px;border-radius:50%;margin:0 auto;display:grid;place-items:center;font-size:21px;font-weight:700;color:#fff;background:var(--teal)}
.wcx .ihero .n{font-size:16.5px;font-weight:700;letter-spacing:-.01em;margin-top:11px}
.wcx .ihero .sub{font-size:12.5px;color:var(--mut);margin-top:2px;word-break:break-all}
.wcx .ihero .chip{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:999px;margin-top:10px;background:var(--lc-50);color:var(--lc)}
.wcx .isec{padding:16px 20px;border-bottom:1px solid var(--bd)}
.wcx .isec:last-child{border-bottom:none}
.wcx .isec h4{font-size:10.5px;font-weight:700;letter-spacing:.13em;color:var(--mut-2);text-transform:uppercase;margin:0 0 11px}
.wcx .kv{display:flex;gap:10px;font-size:12.5px;padding:5px 0;align-items:flex-start}
.wcx .kv .k{color:var(--mut);flex-shrink:0;width:74px}
.wcx .kv .v{color:var(--txt);font-weight:500;flex:1;min-width:0;text-align:end;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wcx .infoclose{display:none}

/* ─────────── close modal ─────────── */
.wcx-modal{position:fixed;inset:0;z-index:1200;display:grid;place-items:center;padding:20px;background:rgba(13,20,23,.5)}
.wcx-modal .box{background:#fff;border-radius:16px;width:100%;max-width:440px;padding:24px;box-shadow:0 30px 70px -30px rgba(13,20,23,.5)}
.wcx-modal h3{font-size:18px;font-weight:700;margin:0 0 6px;letter-spacing:-.02em;color:#0f172a}
.wcx-modal p{font-size:13.5px;color:#64748b;margin:0 0 16px;line-height:1.5}
.wcx-modal label{display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;color:#0f172a}
.wcx-modal input{width:100%;height:44px;border:1px solid #e6ebf0;border-radius:10px;padding:0 13px;font-size:14px;outline:none;font-family:inherit;color:#0f172a}
.wcx-modal input:focus{border-color:#0f7e7a;box-shadow:0 0 0 3px rgba(15,126,122,.1)}
.wcx-modal .acts{display:flex;gap:8px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}
.wcx-modal .regen{font-size:12.5px;font-weight:600;color:#0f7e7a;margin-top:8px;display:inline-flex;align-items:center;gap:6px}

/* ─────────── skeletons ─────────── */
.wcx .sk{background:linear-gradient(90deg,var(--soft-2) 25%,#f7f9fa 37%,var(--soft-2) 63%);background-size:400% 100%;animation:wcxsk 1.4s ease infinite;border-radius:6px;flex-shrink:0}
@keyframes wcxsk{0%{background-position:100% 50%}100%{background-position:0 50%}}
.wcx .skrow{display:flex;gap:11px;padding:13px 16px;border-bottom:1px solid #f1f5f7;align-items:flex-start}
.wcx .skrow .skav{width:40px;height:40px;border-radius:50%}
.wcx .skrow .skm{flex:1;min-width:0;display:flex;flex-direction:column;gap:7px;padding-top:3px}
.wcx .skth{display:flex;align-items:center;gap:12px;padding:11px 18px;background:#fff;border-bottom:1px solid var(--bd);flex-shrink:0}
.wcx .skth .skav{width:40px;height:40px;border-radius:50%}
.wcx .skth .skm{flex:1;min-width:0;display:flex;flex-direction:column;gap:7px}
.wcx .skmsgs{flex:1;padding:22px 24px;display:flex;flex-direction:column;gap:14px;overflow:hidden}
.wcx .skbub{height:44px;border-radius:15px;max-width:62%}
.wcx .skbub.out{margin-inline-start:auto}
.wcx .skcomp{background:#fff;border-top:1px solid var(--bd);padding:12px 18px 14px;flex-shrink:0}
.wcx .skcomp .skbox{height:74px;border-radius:12px}
@media (prefers-reduced-motion:reduce){.wcx .sk{animation:none}}

/* ─────────── responsive ─────────── */
@media (max-width:1180px){
  .wcx{--list:300px;--info:270px}
  .wcx .msgs{padding:18px 16px}
}
@media (max-width:1024px){
  .wcx{grid-template-columns:var(--list) minmax(0,1fr)}
  .wcx .info{position:fixed;top:0;inset-inline-end:0;bottom:0;width:min(320px,90vw);z-index:1100;transform:translateX(100%);transition:transform .24s cubic-bezier(.4,0,.2,1);box-shadow:-20px 0 50px -20px rgba(13,20,23,.25)}
  html[dir="rtl"] .wcx .info{transform:translateX(-100%)}
  .wcx .info.open{transform:translateX(0)}
  .wcx .infoclose{display:grid}
}
@media (max-width:820px){
  .wcx{grid-template-columns:1fr;height:calc(100vh - var(--topbar-height, 64px))}
  .wcx .list,.wcx .thread{grid-column:1;grid-row:1}
  .wcx .list{border-right:none}
  .wcx .thread{display:none}
  .wcx.v-thread .list{display:none}
  .wcx.v-thread .thread{display:flex}
  .wcx .th .back{display:grid}
  .wcx .msgs{padding:16px 14px}
  .wcx .grp{max-width:86%}
  .wcx .comp{padding:10px 12px 12px}
  .wcx .lh{padding:14px 14px 0}
  .wcx .tabs{margin:0 -14px;padding:0 14px;overflow-x:auto}
  .wcx .row{padding:12px 14px}
}
</style>
@endpush

@section('content')

@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $i18n = [
        'title'                 => __('ui.webchat_page.title'),
        'online'                => __('ui.webchat_page.online'),
        'offline'               => __('ui.webchat_page.offline'),
        'tab_pending'           => __('ui.webchat_page.tab_pending'),
        'tab_mine'              => __('ui.webchat_page.tab_mine'),
        'tab_all'               => __('ui.webchat_page.tab_all'),
        'tab_closed'            => __('ui.webchat_page.tab_closed'),
        'no_conversations'      => __('ui.webchat_page.no_conversations'),
        'select_conversation'   => __('ui.webchat_page.select_conversation'),
        'visitor_prefix'        => __('ui.webchat_page.visitor_prefix'),
        'anonymous'             => __('ui.webchat_page.anonymous'),
        'no_messages_yet'       => __('ui.webchat_page.no_messages_yet'),
        'just_now'              => __('ui.webchat_page.just_now'),
        'claim_btn'             => __('ui.webchat_page.claim_btn'),
        'claim_to_reply'        => __('ui.webchat_page.claim_to_reply'),
        'claimed_by_you'        => __('ui.webchat_page.claimed_by_you'),
        'claimed_by_prefix'     => __('ui.webchat_page.claimed_by_prefix'),
        'locked_by_agent'       => __('ui.webchat_page.locked_by_agent'),
        'chat_closed'           => __('ui.webchat_page.chat_closed'),
        'close_chat'            => __('ui.webchat_page.close_chat'),
        'close_confirm'         => __('ui.webchat_page.close_confirm'),
        'release_chat'          => __('ui.webchat_page.release_chat'),
        'composer_placeholder'  => __('ui.webchat_page.composer_placeholder'),
        'status_bot'            => __('ui.webchat_page.status_bot'),
        'status_pending'        => __('ui.webchat_page.status_pending'),
        'status_assigned'       => __('ui.webchat_page.status_assigned'),
        'status_closed'         => __('ui.webchat_page.status_closed'),
        'visitor_info'          => __('ui.webchat_page.visitor_info'),
        'name'                  => __('ui.webchat_page.name'),
        'email'                 => __('ui.webchat_page.email'),
        'page'                  => __('ui.webchat_page.page'),
        'referrer'              => __('ui.webchat_page.referrer'),
        'browser'               => __('ui.webchat_page.browser'),
        'ip'                    => __('ui.webchat_page.ip'),
        'started_at'            => __('ui.webchat_page.started_at'),
        'claim_success'         => __('ui.webchat_page.claim_success'),
        'claim_race_lost'       => __('ui.webchat_page.claim_race_lost'),
        'claim_error'           => __('ui.webchat_page.claim_error'),
        'release_success'       => __('ui.webchat_page.release_success'),
        'release_error'         => __('ui.webchat_page.release_error'),
        'close_success'         => __('ui.webchat_page.close_success'),
        'close_error'           => __('ui.webchat_page.close_error'),
        'send_error'            => __('ui.webchat_page.send_error'),
        'not_your_conversation' => __('ui.webchat_page.not_your_conversation'),
        'conversation_closed'   => __('ui.webchat_page.conversation_closed'),
        'new_pending_toast'     => __('ui.webchat_page.new_pending_toast'),

        'close_modal_title'             => __('ui.conversation_show_page.close_modal_title'),
        'close_modal_intro'             => __('ui.conversation_show_page.close_modal_intro'),
        'close_modal_title_label'       => __('ui.conversation_show_page.close_modal_title_label'),
        'close_modal_title_placeholder' => __('ui.conversation_show_page.close_modal_title_placeholder'),
        'close_modal_generating'        => __('ui.conversation_show_page.close_modal_generating'),
        'close_modal_regenerate'        => __('ui.conversation_show_page.close_modal_regenerate'),
        'close_modal_close_btn'         => __('ui.conversation_show_page.close_modal_close_btn'),
        'close_modal_cancel'            => __('ui.conversation_show_page.close_modal_cancel'),
        'close_modal_generate_failed'   => __('ui.conversation_show_page.close_modal_generate_failed'),
    ];
@endphp

<div x-data="webchatInbox()" x-init="init()" x-cloak class="wcx" :class="{ 'v-thread': mobileThread }">

    {{-- ═══════════ LIST ═══════════ --}}
    <section class="list">
        <div class="lh">
            <div class="t">
                <h1>{{ __('ui.webchat_page.title') }}</h1>
                <span class="badge" x-text="visible.length"></span>
                <div class="grow">
                    <span class="wsdot" :class="{ 'off': !wsConnected }">
                        <i></i><span x-text="wsConnected ? i18n.online : i18n.offline"></span>
                    </span>
                    <button type="button" class="iconbtn" @click="loadList()" :title="i18n.title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 11a8 8 0 10-2.3 5.7M20 5v6h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>

            <div class="search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="text" x-model="q" placeholder="{{ __('ui.webchat_page.search_placeholder') }}">
            </div>

            <div class="tabs">
                <template x-for="t in tabs" :key="t">
                    <button type="button" class="tab" :class="{ 'on': filter === t }" @click="setFilter(t)">
                        <span x-text="i18n['tab_' + t]"></span>
                        <span class="n" x-show="filter === t" x-text="visible.length"></span>
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

            <template x-if="!loading && visible.length === 0">
                <div class="empty">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    <span x-text="i18n.no_conversations"></span>
                </div>
            </template>

            <template x-for="conv in (loading ? [] : visible)" :key="conv.uuid">
                <button type="button" class="row" :class="{ 'on': activeUuid === conv.uuid }" @click="openRow(conv)">
                    <span class="avw">
                        <span class="av" x-text="visitorInitials(conv)"></span>
                        <span class="ch">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="4" width="19" height="13" rx="2.5" fill="#fff"/><path d="M8 20l3-3h2l-5 3z" fill="#fff"/><path d="M7 9h10M7 12.5h6" stroke="#4f6bed" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                    </span>
                    <span class="m">
                        <span class="l1">
                            <span class="nm" x-text="displayName(conv)"></span>
                            <span class="tm" x-text="timeAgo(conv.last_activity_at || conv.created_at)"></span>
                        </span>
                        <span class="pv" x-text="conv.title || conv.last_message_preview || i18n.no_messages_yet"></span>
                        <span class="l3">
                            <span class="st" :class="conv.status" x-text="i18n['status_' + conv.status]"></span>
                            <template x-if="conv.status === 'assigned' && conv.claimer">
                                <span class="asg">
                                    <span class="a" x-text="visitorInitials({ name: conv.claimer.name })"></span>
                                    <span x-text="conv.claimer.id === myId ? i18n.claimed_by_you : conv.claimer.name"></span>
                                </span>
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
                    <div class="sk skbub" style="width:46%"></div>
                </div>
                <div class="skcomp"><div class="sk skbox"></div></div>
            </div>
        </template>

        <template x-if="!activeUuid && !threadLoading">
            <div class="nothread">
                <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
                <span x-text="i18n.select_conversation"></span>
            </div>
        </template>

        <template x-if="activeUuid && !threadLoading">
            <div style="display:flex;flex-direction:column;min-height:0;flex:1">
                {{-- header --}}
                <div class="th">
                    <button type="button" class="iconbtn back" @click="backToList()">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 5l-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <span class="avw">
                        <span class="av" x-text="visitorInitials(active.conversation || active.visitor)"></span>
                        <span class="ch">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="4" width="19" height="13" rx="2.5" fill="#fff"/><path d="M8 20l3-3h2l-5 3z" fill="#fff"/><path d="M7 9h10M7 12.5h6" stroke="#4f6bed" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                    </span>
                    <div class="m">
                        <div class="n" x-text="active.conversation ? displayName(active.conversation) : ''"></div>
                        <div class="s">
                            <span class="st" :class="active.conversation?.status" x-text="active.conversation ? i18n['status_' + active.conversation.status] : ''"></span>
                            <template x-if="active.conversation?.status === 'assigned' && active.conversation?.claimer">
                                <span x-text="active.conversation.claimer.id === myId ? i18n.claimed_by_you : (i18n.claimed_by_prefix + ' ' + active.conversation.claimer.name)"></span>
                            </template>
                        </div>
                    </div>
                    <div class="acts">
                        <template x-if="canManageLock() && isMyClaim()">
                            <button type="button" class="btn g" @click="release()">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 7h2a5 5 0 010 10h-2M9 17H7A5 5 0 017 7h2M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                <span x-text="i18n.release_chat"></span>
                            </button>
                        </template>
                        <template x-if="canManageLock()">
                            <button type="button" class="btn dgr" @click="close()">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                                <span x-text="i18n.close_chat"></span>
                            </button>
                        </template>
                        <button type="button" class="iconbtn" @click="infoOpen = !infoOpen" :title="i18n.visitor_info">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5.5M12 7.6h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>

                {{-- messages --}}
                <div class="msgs" x-ref="thread">
                    <template x-for="g in groups" :key="g.id">
                        <div>
                            <template x-if="g.type === 'sys'">
                                <div class="sys" x-text="g.body"></div>
                            </template>
                            <template x-if="g.type === 'msg'">
                                <div class="grp" :class="g.side === 'out' ? 'out' : ''">
                                    <div class="gav" x-text="g.initials"></div>
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

                    <template x-if="messages.length === 0">
                        <div class="sys" x-text="i18n.no_messages_yet"></div>
                    </template>
                </div>

                {{-- composer --}}
                <div class="comp">
                    <template x-if="active.conversation?.status === 'closed'">
                        <div class="closed-bar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 118 0v3" stroke="currentColor" stroke-width="1.8"/></svg>
                            <span x-text="i18n.chat_closed"></span>
                        </div>
                    </template>

                    <template x-if="active.conversation?.status !== 'closed' && !isMyClaim()">
                        <div>
                            <template x-if="active.conversation?.status === 'pending' || active.conversation?.status === 'bot'">
                                <div class="claim-bar">
                                    <div class="m">
                                        <div class="h" x-text="i18n.claim_to_reply"></div>
                                        <div class="s" x-text="i18n.status_pending"></div>
                                    </div>
                                    <button type="button" class="btn p" @click="claim(active.conversation.uuid)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <span x-text="i18n.claim_btn"></span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="active.conversation?.status === 'assigned'">
                                <div class="closed-bar">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 118 0v3" stroke="currentColor" stroke-width="1.8"/></svg>
                                    <span x-text="i18n.locked_by_agent"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="active.conversation?.status !== 'closed' && isMyClaim()">
                        <div class="cbox">
                            <textarea
                                x-model="composer"
                                @keydown.enter.exact.prevent="sendMessage()"
                                rows="1"
                                :placeholder="i18n.composer_placeholder"></textarea>
                            <div class="crow">
                                <span class="sp">↵ {{ __('ui.webchat_page.enter_to_send') }}</span>
                                <button type="button" class="send" @click="sendMessage()" :disabled="!composer.trim() || sending">
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
    <aside class="info" :class="{ 'open': infoOpen }" x-show="activeUuid && !threadLoading" x-cloak>
        <div class="infoscroll">
            <div class="ihero">
                <button type="button" class="iconbtn infoclose" style="position:absolute;top:12px;inset-inline-end:12px" @click="infoOpen = false">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
                <div class="av" x-text="visitorInitials(active.conversation || active.visitor)"></div>
                <div class="n" x-text="active.visitor?.name || (active.conversation ? displayName(active.conversation) : i18n.anonymous)"></div>
                <div class="sub" x-text="active.visitor?.email || ''"></div>
                <div class="chip">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="4" width="19" height="13" rx="2.5" stroke="currentColor" stroke-width="2"/><path d="M8 20l3-3h2l-5 3z" fill="currentColor"/></svg>
                    {{ __('ui.webchat_page.channel_live_chat') }}
                </div>
            </div>

            <div class="isec">
                <h4 x-text="i18n.visitor_info"></h4>
                <template x-if="active.visitor?.name">
                    <div class="kv"><span class="k" x-text="i18n.name"></span><span class="v" x-text="active.visitor.name"></span></div>
                </template>
                <template x-if="active.visitor?.email">
                    <div class="kv"><span class="k" x-text="i18n.email"></span><span class="v" :title="active.visitor.email" x-text="active.visitor.email"></span></div>
                </template>
                <template x-if="active.meta?.page_url">
                    <div class="kv"><span class="k" x-text="i18n.page"></span><span class="v" :title="active.meta.page_url" x-text="active.meta.page_url"></span></div>
                </template>
                <template x-if="active.meta?.referrer">
                    <div class="kv"><span class="k" x-text="i18n.referrer"></span><span class="v" :title="active.meta.referrer" x-text="active.meta.referrer"></span></div>
                </template>
                <template x-if="active.meta?.user_agent">
                    <div class="kv"><span class="k" x-text="i18n.browser"></span><span class="v" :title="active.meta.user_agent" x-text="active.meta.user_agent"></span></div>
                </template>
                <template x-if="active.meta?.ip">
                    <div class="kv"><span class="k" x-text="i18n.ip"></span><span class="v" x-text="active.meta.ip"></span></div>
                </template>
                <div class="kv">
                    <span class="k" x-text="i18n.started_at"></span>
                    <span class="v" x-text="formatDateTime(active.conversation?.created_at)"></span>
                </div>
            </div>
        </div>
    </aside>

    {{-- ═══════════ close modal ═══════════ --}}
    <template x-if="showCloseModal">
        <div class="wcx-modal" @click.self="cancelCloseModal()">
            <div class="box">
                <h3 x-text="i18n.close_modal_title"></h3>
                <p x-text="i18n.close_modal_intro"></p>
                <label x-text="i18n.close_modal_title_label"></label>
                <input type="text" x-model="closeTitle" :placeholder="i18n.close_modal_title_placeholder" :disabled="closeTitleLoading">
                <button type="button" class="regen" @click="suggestCloseTitle()" :disabled="closeTitleLoading">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="currentColor"/></svg>
                    <span x-text="closeTitleLoading ? i18n.close_modal_generating : i18n.close_modal_regenerate"></span>
                </button>
                <div class="acts">
                    <button type="button" class="btn g" @click="cancelCloseModal()" x-text="i18n.close_modal_cancel"></button>
                    <button type="button" class="btn dgr" @click="submitCloseWithTitle()" :disabled="closing" x-text="i18n.close_modal_close_btn"></button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function webchatInbox() {
    return {
        i18n:           @json($i18n),
        listUrl:        @json(route($panelPrefix . '.webchat.conversations.index')),
        showUrlTpl:     @json(route($panelPrefix . '.webchat.conversations.show',    ['uuid' => '__UUID__'])),
        claimUrlTpl:    @json(route($panelPrefix . '.webchat.conversations.claim',   ['uuid' => '__UUID__'])),
        releaseUrlTpl:  @json(route($panelPrefix . '.webchat.conversations.release', ['uuid' => '__UUID__'])),
        closeUrlTpl:    @json(route($panelPrefix . '.webchat.conversations.close',   ['uuid' => '__UUID__'])),
        suggestTitleUrlTpl: @json(route($panelPrefix . '.webchat.conversations.suggest-title', ['uuid' => '__UUID__'])),
        readUrlTpl:     @json(route($panelPrefix . '.webchat.conversations.read',    ['uuid' => '__UUID__'])),
        messageUrlTpl:  @json(route($panelPrefix . '.webchat.messages.store',        ['uuid' => '__UUID__'])),
        tenantId:       @json((int) auth()->user()->tenant_id),
        myId:           @json((int) auth()->id()),
        myName:         @json((string) auth()->user()->name),
        isAdmin:        @json((bool) auth()->user()->isAdmin()),

        tabs:           ['pending', 'mine', 'all', 'closed'],
        filter:         'pending',
        conversations:  [],
        loading:        false,
        q:              '',
        infoOpen:       false,
        mobileThread:   false,
        threadLoading:  false,

        activeUuid:     null,
        active:         { conversation: null, visitor: null, widget: null, meta: null },
        messages:       [],
        composer:       '',
        sending:        false,

        wsConnected:    false,
        _pollTimer:     null,
        _threadPoll:    null,

        showCloseModal:    false,
        closeTitle:        '',
        closeTitleLoading: false,
        closing:           false,

        get pendingCount() {
            return this.conversations.filter(c => c.status === 'pending').length;
        },

        // Client-side search over the rows already loaded for the active tab.
        get visible() {
            const q = this.q.trim().toLowerCase();
            if (!q) return this.conversations;
            return this.conversations.filter(c =>
                (this.displayName(c) || '').toLowerCase().includes(q)
                || (c.visitor_email || '').toLowerCase().includes(q)
                || (c.title || '').toLowerCase().includes(q)
                || (c.last_message_preview || '').toLowerCase().includes(q)
            );
        },

        // Consecutive messages from the same sender collapse into one group so a
        // short "hello" doesn't drag an avatar and a name row along with it.
        get groups() {
            const GAP_MS = 5 * 60 * 1000;
            const out = [];

            for (const m of this.messages) {
                if (m.sender_type === 'system') {
                    out.push({ type: 'sys', id: 's' + m.id, body: (m.body || '').trim() });
                    continue;
                }

                const side = m.sender_type === 'visitor' ? 'in' : 'out';
                const who  = side === 'in'
                    ? this.displayName(this.active.conversation || {})
                    : (m.sender?.name || this.myName);

                const prev = out[out.length - 1];
                const near = prev && prev.type === 'msg'
                    && prev.side === side
                    && prev.who === who
                    && Math.abs(new Date(m.created_at) - new Date(prev.items[prev.items.length - 1].created_at)) < GAP_MS;

                if (near) {
                    prev.items.push(m);
                } else {
                    out.push({
                        type: 'msg',
                        id: 'g' + m.id,
                        side,
                        who,
                        initials: side === 'in'
                            ? this.visitorInitials(this.active.conversation || this.active.visitor)
                            : this.visitorInitials({ name: who }),
                        items: [m],
                    });
                }
            }

            return out;
        },

        // On narrow screens list and thread share one column.
        backToList() {
            this.mobileThread = false;
            this.infoOpen = false;
        },

        init() {
            this.loadList();
            this.subscribePresence();

            // Poll list every 15s as a broadcast fallback. When a thread is
            // open, the 6s thread poll from _startThreadPoll takes over the
            // per-conversation freshness so the list poll can stay slow.
            this._pollTimer = setInterval(() => {
                this.loadList(true);
            }, 15000);

            if (window._echoStateListeners) {
                window._echoStateListeners.push((c) => { this.wsConnected = c; });
            }
            this.wsConnected = !!window._echoConnected;

            window.addEventListener('beforeunload', () => this.cleanup());
        },

        cleanup() {
            if (this._pollTimer)  clearInterval(this._pollTimer);
            if (this._threadPoll) clearInterval(this._threadPoll);
        },

        _startThreadPoll() {
            if (this._threadPoll) return;
            this._threadPoll = setInterval(() => {
                if (this.wsConnected || !this.activeUuid) return;
                this._pollThreadDelta();
            }, 6000);
        },

        _stopThreadPoll() {
            if (this._threadPoll) { clearInterval(this._threadPoll); this._threadPoll = null; }
        },

        async _pollThreadDelta() {
            if (!this.activeUuid) return;
            const lastId = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
            const url = this.showUrlTpl.replace('__UUID__', this.activeUuid) + '?after=' + lastId;
            try {
                const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const data = await r.json();
                const added = (data.messages || []).filter(m => !this.messages.some(x => x.id === m.id));
                if (added.length) {
                    this.messages.push(...added);
                    if (this.isMyClaim()) this.markRead(this.activeUuid);
                    this.$nextTick(() => this.scrollThreadBottom());
                }
                if (data.conversation && this.active.conversation && data.conversation.status !== this.active.conversation.status) {
                    this.active.conversation = data.conversation;
                }
            } catch (e) { /* silent — next tick will retry */ }
        },

        setFilter(f) {
            if (this.filter === f) return;
            this.filter = f;
            this.activeUuid = null;
            this.active = { conversation: null, visitor: null, widget: null, meta: null };
            this.messages = [];
            this.mobileThread = false;
            this.loadList();
        },

        async loadList(silent = false) {
            if (!silent) this.loading = true;
            try {
                const url = new URL(this.listUrl, window.location.origin);
                url.searchParams.set('filter', this.filter);
                const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                this.conversations = data.data || [];
            } catch (e) {
                console.error('[webchat] loadList failed', e);
            } finally {
                if (!silent) this.loading = false;
            }
        },

        async openRow(conv) {
            this.mobileThread = true;
            if (this.activeUuid === conv.uuid) return;
            this.activeUuid = conv.uuid;
            this.messages = [];
            this.active = { conversation: null, visitor: null, widget: null, meta: null };
            this.threadLoading = true;

            try {
                const r = await fetch(this.showUrlTpl.replace('__UUID__', conv.uuid), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                this.active = {
                    conversation: data.conversation,
                    visitor: data.visitor,
                    widget: data.widget,
                    meta: data.meta,
                };
                this.messages = data.messages || [];

                this.subscribeThread(conv.uuid);
                this._startThreadPoll();

                if (this.isMyClaim()) this.markRead(conv.uuid);

                // Drop the skeleton first so the thread exists to scroll.
                this.threadLoading = false;
                await this.$nextTick();
                this.scrollThreadBottom();
            } catch (e) {
                console.error('[webchat] openRow failed', e);
                this.activeUuid = null;
                this.mobileThread = false;
                window.showToast?.('error', 'Could not open conversation');
            } finally {
                this.threadLoading = false;
            }
        },

        async claim(uuid) {
            try {
                const r = await this.post(this.claimUrlTpl.replace('__UUID__', uuid));
                if (r.status === 409) {
                    window.showToast?.('error', this.i18n.claim_race_lost);
                    this.loadList();
                    return;
                }
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();
                window.showToast?.('success', this.i18n.claim_success);

                const idx = this.conversations.findIndex(c => c.uuid === uuid);
                if (idx !== -1) {
                    this.conversations[idx] = {
                        ...this.conversations[idx],
                        status: 'assigned',
                        claimer: data.conversation.claimer,
                    };
                }
                if (this.activeUuid === uuid && this.active.conversation) {
                    this.active.conversation = { ...this.active.conversation, ...data.conversation };
                }
            } catch (e) {
                console.error('[webchat] claim failed', e);
                window.showToast?.('error', this.i18n.claim_error);
            }
        },

        async release() {
            if (!this.activeUuid) return;
            try {
                const r = await this.post(this.releaseUrlTpl.replace('__UUID__', this.activeUuid));
                if (!r.ok) throw new Error('HTTP ' + r.status);
                window.showToast?.('success', this.i18n.release_success);
                this.loadList();
            } catch (e) {
                console.error('[webchat] release failed', e);
                window.showToast?.('error', this.i18n.release_error);
            }
        },

        close() {
            if (!this.activeUuid) return;
            this.closeTitle = '';
            this.showCloseModal = true;
            this.suggestCloseTitle();
        },

        async suggestCloseTitle() {
            if (!this.activeUuid) return;
            this.closeTitleLoading = true;
            try {
                const r = await this.post(this.suggestTitleUrlTpl.replace('__UUID__', this.activeUuid));
                if (r.ok) {
                    const data = await r.json();
                    if (data.title) this.closeTitle = data.title;
                }
            } catch (e) {
                console.warn('[webchat] title suggest failed', e);
                window.showToast?.('error', this.i18n.close_modal_generate_failed);
            } finally {
                this.closeTitleLoading = false;
            }
        },

        cancelCloseModal() {
            this.showCloseModal = false;
            this.closeTitle = '';
            this.closeTitleLoading = false;
        },

        async submitCloseWithTitle() {
            if (!this.activeUuid || this.closing) return;
            this.closing = true;
            try {
                const url = this.closeUrlTpl.replace('__UUID__', this.activeUuid);
                const r = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ title: (this.closeTitle || '').trim() })
                });
                if (!r.ok) {
                    // Read the body once so we can log and surface it to the user.
                    const bodyText = await r.text().catch(() => '');
                    let msg = '';
                    try { msg = JSON.parse(bodyText)?.message || ''; } catch (_) {}
                    console.error('[webchat] close failed', r.status, bodyText);
                    const detail = msg || `HTTP ${r.status}`;
                    window.showToast?.('error', `${this.i18n.close_error} (${detail})`);
                    return;
                }
                const data = await r.json();
                if (this.active.conversation) {
                    this.active.conversation.title = data.conversation?.title || this.closeTitle;
                }
                window.showToast?.('success', this.i18n.close_success);
                this.showCloseModal = false;
                this.loadList(true);
            } catch (e) {
                console.error('[webchat] close failed', e);
                window.showToast?.('error', `${this.i18n.close_error} (${e.message || 'network'})`);
            } finally {
                this.closing = false;
            }
        },

        async sendMessage() {
            const body = this.composer.trim();
            if (!body || !this.activeUuid || this.sending) return;
            if (!this.isMyClaim()) return;

            this.sending = true;
            try {
                const r = await fetch(this.messageUrlTpl.replace('__UUID__', this.activeUuid), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body }),
                });
                if (r.status === 403) { window.showToast?.('error', this.i18n.not_your_conversation); return; }
                if (r.status === 409) { window.showToast?.('error', this.i18n.conversation_closed); return; }
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();

                this.composer = '';

                if (!this.messages.some(m => m.id === data.message.id)) {
                    this.messages.push({
                        id:          data.message.id,
                        sender_type: data.message.sender_type,
                        sender_id:   data.message.sender_id,
                        sender:      { id: this.myId, name: this.myName },
                        body:        data.message.body,
                        created_at:  data.message.created_at,
                    });
                    await this.$nextTick();
                    this.scrollThreadBottom();
                }
            } catch (e) {
                console.error('[webchat] send failed', e);
                window.showToast?.('error', this.i18n.send_error);
            } finally {
                this.sending = false;
            }
        },

        async markRead(uuid) {
            try { await this.post(this.readUrlTpl.replace('__UUID__', uuid)); } catch (e) {}
        },

        subscribePresence() {
            if (!window.Echo || typeof window.Echo.join !== 'function') return;
            try {
                const ch = window.Echo.join('webchat.tenant.' + this.tenantId);
                ch.listen('.webchat.conversation.requested', (p) => this.onRequested(p));
                ch.listen('.webchat.conversation.claimed',   (p) => this.onClaimed(p));
                ch.listen('.webchat.conversation.released',  (p) => this.onReleased(p));
                ch.listen('.webchat.conversation.closed',    (p) => this.onClosed(p));
                ch.listen('.webchat.message.sent',           (p) => this.onPresenceMessage(p));
            } catch (e) {
                console.warn('[webchat] presence subscribe failed', e);
            }
        },

        subscribeThread(uuid) {
            if (!window.Echo) return;
            try {
                const ch = window.Echo.private('webchat.conversation.' + uuid);
                ch.listen('.webchat.message.sent',        (p) => this.onThreadMessage(p));
                ch.listen('.webchat.conversation.closed', (p) => this.onThreadClosed(p));
            } catch (e) {
                console.warn('[webchat] thread subscribe failed', e);
            }
        },

        onRequested(payload) {
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].status = 'pending';
                this.conversations[idx].last_activity_at = conv.last_activity_at;
                this.conversations[idx].claimer = null;
            } else if (this.filter === 'pending' || this.filter === 'all') {
                this.conversations.unshift({
                    uuid: conv.uuid,
                    status: 'pending',
                    visitor_name: conv.visitor_name,
                    visitor_email: conv.visitor_email,
                    page_url: conv.page_url,
                    last_activity_at: conv.last_activity_at,
                    created_at: conv.created_at,
                    claimer: null,
                    last_message_preview: null,
                });
                window.showToast?.('success', this.i18n.new_pending_toast);
            }
        },

        onClaimed(payload) {
            const conv = payload.conversation;
            const agent = payload.agent;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].status = 'assigned';
                this.conversations[idx].claimer = { id: agent.id, name: agent.name };
                this.conversations[idx].last_activity_at = conv.last_activity_at;
            }
            if (this.activeUuid === conv.uuid && this.active.conversation) {
                this.active.conversation = {
                    ...this.active.conversation,
                    status: 'assigned',
                    claimed_by: agent.id,
                    claimer: { id: agent.id, name: agent.name },
                };
            }
        },

        onReleased(payload) {
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].status = 'pending';
                this.conversations[idx].claimer = null;
            }
            if (this.activeUuid === conv.uuid && this.active.conversation) {
                this.active.conversation = { ...this.active.conversation, status: 'pending', claimed_by: null, claimer: null };
            }
        },

        onClosed(payload) {
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                if (this.filter === 'pending' || this.filter === 'mine') {
                    this.conversations.splice(idx, 1);
                } else {
                    this.conversations[idx].status = 'closed';
                    this.conversations[idx].claimer = null;
                }
            }
            if (this.activeUuid === conv.uuid && this.active.conversation) {
                this.active.conversation = { ...this.active.conversation, status: 'closed', claimed_by: null, claimer: null };
            }
        },

        onPresenceMessage(payload) {
            const msg = payload.message;
            const conv = payload.conversation;
            const idx = this.conversations.findIndex(c => c.uuid === conv.uuid);
            if (idx !== -1) {
                this.conversations[idx].last_message_preview = (msg.body || '').slice(0, 120);
                this.conversations[idx].last_activity_at = conv.last_activity_at;
            }
        },

        onThreadMessage(payload) {
            const msg = payload.message;
            if (msg.conversation_uuid !== this.activeUuid) return;
            if (this.messages.some(m => m.id === msg.id)) return;
            this.messages.push({
                id:          msg.id,
                sender_type: msg.sender_type,
                sender_id:   msg.sender_id,
                sender:      msg.sender_type === 'agent' && msg.sender_id === this.myId
                                ? { id: this.myId, name: this.myName }
                                : null,
                body:        msg.body,
                created_at:  msg.created_at,
            });
            this.$nextTick(() => this.scrollThreadBottom());
        },

        onThreadClosed(payload) {
            if (payload.conversation.uuid !== this.activeUuid) return;
            if (this.active.conversation) {
                this.active.conversation = {
                    ...this.active.conversation,
                    status: 'closed',
                    claimed_by: null,
                    claimer: null,
                };
            }
        },

        post(url, body = {}) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
        },

        csrf() {
            return document.querySelector('meta[name=csrf-token]')?.content || '';
        },

        isMyClaim() {
            const c = this.active.conversation;
            if (!c || c.status !== 'assigned') return false;
            return c.claimed_by === this.myId
                || (c.claimer && c.claimer.id === this.myId);
        },

        isRowLocked(conv) {
            return conv.status === 'assigned'
                && conv.claimer
                && conv.claimer.id !== this.myId
                && !this.isAdmin;
        },

        canManageLock() {
            const c = this.active.conversation;
            if (!c || c.status === 'closed') return false;
            const isClaimer = c.claimed_by === this.myId
                || (c.claimer && c.claimer.id === this.myId);
            return isClaimer || this.isAdmin;
        },

        displayName(conv) {
            if (conv.visitor_name) return conv.visitor_name;
            return this.i18n.visitor_prefix + (conv.uuid ? conv.uuid.slice(0, 6) : '');
        },

        // Two-letter initials for the round avatar. Falls back to a globe glyph
        // when no name and no uuid are available.
        visitorInitials(src) {
            if (!src) return '·';
            const name = src.visitor_name || src.name || '';
            if (name) {
                const parts = name.trim().split(/\s+/);
                const a = parts[0]?.[0] || '';
                const b = parts.length > 1 ? parts[parts.length - 1][0] : '';
                return (a + b).toUpperCase() || '·';
            }
            const uuid = src.uuid || '';
            return uuid ? uuid.slice(0, 2).toUpperCase() : '·';
        },

        // Rail-row pill (small)
        statePillClass(status) {
            return ({
                bot:      'cw-pill-closed',
                pending:  'cw-pill-pool',
                assigned: 'cw-pill-claimed',
                closed:   'cw-pill-closed',
            })[status] || 'cw-pill-closed';
        },

        // Thread-header state badge (larger)
        stateBadgeClass(status) {
            return ({
                bot:      'cw-state-neutral',
                pending:  'cw-state-pool',
                assigned: 'cw-state-claimed',
                closed:   'cw-state-closed',
            })[status] || 'cw-state-neutral';
        },

        timeAgo(iso) {
            if (!iso) return '';
            const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
            if (s < 60)    return this.i18n.just_now;
            if (s < 3600)  return Math.floor(s / 60) + 'm';
            if (s < 86400) return Math.floor(s / 3600) + 'h';
            return Math.floor(s / 86400) + 'd';
        },

        formatTime(iso) {
            if (!iso) return '';
            try {
                return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (e) { return ''; }
        },

        formatDateTime(iso) {
            if (!iso) return '';
            try {
                return new Date(iso).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
            } catch (e) { return ''; }
        },

        scrollThreadBottom() {
            if (this.$refs.thread) {
                this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight;
            }
        },
    };
}
</script>

@endsection
