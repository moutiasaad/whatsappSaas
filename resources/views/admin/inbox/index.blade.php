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
  --wa:#25a35a;--wa-50:#e9f7ee;--lc:#4f6bed;--lc-50:#eef1fe;--fb:#0866ff;--fb-50:#e7f0ff;
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
.ubx .chan .d.fb{background:var(--fb)}
.ubx .chan .d.all{background:conic-gradient(var(--wa) 0 33%,var(--lc) 33% 66%,var(--fb) 66% 100%)}

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
.ubx .ch.fb{background:var(--fb)}
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
.ubx .st.esc{background:rgba(220,38,38,.12);color:#b91c1c}
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
.ubx .grp{display:flex;gap:9px;margin-top:11px;max-width:74%}
.ubx .grp.out{margin-inline-start:auto;flex-direction:row-reverse}
.ubx .grp .gav{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:10.5px;font-weight:700;color:#fff;flex-shrink:0;align-self:flex-end;margin-bottom:2px;background:var(--teal)}
.ubx .grp.out .gav{background:var(--teal-d)}
.ubx .grp .bubs{display:flex;flex-direction:column;gap:3px;min-width:0}
.ubx .grp.out .bubs{align-items:flex-end}
.ubx .who{font-size:11px;font-weight:600;color:var(--mut);margin-bottom:3px;padding:0 3px}
.ubx .grp.out .who{text-align:end}
.ubx .bub{padding:9px 13px;border-radius:15px;font-size:14px;line-height:1.48;position:relative;overflow-wrap:anywhere;white-space:pre-wrap;width:fit-content;max-width:100%}
.ubx .bub.in{background:#fff;border:1px solid var(--bd);border-end-start-radius:5px}
.ubx .bub.out{background:var(--teal);color:#fff;border-end-end-radius:5px}
.ubx .bub.in + .bub.in{border-end-start-radius:15px;border-start-start-radius:5px}
.ubx .bub.out + .bub.out{border-end-end-radius:15px;border-start-end-radius:5px}
.ubx .bub .tm{font-size:10.5px;opacity:.6;margin-top:2px;display:flex;align-items:center;gap:4px;justify-content:flex-end;font-variant-numeric:tabular-nums;line-height:1.2}
.ubx .bub.in .tm{color:var(--mut-2);opacity:1}
.ubx .bub .undeliv{display:inline-flex;align-items:center;gap:4px;margin-inline-start:6px;font-size:11px;font-weight:700;color:#ffd8d8;vertical-align:baseline}
.ubx .bub.in .undeliv{color:#b4232a}
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
.ubx .crow .cbtn{width:30px;height:30px;border-radius:8px;display:grid;place-items:center;color:var(--mut);transition:.12s;flex-shrink:0}
.ubx .crow .cbtn:hover{background:var(--soft-2);color:var(--txt)}
.ubx .crow .cbtn.on{background:var(--teal-50);color:var(--teal)}
.ubx .crow .cbtn:disabled{opacity:.45;cursor:not-allowed}

/* pending attachment, sitting above the textarea until the message is sent */
.ubx .att{display:flex;align-items:center;gap:9px;margin:9px 9px 0;padding:8px 10px;border:1px solid var(--bd);border-radius:10px;background:var(--soft)}
.ubx .att .ic{width:30px;height:30px;border-radius:7px;background:var(--teal-50);color:var(--teal);display:grid;place-items:center;flex-shrink:0}
.ubx .att img{width:34px;height:34px;border-radius:7px;object-fit:cover;flex-shrink:0}
.ubx .att .m{flex:1;min-width:0}
.ubx .att .n{font-size:12.5px;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ubx .att .s{font-size:11px;color:var(--mut-2);margin-top:1px}
.ubx .att .x{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;color:var(--mut);flex-shrink:0;transition:.12s}
.ubx .att .x:hover{background:#fef2f2;color:#dc2626}

/* media inside a bubble */
.ubx .bub .media{display:block;margin:-2px 0 5px;border-radius:10px;overflow:hidden;max-width:260px}
.ubx .bub .media img{display:block;width:100%;height:auto;max-height:300px;object-fit:cover;cursor:zoom-in}
.ubx .bub .file{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:10px;margin:-1px 0 5px;background:rgba(255,255,255,.16);max-width:260px}
.ubx .bub.in .file{background:var(--soft);border:1px solid var(--bd)}
.ubx .bub .file .ic{width:30px;height:30px;border-radius:7px;display:grid;place-items:center;flex-shrink:0;background:rgba(255,255,255,.22)}
.ubx .bub.in .file .ic{background:var(--teal-50);color:var(--teal)}
.ubx .bub .file .n{font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.ubx .bub .file.off{opacity:.82;cursor:default}
.ubx .bub audio.media{width:250px;max-width:100%;height:38px}
.ubx .bub video.media{width:100%;max-height:280px;background:#000}

/* saved replies — anchored above the composer, per the Inbox design kit */
.ubx .cbox{position:relative}
.ubx .srp{position:absolute;bottom:calc(100% + 8px);inset-inline-start:0;width:min(380px,100%);background:#fff;border:1px solid var(--bd);border-radius:13px;box-shadow:0 22px 55px -22px rgba(13,20,23,.42);z-index:60;overflow:hidden;display:flex;flex-direction:column;max-height:330px}
.ubx .srp .hd{padding:10px 12px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:8px;flex-shrink:0}
.ubx .srp .hd input{flex:1;border:none;outline:none;font-size:13px;font-family:inherit;color:var(--txt);background:none;min-width:0}
.ubx .srp .hd input::placeholder{color:var(--mut-2)}
.ubx .srp .bd{overflow-y:auto;padding:5px;min-height:0}
.ubx .srp .it{display:block;width:100%;text-align:start;padding:8px 10px;border-radius:9px;transition:.11s}
.ubx .srp .it:hover,.ubx .srp .it:focus-visible{background:var(--teal-50);outline:none}
.ubx .srp .it .t{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:var(--txt)}
.ubx .srp .it .sc{font-size:10.5px;font-weight:700;color:var(--teal);background:var(--teal-50);border-radius:5px;padding:1px 5px;flex-shrink:0}
.ubx .srp .it .tag{font-size:10px;font-weight:700;color:var(--mut);background:var(--soft-2);border-radius:5px;padding:1px 5px;flex-shrink:0}
.ubx .srp .it .b{font-size:12px;color:var(--mut);margin-top:2px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.ubx .srp .empty{padding:20px 14px;text-align:center;font-size:12.5px;color:var(--mut-2)}
.ubx .srp .hd .ad{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;color:var(--teal);background:var(--teal-50);flex-shrink:0;transition:.11s}
.ubx .srp .hd .ad:hover{background:var(--teal-100)}
.ubx .srp .hd .ttl{flex:1;font-size:13px;font-weight:700;color:var(--txt);min-width:0}
.ubx .srp .ft{border-top:1px solid var(--bd);padding:7px 12px;flex-shrink:0}
.ubx .srp .ft a{font-size:12px;font-weight:600;color:var(--teal);display:inline-flex;align-items:center;gap:5px}
.ubx .srp .ft a:hover{text-decoration:underline}
/* create form — the agent saves the phrasing they just typed without leaving the thread */
.ubx .srp .fm{padding:10px 12px 12px;display:flex;flex-direction:column;gap:8px;overflow-y:auto;min-height:0}
.ubx .srp .fm label{font-size:11px;font-weight:700;color:var(--mut);letter-spacing:.01em}
.ubx .srp .fm input[type=text],.ubx .srp .fm textarea{width:100%;border:1px solid var(--bd);border-radius:9px;padding:7px 9px;font-size:12.5px;font-family:inherit;color:var(--txt);background:#fff;outline:none;transition:.11s}
.ubx .srp .fm input[type=text]:focus,.ubx .srp .fm textarea:focus{border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-50)}
.ubx .srp .fm textarea{resize:vertical;min-height:64px;line-height:1.45}
.ubx .srp .fm .pfx{display:flex;align-items:center;border:1px solid var(--bd);border-radius:9px;overflow:hidden;background:#fff}
.ubx .srp .fm .pfx span{padding:0 8px;font-size:12.5px;font-weight:700;color:var(--mut-2);background:var(--soft-2);align-self:stretch;display:grid;place-items:center}
.ubx .srp .fm .pfx input{border:none!important;border-radius:0;box-shadow:none!important}
.ubx .srp .fm .seg{display:flex;gap:6px}
.ubx .srp .fm .seg button{flex:1;border:1px solid var(--bd);border-radius:9px;padding:6px 8px;font-size:11.5px;font-weight:700;color:var(--mut);background:#fff;transition:.11s}
.ubx .srp .fm .seg button.on{border-color:var(--teal);background:var(--teal-50);color:var(--teal-d)}
.ubx .srp .fm .err{font-size:11.5px;font-weight:600;color:#dc2626}
.ubx .srp .fm .ac{display:flex;gap:7px;justify-content:flex-end;margin-top:2px}
.ubx .srp .fm .ac button{border-radius:9px;padding:7px 13px;font-size:12.5px;font-weight:700;transition:.11s}
.ubx .srp .fm .ac .g{border:1px solid var(--bd);color:var(--mut);background:#fff}
.ubx .srp .fm .ac .p{background:var(--teal);color:#fff}
.ubx .srp .fm .ac .p:disabled{opacity:.55}
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
.ubx .ihero .chip.fb{background:var(--fb-50);color:var(--fb)}
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

    // Saved replies follow the same entitlement as the sidebar link, and the
    // same scope rule as Api\SavedReplyController: only a workspace owner may
    // create a reply the whole team sees.
    $srUser        = auth()->user();
    $canUseReplies = $srUser->isSuperAdmin() || ($srUser->tenant?->planAllows('saved_replies') ?? false);
    $canSaveTeamReply = $srUser->isAdmin() || $srUser->isSupervisor() || $srUser->isSuperAdmin();
    $manageRepliesUrl = $canUseReplies && \Illuminate\Support\Facades\Route::has($panelPrefix . '.saved-replies.index')
        ? route($panelPrefix . '.saved-replies.index')
        : '';
    $jsLabels = [
        'tab_pending'     => __('ui.webchat_page.tab_pending'),
        'tab_mine'        => __('ui.webchat_page.tab_mine'),
        'tab_all'         => __('ui.webchat_page.tab_all'),
        'tab_closed'      => __('ui.webchat_page.tab_closed'),
        'status_pending'  => $i18n['status_pending'],
        'msg_undelivered' => $i18n['msg_undelivered'],
        'msg_failed'      => $i18n['msg_failed'],
        'status_assigned' => $i18n['status_assigned'],
        'status_closed'   => $i18n['status_closed'],
        'status_bot'      => $i18n['status_bot'],
        'status_ai'       => $i18n['status_ai'],
        'status_escalated'=> $i18n['status_escalated'],
        'escalated_hint'  => $i18n['escalated_hint'],
        'claim_error'     => $i18n['claim_error'],
        'send_error'      => $i18n['send_error'],
        'action_error'    => $i18n['action_error'],
        'reassign_error'  => $i18n['reassign_error'],
        'reassign_done'   => $i18n['reassign_done'],
        'just_now'        => __('ui.webchat_page.just_now'),
        // Authors for messages appended live, where the server-rendered `who`
        // of the thread payload is not available to fall back on.
        'who_ai'          => __('ui.inbox_page.ai'),
        'who_agent'       => __('ui.inbox_page.agent'),
        'attach'            => $i18n['attach'],
        'attach_error'      => $i18n['attach_error'],
        'attach_remove'     => $i18n['attach_remove'],
        'attach_uploading'  => $i18n['attach_uploading'],
        'attach_generic'    => $i18n['attach_generic'],
        'saved_replies'     => $i18n['saved_replies'],
        'sr_empty'          => $i18n['saved_replies_empty'],
        'sr_none'           => $i18n['saved_replies_none'],
        'sr_error'          => $i18n['saved_replies_error'],
        'sr_personal'       => $i18n['saved_replies_personal'],
        'sr_tenant'         => $i18n['saved_replies_tenant'],
        'sr_new'            => $i18n['saved_replies_new'],
        'sr_added'          => $i18n['saved_replies_added'],
        'sr_err_title'      => __('ui.saved_replies_page.error_title_required'),
        'sr_err_body'       => __('ui.saved_replies_page.error_body_required'),
        'sr_err_save'       => __('ui.saved_replies_page.save_error'),
        'cancel'            => __('ui.cancel'),
        'media_image'       => $i18n['media_image'],
        'media_audio'       => $i18n['media_audio'],
        'media_video'       => $i18n['media_video'],
        'media_document'    => $i18n['media_document'],
        'media_offsite'     => $i18n['media_offsite'],
    ];
@endphp

{{-- No x-init here: Alpine calls init() on the x-data object automatically.
     Adding x-init="init()" ran it twice, which started two poll timers and
     left the first one unreachable. --}}
<div x-data="unifiedInbox()" x-cloak class="ubx" :class="{ 'v-thread': mobileThread }">

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
                @if($canUseMessenger ?? false)
                    <button type="button" class="chan" :class="{ 'on': channel === 'messenger' }" @click="setChannel('messenger')"><span class="d fb"></span>Messenger</button>
                @endif
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
                        <span class="ch" :class="r.channel === 'whatsapp' ? 'wa' : (r.channel === 'messenger' ? 'fb' : 'lc')" x-html="channelGlyph(r.channel)"></span>
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
                            <template x-if="r.escalated">
                                <span class="st esc" style="display:inline-flex;align-items:center;gap:3px" :title="labels.escalated_hint">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 1.5 21h21L12 2zm1 14h-2v2h2v-2zm0-7h-2v5h2V9z"/></svg>
                                    <span x-text="labels.status_escalated"></span>
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
                        <span class="ch" :class="thread.channel === 'whatsapp' ? 'wa' : (thread.channel === 'messenger' ? 'fb' : 'lc')" x-html="channelGlyph(thread.channel)"></span>
                    </span>
                    <div class="m">
                        <div class="n" x-text="thread.header.name"></div>
                        <div class="s">
                            <span class="st" :class="thread.header.status" x-text="labels['status_' + thread.header.status] ?? thread.header.status"></span>
                            <template x-if="thread.header.escalated">
                                <span class="st esc" style="display:inline-flex;align-items:center;gap:3px" :title="labels.escalated_hint">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 1.5 21h21L12 2zm1 14h-2v2h2v-2zm0-7h-2v5h2V9z"/></svg>
                                    <span x-text="labels.status_escalated"></span>
                                </span>
                            </template>
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
                        <template x-if="thread.can.reassign && assignTpl">
                            <button type="button" class="btn g" @click="openReassign()" :disabled="busy">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M16 3.5a4 4 0 010 7.3M8 11a4 4 0 100-8 4 4 0 000 8zM2 20.5a6 6 0 0112 0M18 14.6a6 6 0 013.9 5.9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                                {{ $i18n['reassign'] }}
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
                                            {{-- Keep this element on one line: .bub is white-space:pre-wrap, so any
                                                 source indentation between these tags renders as literal spaces
                                                 inside the bubble. --}}
                                            <div class="bub" :class="g.side"><template x-if="m.media && m.media.inline && m.media.type === 'image'"><a class="media" :href="m.media.url" target="_blank" rel="noopener"><img :src="m.media.url" :alt="m.media.name || labels.media_image" loading="lazy"></a></template><template x-if="m.media && m.media.inline && m.media.type === 'audio'"><audio class="media" controls preload="none" :src="m.media.url"></audio></template><template x-if="m.media && m.media.inline && m.media.type === 'video'"><video class="media" controls preload="metadata" :src="m.media.url"></video></template><template x-if="m.media && m.media.inline && !['image','audio','video'].includes(m.media.type)"><a class="file" :href="m.media.url" target="_blank" rel="noopener"><span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M14 3v5h5M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span><span class="n" x-text="m.media.name || labels.media_document"></span></a></template><template x-if="m.media && !m.media.inline"><span class="file off" :title="labels.media_offsite"><span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M14 3v5h5M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span><span class="n" x-text="m.media.name || mediaLabel(m.media.type)"></span></span></template><span x-text="(m.body || '').trim()"></span><template x-if="isUndelivered(m, g)"><span class="undeliv" :title="m.status === 'failed' ? labels.msg_failed : labels.msg_undelivered"><i class="ri-error-warning-line"></i><span x-text="m.status === 'failed' ? labels.msg_failed : labels.msg_undelivered"></span></span></template><template x-if="mi === g.items.length - 1"><div class="tm" x-text="formatTime(m.created_at)"></div></template></div>
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
                        <div class="cbox" @keydown.escape="srCreating ? (srCreating = false) : (srOpen = false)">
                            {{-- Saved replies: click a row to drop its body into the composer.

                                 x-show, not x-if: with x-if the panel is created
                                 during the same click that opens it, so Alpine's
                                 freshly-registered .outside handler saw that very
                                 click as an outside click and shut it again — the
                                 panel appeared to do nothing at all. x-show keeps
                                 the element mounted, so .outside correctly ignores
                                 clicks while it is hidden, and x-ref="srSearch"
                                 exists in time to be focused. --}}
                            <div class="srp" x-show="srOpen" x-cloak @click.outside="srOpen = false">
                                    <div class="hd">
                                        <template x-if="!srCreating">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" style="color:var(--mut-2);flex-shrink:0"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </template>
                                        <input type="text" x-show="!srCreating" x-model="srQuery" x-ref="srSearch" placeholder="{{ $i18n['saved_replies_search'] }}">
                                        <span class="ttl" x-show="srCreating" x-text="labels.sr_new"></span>
                                        {{-- Add without leaving the thread: the draft in the composer seeds the body. --}}
                                        <button type="button" class="ad" @click="toggleCreateReply()"
                                                :title="srCreating ? labels.cancel : labels.sr_new">
                                            <template x-if="!srCreating">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                            </template>
                                            <template x-if="srCreating">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                                            </template>
                                        </button>
                                    </div>

                                    {{-- Create form --}}
                                    <div class="fm" x-show="srCreating">
                                        <div>
                                            <label for="srNewTitle">{{ __('ui.saved_replies_page.field_title') }}</label>
                                            <input id="srNewTitle" type="text" maxlength="120" x-model="srForm.title" x-ref="srTitle"
                                                   placeholder="{{ __('ui.saved_replies_page.field_title_placeholder') }}">
                                        </div>
                                        <div>
                                            <label for="srNewShortcut">{{ __('ui.saved_replies_page.field_shortcut') }}</label>
                                            <div class="pfx">
                                                <span>/</span>
                                                <input id="srNewShortcut" type="text" maxlength="31" x-model="srForm.shortcut"
                                                       placeholder="{{ __('ui.saved_replies_page.field_shortcut_placeholder') }}"
                                                       @input="srForm.shortcut = srForm.shortcut.replace(/[^a-z0-9_-]/g, '')">
                                            </div>
                                        </div>
                                        <div>
                                            <label for="srNewBody">{{ __('ui.saved_replies_page.field_body') }}</label>
                                            <textarea id="srNewBody" maxlength="4000" x-model="srForm.body"
                                                      placeholder="{{ __('ui.saved_replies_page.field_body_placeholder') }}"></textarea>
                                        </div>
                                        @if($canSaveTeamReply)
                                            <div>
                                                <label>{{ __('ui.saved_replies_page.field_scope') }}</label>
                                                <div class="seg" style="margin-top:5px">
                                                    <button type="button" :class="srForm.scope === 'tenant' ? 'on' : ''"
                                                            @click="srForm.scope = 'tenant'" x-text="labels.sr_tenant"></button>
                                                    <button type="button" :class="srForm.scope === 'personal' ? 'on' : ''"
                                                            @click="srForm.scope = 'personal'" x-text="labels.sr_personal"></button>
                                                </div>
                                            </div>
                                        @endif
                                        <div class="err" x-show="srFormError" x-text="srFormError"></div>
                                        <div class="ac">
                                            <button type="button" class="g" @click="srCreating = false" x-text="labels.cancel"></button>
                                            <button type="button" class="p" @click="createReply()" :disabled="srSaving">{{ __('ui.save') }}</button>
                                        </div>
                                    </div>

                                    <div class="bd" x-show="!srCreating">
                                        <template x-for="r in filteredReplies" :key="r.id">
                                            <button type="button" class="it" @click="useReply(r)">
                                                <span class="t">
                                                    <span x-text="r.title"></span>
                                                    {{-- Seeded shortcuts already carry the slash; don't double it. --}}
                                                    <template x-if="r.shortcut"><span class="sc" x-text="r.shortcut.startsWith('/') ? r.shortcut : '/' + r.shortcut"></span></template>
                                                    <span class="tag" x-text="r.scope === 'tenant' ? labels.sr_tenant : labels.sr_personal"></span>
                                                </span>
                                                <span class="b" x-text="r.body"></span>
                                            </button>
                                        </template>
                                        <template x-if="!filteredReplies.length">
                                            <div class="empty" x-text="srError ? labels.sr_error : (replies.length ? labels.sr_none : labels.sr_empty)"></div>
                                        </template>
                                    </div>
@if($manageRepliesUrl)
                                    <div class="ft" x-show="!srCreating">
                                        <a href="{{ $manageRepliesUrl }}">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 20h9M4 20h3l10-10-3-3L4 17v3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                                            {{ __('ui.inbox_page.saved_replies_manage') }}
                                        </a>
                                    </div>
@endif
                            </div>

                            {{-- Uploaded and waiting to go out with the next send. --}}
                            <template x-if="attachment">
                                <div class="att">
                                    <template x-if="attachment.type === 'image' && attachment.url">
                                        <img :src="attachment.url" alt="">
                                    </template>
                                    <template x-if="attachment.type !== 'image' || !attachment.url">
                                        <span class="ic">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M14 3v5h5M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                                        </span>
                                    </template>
                                    <div class="m">
                                        <div class="n" x-text="attachment.file_name || labels.attach_generic"></div>
                                        <div class="s" x-text="uploading ? labels.attach_uploading : humanSize(attachment.size)"></div>
                                    </div>
                                    <button type="button" class="x" @click="clearAttachment()" :title="labels.attach_remove">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                                    </button>
                                </div>
                            </template>

                            <textarea x-model="composer" @keydown.enter.exact.prevent="send()" rows="1"
                                      placeholder="{{ $i18n['composer_placeholder'] }}"></textarea>
                            <div class="crow">
                                <input type="file" x-ref="file" style="display:none" @change="pickFile($event)"
                                       accept=".jpeg,.jpg,.png,.gif,.webp,.mp4,.mov,.avi,.mp3,.ogg,.aac,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
                                <button type="button" class="cbtn" @click="$refs.file.click()"
                                        :disabled="uploading || sending" :title="labels.attach">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M21 11.5l-8.4 8.4a5 5 0 01-7-7l8.4-8.4a3.3 3.3 0 014.7 4.7l-8.4 8.4a1.7 1.7 0 01-2.3-2.3l7.7-7.7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                @if($canUseReplies)
                                <button type="button" class="cbtn" :class="srOpen ? 'on' : ''"
                                        @click.stop="toggleReplies()" :title="labels.saved_replies">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                                </button>
                                @endif
                                <span class="sp">↵ {{ $i18n['enter_to_send'] }}</span>
                                <button type="button" class="send" @click="send()"
                                        :disabled="(!composer.trim() && !attachment) || sending || uploading">
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
                <div class="chip" :class="thread?.channel === 'whatsapp' ? 'wa' : (thread?.channel === 'messenger' ? 'fb' : 'lc')">
                    <span x-text="thread?.channel === 'whatsapp' ? '{{ $i18n['whatsapp'] }}' : (thread?.channel === 'messenger' ? 'Messenger' : '{{ $i18n['live_chat'] }}')"></span>
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

    {{-- ═══════════ reassign modal ═══════════ --}}
    <template x-if="reassignModal">
        <div class="ubx-modal" @click.self="reassignModal = false">
            <div class="box">
                <h3>{{ $i18n['reassign_title'] }}</h3>
                <template x-if="thread?.header?.assignee">
                    <p style="font-size:12px;color:var(--mut);margin:0 0 10px">
                        {{ $i18n['reassign_current'] }}: <strong x-text="thread.header.assignee.name"></strong>
                    </p>
                </template>
                <template x-if="reassignLoading">
                    <p style="font-size:12px;color:var(--mut)">…</p>
                </template>
                <template x-if="!reassignLoading && reassignAgents.length === 0">
                    <p style="font-size:12px;color:var(--mut)">{{ $i18n['reassign_none'] }}</p>
                </template>
                <template x-if="!reassignLoading && reassignAgents.length > 0">
                    <div>
                        <label>{{ $i18n['reassign_to'] }}</label>
                        <select x-model="reassignAgentId" style="width:100%">
                            <template x-for="a in reassignAgents" :key="a.id">
                                <option :value="a.id" x-text="a.name"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <div class="acts">
                    <button type="button" class="btn g" @click="reassignModal = false">{{ __('ui.cancel') }}</button>
                    <button type="button" class="btn p" @click="doReassign()"
                            :disabled="busy || reassignLoading || !reassignAgentId">{{ $i18n['reassign_confirm'] }}</button>
                </div>
            </div>
        </div>
    </template>

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
        assignTpl:  @json(\Illuminate\Support\Facades\Route::has($panelPrefix . '.inbox.assignable')
            ? route($panelPrefix . '.inbox.assignable', ['channel' => '__CH__', 'ref' => '__REF__'])
            : ''),
        webchatTpl: @json($canUseWebChat ? route($panelPrefix . '.webchat.conversations.index') : ''),
        // Messenger routes are gated by `module:messenger` — route() would throw
        // if the module isn't in the plan and the URL isn't registered.
        // Guard with Route::has to keep the JSON safe on plans without it.
        messengerTpl: @json(($canUseMessenger ?? false) && \Illuminate\Support\Facades\Route::has($panelPrefix . '.messenger.index') ? route($panelPrefix . '.messenger.index') : ''),
        uploadUrl:  '/api/media/upload',
        repliesUrl: '/api/saved-replies',
        myId:       @json((int) auth()->id()),
        myName:     @json((string) auth()->user()->name),

        tabs: ['pending', 'mine', 'all', 'closed'],
        channel: 'all',
        // 'mine' when the agent already has claimed threads — see InboxController::index.
        tab: @json($initialTab ?? 'pending'),
        q: '',
        rows: [],
        counts: @json($initialCounts ?? []),
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
        reassignModal: false,
        reassignAgents: [],
        reassignAgentId: '',
        reassignLoading: false,
        wsConnected: false,
        attachment: null,
        uploading: false,
        srOpen: false,
        srQuery: '',
        srError: false,
        replies: [],
        _repliesLoaded: false,
        srCreating: false,
        srSaving: false,
        srFormError: '',
        srCanTeam: @json($canSaveTeamReply),
        srForm: { title: '', shortcut: '', body: '', scope: 'personal' },
        _timer: null,
        _chan: null,
        _tmp: 0,
        _booted: false,
        _listReq: null,
        _listSeq: 0,

        init() {
            // Idempotent: a second call must not start a second poll timer.
            // Two timers is exactly what produced the duplicated list requests.
            if (this._booted) return;
            this._booted = true;

            this.loadList();
            clearInterval(this._timer);
            this._timer = setInterval(() => this.loadList(true), 15000);

            if (window._echoStateListeners) window._echoStateListeners.push((c) => {
                this.wsConnected = c;
                // Echo finishes connecting after init() on a cold load, and a
                // dropped socket resubscribes on reconnect. Either way the thread
                // already on screen needs its subscription (re)attached.
                if (c && this.thread) this.watchThread();
            });
            this.wsConnected = !!window._echoConnected;
            window.addEventListener('beforeunload', () => this.teardown());
        },

        teardown() {
            clearInterval(this._timer);
            this._timer = null;
            clearTimeout(this._sendSettle);
            this._listReq?.abort();
            this.unwatchThread();
        },

        // ── list ──
        setChannel(c) { if (this.channel === c) return; this.channel = c; this.clearThread(); this.loadList(); },
        setTab(t)     { if (this.tab === t) return; this.tab = t; this.clearThread(); this.loadList(); },

        clearThread() { this.unwatchThread(); this.resetComposer(); this.activeKey = null; this.thread = null; this.mobileThread = false; this.infoOpen = false; },

        // A half-written reply or a staged file belongs to the thread it was
        // started in — carrying either into the next one sends it to the wrong
        // person.
        resetComposer() { this.composer = ''; this.attachment = null; this.uploading = false; this.srOpen = false; this.srQuery = ''; this.srCreating = false; },

        async loadList(silent = false) {
            // A new list load supersedes whatever is still in flight: switching
            // tabs quickly used to leave several racing requests, and whichever
            // landed last won regardless of which tab was actually on screen.
            this._listReq?.abort();
            const req = this._listReq = new AbortController();
            const seq = ++this._listSeq;

            if (!silent) this.loading = true;
            try {
                const url = new URL(this.listUrl, window.location.origin);
                url.searchParams.set('channel', this.channel);
                url.searchParams.set('tab', this.tab);
                if (this.q.trim()) url.searchParams.set('q', this.q.trim());
                const r = await fetch(url, {
                    signal: req.signal,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();

                // Drop a response the user has already navigated past.
                if (seq !== this._listSeq) return;

                this.rows = data.data || [];
                this.counts = data.counts || {};
            } catch (e) {
                // An abort is the expected outcome of switching tabs, not a fault.
                if (e.name === 'AbortError') return;
                console.error('[inbox] list failed', e);
            } finally {
                if (!silent && seq === this._listSeq) this.loading = false;
            }
        },

        // ── thread ──
        async openRow(row) {
            this.mobileThread = true;
            if (this.activeKey === row.key) return;
            this.activeKey = row.key;
            this.unwatchThread();
            this.thread = null;
            this.resetComposer();
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
            this.watchThread();
            if (this.thread.can.reply) this.markRead();
            await this.$nextTick();
            this.scrollBottom();
        },

        // Best effort — a failed read receipt shouldn't disturb the thread.
        markRead() {
            const url = this.actionUrl('read');
            return url ? this.post(url).catch(() => {}) : Promise.resolve();
        },

        // A freshly sent message sits at 'pending' for the second or two the
        // queue takes to hand it to WhatsApp. The thread does not live-refresh,
        // so flagging that window as "Not delivered" left the warning stuck on
        // messages the customer had already received. Only flag a send that has
        // genuinely stalled, or one the gateway rejected outright.
        isUndelivered(m, g) {
            if (g.side !== 'out') return false;
            if (m.status === 'failed') return true;
            if (m.status !== 'pending') return false;

            const age = Date.now() - new Date(m.created_at).getTime();
            return Number.isFinite(age) && age > 30000;
        },
        async reloadThread() {
            if (!this.thread) return;
            await this.loadThread(this.thread.channel, this.thread.ref);
        },

        // ── live thread ──
        // The list polls every 15s, but an open thread only ever refreshed on
        // open or after an action. So an inbound message updated the row in the
        // sidebar — unread dot, new preview — while the conversation on screen
        // stayed frozen, which read as "the message never arrived". Subscribe to
        // the open conversation's own channel and append as messages land.
        watchThread() {
            this.unwatchThread();
            const t = this.thread;
            if (!t || !window.Echo) return;

            // A super admin has no tenant_id, so channel authorization rejects
            // them on a tenant conversation; they stay on the poll.
            const name = t.channel === 'whatsapp'
                ? (t.tenant_id ? `tenant.${t.tenant_id}.conversation.${t.ref}` : null)
                : `webchat.conversation.${t.ref}`;
            if (!name) return;

            this._chan = name;
            const ch = window.Echo.private(name);
            if (t.channel === 'whatsapp') {
                ch.listen('.message.received', (e) => this.absorbWhatsApp(e))
                  .listen('.message.sent',     (e) => this.absorbWhatsApp(e));
            } else {
                ch.listen('.webchat.message.sent', (e) => this.absorbWebChat(e));
            }
        },

        unwatchThread() {
            if (this._chan) window.Echo?.leave?.(this._chan);
            this._chan = null;
        },

        absorbWhatsApp(e) {
            const m = e?.message;
            if (!m || this.thread?.channel !== 'whatsapp') return;
            if (String(m.conversation_id) !== String(this.thread.ref)) return;

            this.absorb({
                id:         'wa-' + m.id,
                kind:       'text',
                side:       m.direction === 'in' ? 'in' : 'out',
                who:        m.direction === 'in'
                                ? this.thread.header.name
                                : (m.author_type === 'agent'
                                    ? (this.thread.header.assignee?.name ?? this.labels.who_agent)
                                    : this.labels.who_ai),
                body:       m.body,
                created_at: m.sent_at || m.created_at,
                status:     m.status,
            });
        },

        absorbWebChat(e) {
            const m = e?.message;
            if (!m || this.thread?.channel !== 'webchat') return;
            if (m.conversation_uuid && m.conversation_uuid !== this.thread.ref) return;

            this.absorb({
                id:         'lc-' + m.id,
                kind:       m.sender_type === 'system' ? 'system' : 'text',
                side:       m.sender_type === 'visitor' ? 'in' : 'out',
                who:        m.sender_type === 'visitor'
                                ? this.thread.header.name
                                : (m.sender_type === 'bot'
                                    ? this.labels.who_ai
                                    : (this.thread.header.assignee?.name ?? this.labels.who_agent)),
                body:       m.body,
                created_at: m.created_at,
                status:     'sent',
                media:      m.attachment ? {
                    url:    m.attachment.url,
                    type:   m.attachment.type,
                    name:   m.attachment.name,
                    inline: true,
                } : null,
            });
        },

        // Both ends can deliver the same message twice — the web-chat event
        // reaches the sender's own socket, and a queued WhatsApp send broadcasts
        // again once it leaves the gateway — so the id decides, not arrival. A
        // second delivery is a status update, not a duplicate bubble: merge it,
        // which is what settles an optimistic bubble from pending to sent.
        absorb(msg) {
            const list = this.thread.messages;
            const at = list.findIndex((x) => x.id === msg.id);
            if (at !== -1) { list[at] = { ...list[at], ...msg }; return; }

            // Only follow the bottom if the agent is already reading there.
            // Yanking the viewport while they scroll back through history is
            // worse than a message landing below the fold.
            const follow = this.isAtBottom();
            list.push(msg);
            this.$nextTick(() => { if (follow) this.scrollBottom(); });

            if (msg.side !== 'in') return;
            // The thread is open in front of the agent, so its row must not go on
            // claiming the message is unread.
            const read = this.thread.can?.reply ? this.markRead() : Promise.resolve();
            read.then(() => this.loadList(true));
        },

        isAtBottom() {
            const el = this.$refs.thread;
            if (!el) return true;
            return el.scrollHeight - el.scrollTop - el.clientHeight < 60;
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
            if (t.channel === 'whatsapp')  return `/api/conversations/${t.ref}/${what}`;
            if (t.channel === 'messenger') {
                // Messenger's send endpoint is /reply (not /messages) — the
                // route was named that way to make the "auto-claim on send"
                // behaviour obvious. `messages` normalises to `reply` here.
                const tail = what === 'messages' ? 'reply' : what;
                return this.messengerTpl + '/conversations/' + encodeURIComponent(t.ref) + '/' + tail;
            }
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

        // The picker is fetched per thread rather than cached: team membership
        // and the current assignee both change what is eligible.
        async openReassign() {
            const t = this.thread;
            if (!t || !this.assignTpl) return;
            this.reassignAgents  = [];
            this.reassignAgentId = '';
            this.reassignLoading = true;
            this.reassignModal   = true;
            try {
                const url = this.assignTpl.replace('__CH__', t.channel).replace('__REF__', encodeURIComponent(t.ref));
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                this.reassignAgents  = d.agents ?? [];
                this.reassignAgentId = this.reassignAgents[0]?.id ?? '';
            } catch (e) {
                console.error('[inbox] load assignable failed', e);
                window.showToast?.('error', this.labels.reassign_error);
                this.reassignModal = false;
            } finally { this.reassignLoading = false; }
        },

        async doReassign() {
            if (this.busy || !this.reassignAgentId) return;
            this.busy = true;
            try {
                const r = await this.post(this.actionUrl('reassign'), { agent_id: this.reassignAgentId });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.reassignModal = false;
                await this.reloadThread();
                await this.loadList(true);
                window.showToast?.('success', this.labels.reassign_done);
            } catch (e) {
                console.error('[inbox] reassign failed', e);
                window.showToast?.('error', this.labels.reassign_error);
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

        // The reply used to appear only after the POST and a full thread refetch
        // had both come back, so it lagged a beat behind the keystroke. Paint the
        // bubble first and reconcile after: the server's id replaces the local
        // one, so the echo from the broadcast merges instead of duplicating.
        async send() {
            const body = this.composer.trim();
            const file = this.attachment;
            if ((!body && !file) || this.sending || this.uploading || !this.thread?.can.reply) return;

            // Pin the thread: the agent can switch conversations while the POST
            // is in flight, and the reconcile must not land on the new one.
            const t = this.thread;
            const tempId = 'tmp-' + (++this._tmp);
            this.absorb({
                id:         tempId,
                kind:       'text',
                side:       'out',
                who:        this.thread.header.assignee?.name ?? this.myName,
                body:       body,
                created_at: new Date().toISOString(),
                status:     'pending',
                media:      file ? { url: file.url, type: file.type, name: file.file_name, mime: file.mime_type } : null,
            });
            this.composer = '';
            this.attachment = null;
            this.srOpen = false;
            this.sending = true;

            const payload = file
                ? { body, media_url: file.url, type: file.type, file_name: file.file_name, media_path: file.media_path }
                : { body };

            try {
                const r = await this.post(this.actionUrl('messages'), payload);
                if (!r.ok) throw new Error('HTTP ' + r.status);

                // Re-key the optimistic bubble to the stored id, so the reload and
                // the broadcast both land on it instead of adding a second copy.
                const saved = await r.json().catch(() => null);
                const realId = t.channel === 'whatsapp'
                    ? (saved?.id ? 'wa-' + saved.id : null)
                    : (saved?.message?.id ? 'lc-' + saved.message.id : null);
                const at = t.messages.findIndex((m) => m.id === tempId);
                if (at !== -1 && realId) t.messages[at].id = realId;

                this.loadList(true);

                // The send is queued, so it is still 'pending' above. Settle the
                // delivery state once the job has had time to run.
                if (this.thread === t) {
                    clearTimeout(this._sendSettle);
                    this._sendSettle = setTimeout(() => this.reloadThread().catch(() => {}), 4000);
                }
            } catch (e) {
                console.error('[inbox] send failed', e);
                // Drop the bubble and hand the text back rather than leaving a
                // message on screen that never left the building.
                const at = t.messages.findIndex((m) => m.id === tempId);
                if (at !== -1) t.messages.splice(at, 1);
                if (this.thread === t) {
                    if (!this.composer.trim()) this.composer = body;
                    if (file) this.attachment = file;
                }
                window.showToast?.('error', this.labels.send_error);
            } finally { this.sending = false; }
        },

        // ── attachments ──
        // Upload on pick so the file is already stored by the time the agent hits
        // send; the reply endpoint only wants the resulting url.
        async pickFile(ev) {
            const f = ev.target.files?.[0];
            ev.target.value = '';
            if (!f) return;

            this.uploading = true;
            this.attachment = { file_name: f.name, size: f.size, type: f.type.startsWith('image/') ? 'image' : 'document', url: null };
            try {
                const fd = new FormData();
                fd.append('file', f);
                const r = await fetch(this.uploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: fd,
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                this.attachment = { ...d, size: f.size };
            } catch (e) {
                console.error('[inbox] upload failed', e);
                this.attachment = null;
                window.showToast?.('error', this.labels.attach_error);
            } finally { this.uploading = false; }
        },

        clearAttachment() { this.attachment = null; this.uploading = false; },

        // Name for media we can't render — the type is all we know about it.
        mediaLabel(type) {
            return this.labels['media_' + type] || this.labels.media_document;
        },

        humanSize(bytes) {
            if (!Number.isFinite(bytes)) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        // ── saved replies ──
        async toggleReplies() {
            this.srOpen = !this.srOpen;
            if (!this.srOpen) { this.srCreating = false; return; }
            this.$nextTick(() => this.$refs.srSearch?.focus());
            if (this._repliesLoaded) return;
            this._repliesLoaded = true;
            try {
                const r = await fetch(this.repliesUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.replies = (await r.json()).data || [];
                this.srError = false;
            } catch (e) {
                console.error('[inbox] saved replies failed', e);
                // Let the next open retry — a blip shouldn't disable the panel.
                this._repliesLoaded = false;
                this.srError = true;
            }
        },

        get filteredReplies() {
            const q = this.srQuery.trim().toLowerCase();
            if (!q) return this.replies;
            return this.replies.filter((r) =>
                (r.title || '').toLowerCase().includes(q)
                || (r.shortcut || '').toLowerCase().includes(q)
                || (r.body || '').toLowerCase().includes(q));
        },

        // Opening the form seeds the body with whatever is already in the
        // composer: the common case is "I just wrote this, keep it".
        toggleCreateReply() {
            this.srCreating = !this.srCreating;
            if (!this.srCreating) return;
            this.srFormError = '';
            this.srForm = {
                title: '',
                shortcut: '',
                body: this.composer.trim(),
                scope: this.srCanTeam ? 'tenant' : 'personal',
            };
            this.$nextTick(() => this.$refs.srTitle?.focus());
        },

        async createReply() {
            if (this.srSaving) return;
            const title = this.srForm.title.trim();
            const body  = this.srForm.body.trim();
            if (!title) { this.srFormError = this.labels.sr_err_title; return; }
            if (!body)  { this.srFormError = this.labels.sr_err_body;  return; }

            this.srSaving = true;
            this.srFormError = '';
            const shortcut = this.srForm.shortcut.trim().replace(/^\//, '');

            try {
                const r = await this.post(this.repliesUrl, {
                    title,
                    body,
                    shortcut: shortcut ? '/' + shortcut : null,
                    // A non-privileged user asking for 'tenant' is downgraded
                    // server-side, so the list stays truthful either way.
                    scope: this.srCanTeam ? this.srForm.scope : 'personal',
                });
                const data = await r.json().catch(() => null);
                if (!r.ok) {
                    const first = Object.values(data?.errors || {})[0];
                    this.srFormError = (Array.isArray(first) ? first[0] : first) || data?.message || this.labels.sr_err_save;
                    return;
                }
                const saved = data?.data ?? data;
                if (saved?.id) this.replies = [saved, ...this.replies];
                this._repliesLoaded = true;
                this.srError = false;
                this.srCreating = false;
                window.showToast?.('success', this.labels.sr_added);
            } catch (e) {
                console.error('[inbox] saved reply create failed', e);
                this.srFormError = this.labels.sr_err_save;
            } finally {
                this.srSaving = false;
            }
        },

        // Append rather than overwrite — the agent has often already typed a
        // greeting before reaching for the canned part.
        useReply(r) {
            const cur = this.composer.trim();
            this.composer = cur ? cur + '\n' + r.body : r.body;
            this.srOpen = false;
            this.srQuery = '';
        },

        // ── ui helpers ──
        backToList() { this.mobileThread = false; this.infoOpen = false; },

        channelGlyph(channel) {
            if (channel === 'whatsapp') {
                return '<svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.15l-.3-.18-3.1.81.83-3.02-.2-.31A8.2 8.2 0 1112 20.2z"/><path d="M17.5 14.4c-.3-.15-1.75-.86-2-.96-.28-.1-.48-.15-.68.15s-.78.96-.95 1.16c-.18.2-.35.22-.65.07a8.2 8.2 0 01-2.4-1.48 9 9 0 01-1.67-2.07c-.17-.3 0-.46.13-.61.14-.14.3-.35.45-.53.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.68-1.6-.93-2.2-.24-.58-.49-.5-.67-.51h-.58c-.2 0-.53.07-.8.38-.28.3-1.05 1.02-1.05 2.5s1.07 2.9 1.22 3.1c.15.2 2.1 3.2 5.1 4.5.71.3 1.27.48 1.7.62.72.23 1.37.2 1.89.12.57-.09 1.75-.72 2-1.4.25-.7.25-1.28.17-1.4-.07-.13-.27-.2-.57-.35z"/></svg>';
            }
            if (channel === 'messenger') {
                return '<svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><path d="M12 2C6.5 2 2 6.2 2 11.4c0 2.9 1.4 5.5 3.7 7.3v3.3l3.4-1.9c.9.3 1.9.4 2.9.4 5.5 0 10-4.2 10-9.4S17.5 2 12 2zm1 12.6l-2.6-2.7-5 2.7 5.5-5.7 2.6 2.6 5-2.6-5.5 5.7z"/></svg>';
            }
            // webchat
            return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="4" width="19" height="13" rx="2.5" fill="#fff"/><path d="M8 20l3-3h2l-5 3z" fill="#fff"/><path d="M7 9h10M7 12.5h6" stroke="#4f6bed" stroke-width="1.8" stroke-linecap="round"/></svg>';
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
