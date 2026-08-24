/*!
 * Wavadesk / TshlBot Web Live-Chat widget — Support Widget Spec revision.
 * Vanilla JS, IIFE, no framework, no build step.
 * Ships as a committed static asset; git pull deploys it as-is.
 *
 * Embed:
 *   <script>window.WavadeskChat = { key: "wck_..." };</script>
 *   <script src="https://your-app-domain/webchat/widget.js" async></script>
 *
 * Backwards-compatible: `window.TshlBotChat` is also accepted.
 */
(function () {
    'use strict';

    // ── Config guard ──────────────────────────────────────────────────
    var CONFIG = window.WavadeskChat || window.TshlBotChat || {};
    if (!CONFIG.key || typeof CONFIG.key !== 'string' || CONFIG.key.indexOf('wck_') !== 0) {
        console.warn('WavadeskChat: missing or invalid window.WavadeskChat.key');
        return;
    }
    if (window.__wvchLoaded) return;      // prevent double-boot if snippet is pasted twice
    window.__wvchLoaded = true;

    // Mirror to both globals so debug hooks work under either name
    window.WavadeskChat = window.WavadeskChat || CONFIG;
    window.TshlBotChat  = window.TshlBotChat  || CONFIG;

    // ── Derive API base from own script src (works cross-origin) ──────
    var API_BASE = (function () {
        var found = document.querySelector('script[src*="/webchat/widget.js"]');
        var src = found ? found.src : '';
        return src.replace(/\/webchat\/widget\.js(\?.*)?$/, '');
    })();

    // ── localStorage helpers (keyed per widget key so multiple sites work) ─
    var LS_TOKEN = 'wvch:v1:token:' + CONFIG.key;
    var LS_CONV  = 'wvch:v1:conv:'  + CONFIG.key;
    var LS_OPEN  = 'wvch:v1:open:'  + CONFIG.key;
    var LS_LANG  = 'wvch:v1:lang:'  + CONFIG.key;
    var LS_TIP   = 'wvch:v1:tip:'   + CONFIG.key;
    var LS_HUMAN = 'wvch:v1:human:' + CONFIG.key;
    var LS_SOUND = 'wvch:v1:sound:' + CONFIG.key;
    function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function lsSet(k, v) { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} }

    // ── Language auto-detect (AR default, EN toggle) ──────────────────
    function detectLang() {
        var stored = lsGet(LS_LANG);
        if (stored === 'ar' || stored === 'en') return stored;
        if (CONFIG.defaultLang === 'ar' || CONFIG.defaultLang === 'en') return CONFIG.defaultLang;
        var htmlLang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
        var htmlDir  = (document.documentElement.getAttribute('dir')  || '').toLowerCase();
        if (htmlLang.indexOf('ar') === 0 || htmlDir === 'rtl') return 'ar';
        if (htmlLang.indexOf('en') === 0) return 'en';
        return 'ar'; // spec default
    }

    // ── Text bundles ──────────────────────────────────────────────────
    var I18N = {
        ar: {
            dir: 'rtl',
            floatingHint:   'تحتاج مساعدة؟',
            openAria:       'افتح محادثة الدعم',
            closeAria:      'إغلاق المحادثة',
            backAria:       'العودة إلى المواضيع',
            endChatAria:    'إنهاء المحادثة',
            endChatConfirm: 'إنهاء المحادثة الحالية؟',
            resumeChat:     'استكمال المحادثة الجارية',
            settingsAria:   'الإعدادات',
            soundLabel:     'تنبيهات صوتية',
            endChatMenu:    'إنهاء المحادثة',
            langToggle:     'EN',
            langToggleAria: 'التبديل إلى الإنجليزية',
            headerTitle:    null, // fallback to widget.name, else "الدعم الفني"
            headerFallback: 'الدعم الفني',
            headerPromise:  'نرد خلال دقيقة',
            statusOnline:   'متصل الآن',
            welcomeTitle:   'أهلاً بك 👋',
            welcomeSub:     'اختر ما تحتاجه لنبدأ فورًا',
            topics: [
                { id: 't-order',   label: 'أين طلبي؟',        tint: 'blue'   },
                { id: 't-return',  label: 'استرجاع أو استبدال', tint: 'orange' },
                { id: 't-payment', label: 'مشكلة في الدفع',   tint: 'green'  },
                { id: 't-agent',   label: 'التحدث مع موظف',   tint: 'purple' }
            ],
            composerPlaceholder: 'اكتب رسالتك…',
            sendAria:       'إرسال',
            connecting:     'جارٍ توصيلك بموظف الدعم…',
            handoffRoleFallback: 'أخصائي دعم العملاء',
            handoffPrefix:  'أنت الآن مع',
            closedTitle:    'انتهت المحادثة',
            closedSub:      'شكرًا للتواصل معنا. يمكنك بدء محادثة جديدة في أي وقت.',
            startNew:       'بدء محادثة جديدة',
            offlineTitle:   'نحن خارج الخدمة حاليًا',
            prechatTitle:   'قبل أن نبدأ',
            prechatSub:     'اترك بياناتك لنرد عليك.',
            prechatName:    'اسمك (اختياري)',
            prechatEmail:   'بريدك (اختياري)',
            prechatStart:   'بدء المحادثة',
            prechatStarting:'جارٍ البدء…',
            couldNotConnect:'تعذّر الاتصال. حاول مرة أخرى.',
            failedSend:     'تعذّر الإرسال',
            branding:       'مدعوم من'
        },
        en: {
            dir: 'ltr',
            floatingHint:   'Need help?',
            openAria:       'Open support chat',
            closeAria:      'Close chat',
            backAria:       'Back to topics',
            endChatAria:    'End chat',
            endChatConfirm: 'End this chat?',
            resumeChat:     'Resume your ongoing chat',
            settingsAria:   'Settings',
            soundLabel:     'Sound notifications',
            endChatMenu:    'End chat',
            langToggle:     'ع',
            langToggleAria: 'Switch to Arabic',
            headerTitle:    null,
            headerFallback: 'Customer Support',
            headerPromise:  'We reply within a minute',
            statusOnline:   'Online now',
            welcomeTitle:   'Hi there 👋',
            welcomeSub:     'Pick a topic to get started.',
            topics: [
                { id: 't-order',   label: 'Where’s my order?',    tint: 'blue'   },
                { id: 't-return',  label: 'Return or exchange',        tint: 'orange' },
                { id: 't-payment', label: 'Payment issue',             tint: 'green'  },
                { id: 't-agent',   label: 'Talk to an agent',          tint: 'purple' }
            ],
            composerPlaceholder: 'Type your message…',
            sendAria:       'Send',
            connecting:     'Connecting you to a support specialist…',
            handoffRoleFallback: 'Customer support specialist',
            handoffPrefix:  'You’re now with',
            closedTitle:    'Chat ended',
            closedSub:      'Thanks for reaching out. Feel free to start a new chat any time.',
            startNew:       'Start a new chat',
            offlineTitle:   'We’re offline right now',
            prechatTitle:   'Before we start',
            prechatSub:     'Leave your details so we can get back to you.',
            prechatName:    'Your name (optional)',
            prechatEmail:   'Your email (optional)',
            prechatStart:   'Start chat',
            prechatStarting:'Starting…',
            couldNotConnect:'Could not connect. Please try again.',
            failedSend:     'Failed to send',
            branding:       'Powered by'
        }
    };
    function t() { return I18N[S.lang] || I18N.ar; }

    // ── State ─────────────────────────────────────────────────────────
    var S = {
        lang:          detectLang(),
        open:          lsGet(LS_OPEN) === '1',
        booted:        false,
        booting:       false,
        view:          'welcome',           // welcome | prechat | chat | closed | offline
        visitorToken:  lsGet(LS_TOKEN),
        convUuid:      lsGet(LS_CONV),
        widget:        null,
        reverb:        null,
        runtime:       null,
        status:        null,                // bot | pending | assigned | closed
        agent:         null,
        messages:      [],
        seenIds:       {},                  // dedup for typewriter effect
        lastMessageId: 0,
        sending:       false,
        typing:        false,               // "bot is typing…" bubble visible
        typingSince:   0,
        wsConnected:   false,
        pusher:        null,
        pollTimer:     null,
        hintShown:     lsGet(LS_TIP) === '1',
        humanOnce:     lsGet(LS_HUMAN) === '1', // avatar stays green after first handoff
        soundEnabled:  lsGet(LS_SOUND) !== '0', // default on, opt-out via settings menu
        settingsOpen:  false
    };

    // ── HTTP helper ───────────────────────────────────────────────────
    function api(path, opts) {
        opts = opts || {};
        var url = API_BASE + '/api/webchat/' + encodeURIComponent(CONFIG.key) + path;
        var headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
        if (opts.auth !== false && S.visitorToken) {
            headers['Authorization'] = 'Bearer ' + S.visitorToken;
        }
        return fetch(url, {
            method: opts.method || 'GET',
            headers: headers,
            body: opts.body ? JSON.stringify(opts.body) : undefined,
            credentials: 'omit'
        }).then(function (r) {
            if (r.status === 204) return null;
            var isJson = (r.headers.get('content-type') || '').indexOf('application/json') !== -1;
            return (isJson ? r.json() : r.text()).then(function (payload) {
                if (!r.ok) {
                    var err = new Error('HTTP ' + r.status);
                    err.status = r.status; err.body = payload; throw err;
                }
                return payload;
            });
        });
    }

    // ── Session lifecycle ─────────────────────────────────────────────
    function ensureSession() {
        if (S.booted) return Promise.resolve();
        if (S.booting) return S.bootingPromise;
        S.booting = true;
        var body = {
            visitor_token: S.visitorToken || undefined,
            page_url: location.href,
            referrer: document.referrer || null
        };
        S.bootingPromise = api('/session', { method: 'POST', body: body, auth: false })
            .then(function (data) {
                S.visitorToken = data.visitor_token;
                S.widget  = data.widget  || {};
                S.reverb  = data.reverb  || null;
                S.runtime = data.runtime || null;
                lsSet(LS_TOKEN, S.visitorToken);
                if (data.active_conversation) {
                    S.convUuid = data.active_conversation.uuid;
                    S.status   = data.active_conversation.status;
                    lsSet(LS_CONV, S.convUuid);
                }
                S.booted  = true;
                S.booting = false;
                applyThemeFromWidget();
                if (S.convUuid && S.status !== 'closed' && S.status !== 'bot') {
                    return loadMessages({ initial: true }).then(function () {
                        S.view = 'chat';
                        subscribeRealtime();
                        startPolling();
                    });
                } else if (S.convUuid && S.status === 'closed') {
                    S.view = 'closed';
                } else if (S.widget.pre_chat_ask_email) {
                    S.view = 'prechat';
                } else {
                    S.view = 'welcome';
                }
            })
            .catch(function (e) {
                S.booting = false;
                console.error('WavadeskChat: session failed', e);
                throw e;
            });
        return S.bootingPromise;
    }

    function submitPrechat(name, email) {
        return api('/session', {
            method: 'POST',
            body: {
                visitor_token: S.visitorToken,
                name: name || null,
                email: email || null,
                page_url: location.href
            },
            auth: false
        }).then(function () {
            S.view = 'welcome';
            render();
        });
    }

    function openConversation() {
        if (S.convUuid) return Promise.resolve(S.convUuid);
        return api('/conversations', {
            method: 'POST',
            body: { page_url: location.href, referrer: document.referrer || null }
        }).then(function (data) {
            S.convUuid = data.uuid;
            S.status   = data.status || 'bot';
            lsSet(LS_CONV, S.convUuid);
            return S.convUuid;
        });
    }

    function requestAgent() {
        return openConversation()
            .then(function (uuid) {
                return api('/conversations/' + uuid + '/request-agent', { method: 'POST', body: {} });
            })
            .then(function (data) {
                S.status = data.status || 'pending';
                if (S.view !== 'chat') { switchToChat(); } else { renderStatus(); renderComposer(); }
                subscribeRealtime();
                startPolling();
            });
    }

    function sendMessage(body) {
        var text = (body || '').trim();
        if (!text || S.sending) return Promise.resolve();
        S.sending = true;

        // Local echo — the spec's bubble rise + colored shadow applies here.
        var localId = 'local-' + Date.now();
        var echo = {
            id: localId, sender_type: 'visitor', body: text,
            created_at: new Date().toISOString(), pending: true, _rendered: false
        };
        S.messages.push(echo);
        S.seenIds[localId] = true;

        if (S.view !== 'chat') switchToChat();
        else appendMessage(echo);

        showTyping();

        return openConversation()
            .then(function (uuid) {
                return api('/conversations/' + uuid + '/messages', { method: 'POST', body: { body: text } });
            })
            .then(function (data) {
                for (var i = 0; i < S.messages.length; i++) {
                    if (S.messages[i].id === localId) {
                        var replaced = {
                            id: data.message.id,
                            sender_type: 'visitor',
                            body: data.message.body,
                            created_at: data.message.created_at,
                            _rendered: true
                        };
                        S.messages[i] = replaced;
                        delete S.seenIds[localId];
                        S.seenIds[replaced.id] = true;
                        break;
                    }
                }
                if (data.message.id > S.lastMessageId) S.lastMessageId = data.message.id;
                if (S.status === 'bot') { S.status = 'pending'; renderStatus(); }
                if (!S.pusher) { subscribeRealtime(); startPolling(); }
            })
            .catch(function () {
                for (var j = 0; j < S.messages.length; j++) {
                    if (S.messages[j].id === localId) {
                        S.messages[j].failed = true;
                        var node = el.thread && el.thread.querySelector('[data-mid="' + localId + '"]');
                        if (node) node.classList.add('wvch-bubble-failed');
                        break;
                    }
                }
            })
            .then(function () {
                S.sending = false;
                renderComposer();
            });
    }

    // ── Message polling (fallback for no-WS) ──────────────────────────
    function loadMessages(opts) {
        opts = opts || {};
        if (!S.convUuid) return Promise.resolve();
        return api('/conversations/' + S.convUuid + '/messages?after=' + S.lastMessageId)
            .then(function (data) {
                (data.messages || []).forEach(function (m) {
                    if (S.seenIds[m.id]) return;
                    if (m.id <= S.lastMessageId && !opts.initial) return;
                    S.messages.push(m);
                    S.seenIds[m.id] = true;
                    if (m.id > S.lastMessageId) S.lastMessageId = m.id;
                    if (S.view === 'chat' && !opts.initial) {
                        hideTyping();
                        appendMessage(m);
                    }
                    if (!opts.initial) maybePlayForIncoming(m);
                });
                if (data.conversation && data.conversation.status !== S.status) {
                    S.status = data.conversation.status;
                    if (S.status === 'closed') { S.view = 'closed'; render(); return; }
                    renderStatus(); renderComposer();
                }
            })
            .catch(function () { /* silent */ });
    }
    function startPolling() {
        stopPolling();
        var interval = (S.runtime && S.runtime.poll_interval_ms) || 4000;
        S.pollTimer = setInterval(loadMessages, interval);
    }
    function stopPolling() {
        if (S.pollTimer) { clearInterval(S.pollTimer); S.pollTimer = null; }
    }

    // ── Realtime via Pusher-protocol client (Reverb-compatible) ───────
    function subscribeRealtime() {
        if (S.pusher || !S.convUuid) return;
        if (!S.reverb || !S.reverb.key || !S.reverb.host) return;

        loadPusher(function () {
            try {
                var p = new Pusher(S.reverb.key, {
                    wsHost: S.reverb.host,
                    wsPort: S.reverb.port,
                    wssPort: S.reverb.port,
                    forceTLS: S.reverb.scheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                    cluster: 'mt1',
                    authEndpoint: API_BASE + '/api/webchat/broadcasting/auth',
                    auth: { headers: { 'Authorization': 'Bearer ' + S.visitorToken } }
                });
                S.pusher = p;
                p.connection.bind('state_change', function (states) {
                    S.wsConnected = states.current === 'connected';
                    renderStatus();
                });
                p.connection.bind('error', function () { S.wsConnected = false; renderStatus(); });

                var ch = p.subscribe('private-webchat.conversation.' + S.convUuid);
                ch.bind('webchat.message.sent', function (payload) {
                    var m = payload.message;
                    if (!m || m.conversation_uuid !== S.convUuid) return;
                    if (S.seenIds[m.id]) return;
                    S.messages.push({
                        id: m.id, sender_type: m.sender_type, sender_id: m.sender_id,
                        body: m.body, created_at: m.created_at, sender: m.sender || null
                    });
                    S.seenIds[m.id] = true;
                    if (m.id > S.lastMessageId) S.lastMessageId = m.id;
                    if (S.view === 'chat') {
                        hideTyping();
                        appendMessage(S.messages[S.messages.length - 1]);
                    }
                    maybePlayForIncoming(m);
                });
                ch.bind('webchat.conversation.claimed', function (payload) {
                    S.status = 'assigned';
                    S.agent  = payload.agent || null;
                    if (!S.humanOnce) { S.humanOnce = true; lsSet(LS_HUMAN, '1'); }
                    renderStatus();
                });
                ch.bind('webchat.conversation.closed', function () {
                    S.status = 'closed'; S.view = 'closed'; render();
                });
            } catch (e) {
                console.warn('WavadeskChat: realtime subscribe failed', e);
            }
        });
    }
    var pusherLoading = false, pusherReady = false, pusherCbs = [];
    function loadPusher(cb) {
        if (pusherReady || window.Pusher) { pusherReady = true; cb(); return; }
        pusherCbs.push(cb);
        if (pusherLoading) return;
        pusherLoading = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js';
        s.async = true;
        s.onload = function () {
            pusherReady = true;
            pusherCbs.forEach(function (fn) { try { fn(); } catch (e) {} });
            pusherCbs = [];
        };
        s.onerror = function () {
            console.warn('WavadeskChat: Pusher CDN failed, polling only');
            pusherCbs = [];
        };
        document.head.appendChild(s);
    }

    // ── Notification sound (Web Audio, no asset) ──────────────────────
    var audioCtx = null;
    function ensureAudioCtx() {
        if (audioCtx) return audioCtx;
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        try { audioCtx = new AC(); } catch (e) { audioCtx = null; }
        return audioCtx;
    }
    function playNotificationSound() {
        if (!S.soundEnabled) return;
        var ctx = ensureAudioCtx();
        if (!ctx) return;
        try {
            // Two-note soft chime — G5 then C6. Quick fade to avoid clipping.
            [
                { freq: 784.0, start: 0.00, dur: 0.18 },
                { freq: 1046.5, start: 0.10, dur: 0.24 }
            ].forEach(function (n) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = n.freq;
                var t0 = ctx.currentTime + n.start;
                gain.gain.setValueAtTime(0.0001, t0);
                gain.gain.exponentialRampToValueAtTime(0.18, t0 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t0 + n.dur);
                osc.connect(gain).connect(ctx.destination);
                osc.start(t0);
                osc.stop(t0 + n.dur + 0.02);
            });
        } catch (e) { /* AudioContext quirk on some browsers — ignore */ }
    }
    function maybePlayForIncoming(m) {
        if (!m) return;
        if (m.sender_type === 'visitor' || m.sender_type === 'system') return;
        playNotificationSound();
    }

    // ── Visitor navigation: back to welcome / end current session ─────
    function goBackToWelcome() {
        // Keep convUuid + messages + status intact so the visitor can resume
        // via the pill on the welcome view, or start another topic (which
        // just posts to the same conversation).
        S.view = 'welcome';
        render();
    }

    function endSession() {
        if (!S.convUuid) { startNewChat(); return; }
        if (!window.confirm(t().endChatConfirm)) return;

        // Flip the UI to "ended" immediately so the visitor sees feedback
        // even before the server round-trip finishes. The Reverb broadcast
        // (webchat.conversation.closed) will land later and stay consistent.
        S.status = 'closed';
        S.view   = 'closed';
        render();

        api('/conversations/' + S.convUuid + '/close', { method: 'POST', body: {} })
            .catch(function () { /* server already noop-safe on repeat close */ });
    }

    // ── Start a fresh chat (after closed) ─────────────────────────────
    function startNewChat() {
        if (S.pusher) { try { S.pusher.disconnect(); } catch (e) {} S.pusher = null; }
        stopPolling();
        S.convUuid = null; S.messages = []; S.seenIds = {}; S.lastMessageId = 0;
        S.status = null; S.agent = null; S.humanOnce = false;
        lsSet(LS_CONV, null); lsSet(LS_HUMAN, null);
        S.view = S.widget && S.widget.pre_chat_ask_email ? 'prechat' : 'welcome';
        render();
    }

    // ── DOM rendering ─────────────────────────────────────────────────
    var el = {
        root: null, launcher: null, hintPill: null,
        panel: null, header: null, body: null, thread: null,
        composer: null, statusBar: null, typingBubble: null,
        settingsMenu: null
    };
    function _(tag, attrs, kids) {
        var e = document.createElement(tag);
        if (attrs) for (var k in attrs) {
            if (k === 'class') e.className = attrs[k];
            else if (k === 'html') e.innerHTML = attrs[k];
            else if (k === 'text') e.textContent = attrs[k];
            else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
            else if (attrs[k] != null) e.setAttribute(k, attrs[k]);
        }
        if (kids) kids.forEach(function (c) { c && e.appendChild(c); });
        return e;
    }
    function svg(icon) {
        var SVGS = {
            close:   '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
            back:    '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>',
            end:     '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v10"/><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/></svg>',
            settings:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            bell:    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
            send:    '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12l16-8-6 18-3-8z"/></svg>',
            chat:    '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.13L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>',
            arrow:   '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>',
            spark:   '<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M12 2l1.6 4.4L18 8l-4.4 1.6L12 14l-1.6-4.4L6 8l4.4-1.6z"/></svg>',
            check:   '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12l5 5L20 6"/></svg>'
        };
        return SVGS[icon] || '';
    }
    function ensureRoot() {
        if (el.root) return;
        // Give the widget its own directionality so RTL host pages don't warp us
        // and vice-versa. `data-lang` flips the panel from AR-RTL to EN-LTR.
        // Seed data-position immediately (before /session resolves) so the
        // fixed-positioned launcher doesn't flash flush against the viewport
        // edge without its 24px offset. applyThemeFromWidget() will overwrite
        // this once the tenant's widget config lands.
        el.root = _('div', {
            id: 'wvch-root',
            'data-lang': S.lang,
            'data-position': CONFIG.position === 'left' ? 'left' : 'right',
            dir: t().dir
        });
        document.body.appendChild(el.root);
        injectStyles();
        injectFonts();

        // Esc closes the settings menu first, then the panel
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (S.settingsOpen) { closeSettingsMenu(); return; }
            if (S.open) togglePanel();
        });

        // Click outside the settings menu closes it
        document.addEventListener('click', function () {
            if (S.settingsOpen) closeSettingsMenu();
        });
    }
    function applyThemeFromWidget() {
        if (!el.root || !S.widget) return;
        var accent = S.widget.theme_color || CONFIG.accent || '#2E5BFF';
        el.root.style.setProperty('--wvch-accent',      accent);
        el.root.style.setProperty('--wvch-accent-2',    shade(accent, -8));
        el.root.style.setProperty('--wvch-accent-soft', rgba(accent, 0.12));
        el.root.style.setProperty('--wvch-accent-ring', rgba(accent, 0.28));
        el.root.setAttribute('data-position', S.widget.position || 'right');
    }
    function switchLang(newLang) {
        if (newLang !== 'ar' && newLang !== 'en') return;
        if (S.lang === newLang) return;
        S.lang = newLang;
        lsSet(LS_LANG, newLang);
        if (el.root) {
            el.root.setAttribute('data-lang', newLang);
            el.root.setAttribute('dir', t().dir);
        }
        render();
    }

    function render() {
        ensureRoot();
        if (S.widget && S.widget.enabled === false) {
            if (el.launcher) el.launcher.style.display = 'none';
            if (el.panel)    el.panel.style.display    = 'none';
            return;
        }
        renderLauncher();
        renderPanel();
    }

    function renderLauncher() {
        if (el.launcher && el.launcher.parentNode) el.launcher.parentNode.removeChild(el.launcher);
        if (el.hintPill && el.hintPill.parentNode) el.hintPill.parentNode.removeChild(el.hintPill);

        // The floating hint pill sits above/beside the launcher and fades in
        // after 1 s, once per session (spec §5). Not shown when the panel is
        // open (icon becomes ✕).
        if (!S.open && !S.hintShown) {
            var hint = _('div', { class: 'wvch-hint', role: 'status' }, [
                _('span', { text: t().floatingHint })
            ]);
            el.hintPill = hint;
            el.root.appendChild(hint);
            setTimeout(function () {
                hint.classList.add('wvch-hint-in');
            }, 1000);
            setTimeout(function () {
                if (hint.parentNode) hint.parentNode.removeChild(hint);
                el.hintPill = null;
                S.hintShown = true;
                lsSet(LS_TIP, '1');
            }, 9000);
        }

        var bubble = _('button', {
            class: 'wvch-launcher' + (S.open ? ' wvch-launcher-open' : ''),
            'aria-label': S.open ? t().closeAria : t().openAria,
            'aria-expanded': String(!!S.open),
            onclick: togglePanel
        }, [
            _('span', { class: 'wvch-launcher-ring' }),
            _('span', { class: 'wvch-launcher-icon wvch-launcher-icon-chat', html: svg('chat') }),
            _('span', { class: 'wvch-launcher-icon wvch-launcher-icon-close', html: svg('close') })
        ]);
        el.launcher = bubble;
        el.root.appendChild(bubble);
    }

    function renderPanel() {
        if (!S.open) {
            if (el.panel && el.panel.parentNode) el.panel.parentNode.removeChild(el.panel);
            el.panel = el.header = el.body = el.thread = el.statusBar = el.composer = null;
            return;
        }
        if (el.panel && el.panel.parentNode) el.panel.parentNode.removeChild(el.panel);

        var titleName = t().headerTitle || (S.widget && S.widget.name) || t().headerFallback;
        var promise   = (S.widget && S.widget.header_subtitle) || t().headerPromise;

        var langBtn = _('button', {
            class: 'wvch-header-lang',
            type: 'button',
            'aria-label': t().langToggleAria,
            onclick: function () { switchLang(S.lang === 'ar' ? 'en' : 'ar'); },
            text: t().langToggle
        });
        var backBtn = null;
        if (S.view === 'chat') {
            backBtn = _('button', {
                class: 'wvch-header-btn wvch-header-back',
                type: 'button',
                'aria-label': t().backAria,
                title: t().backAria,
                onclick: goBackToWelcome,
                html: svg('back')
            });
        }
        var settingsBtn = _('button', {
            class: 'wvch-header-btn wvch-header-settings',
            type: 'button',
            'aria-label': t().settingsAria,
            'aria-expanded': String(!!S.settingsOpen),
            title: t().settingsAria,
            onclick: function (e) { e.stopPropagation(); toggleSettingsMenu(); },
            html: svg('settings')
        });
        var closeBtn = _('button', {
            class: 'wvch-header-close',
            type: 'button',
            'aria-label': t().closeAria,
            onclick: togglePanel,
            html: svg('close')
        });

        var brandInitial = (titleName || '?').trim().charAt(0).toUpperCase();
        var brandBadge = _('div', { class: 'wvch-header-brand' }, [
            _('span', { class: 'wvch-header-brand-badge', text: brandInitial }),
            _('span', { class: 'wvch-header-brand-dot',  'aria-hidden': 'true' })
        ]);
        var titles = _('div', { class: 'wvch-header-titles' }, [
            _('div', { class: 'wvch-header-name',    text: titleName }),
            _('div', { class: 'wvch-header-promise', text: promise })
        ]);
        var actionKids = [];
        if (backBtn) actionKids.push(backBtn);
        actionKids.push(langBtn);
        actionKids.push(settingsBtn);
        actionKids.push(closeBtn);
        var actions = _('div', { class: 'wvch-header-actions' }, actionKids);

        el.header = _('div', { class: 'wvch-header' }, [brandBadge, titles, actions]);

        el.body = _('div', { class: 'wvch-body' });
        el.statusBar = _('div', { class: 'wvch-statusbar' });
        el.composer  = _('div', { class: 'wvch-composer' });

        var panelKids = [el.header, el.statusBar, el.body, el.composer];
        if (S.widget && S.widget.show_branding) {
            panelKids.push(_('div', { class: 'wvch-branding' }, [
                _('span', { class: 'wvch-branding-bolt', html: svg('spark') }),
                _('span', { html: t().branding + ' <b>Wavadesk</b>' })
            ]));
        }

        el.panel = _('div', {
            class: 'wvch-panel',
            role: 'dialog',
            'aria-modal': 'false',
            'aria-label': titleName
        }, panelKids);
        el.root.appendChild(el.panel);

        if (S.settingsOpen) renderSettingsMenu();

        renderStatus();
        renderView();
    }

    function toggleSettingsMenu() {
        S.settingsOpen = !S.settingsOpen;
        if (S.settingsOpen) {
            renderSettingsMenu();
            // Update aria-expanded on the button without a full re-render
            var btn = el.panel && el.panel.querySelector('.wvch-header-settings');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            // Prime AudioContext on this user gesture so the first bot reply
            // isn't blocked by browser autoplay policies.
            ensureAudioCtx();
        } else {
            removeSettingsMenu();
            var btn2 = el.panel && el.panel.querySelector('.wvch-header-settings');
            if (btn2) btn2.setAttribute('aria-expanded', 'false');
        }
    }

    function removeSettingsMenu() {
        if (el.settingsMenu && el.settingsMenu.parentNode) {
            el.settingsMenu.parentNode.removeChild(el.settingsMenu);
        }
        el.settingsMenu = null;
    }

    function renderSettingsMenu() {
        removeSettingsMenu();
        if (!el.panel) return;

        var soundRow = _('button', {
            class: 'wvch-menu-row',
            type: 'button',
            role: 'switch',
            'aria-checked': String(!!S.soundEnabled),
            onclick: function (e) { e.stopPropagation(); toggleSound(); }
        }, [
            _('span', { class: 'wvch-menu-icon', html: svg('bell') }),
            _('span', { class: 'wvch-menu-label', text: t().soundLabel }),
            _('span', {
                class: 'wvch-toggle' + (S.soundEnabled ? ' wvch-toggle-on' : ''),
                'aria-hidden': 'true'
            }, [_('span', { class: 'wvch-toggle-knob' })])
        ]);

        var kids = [soundRow];

        if (S.convUuid && S.status && S.status !== 'closed') {
            kids.push(_('div', { class: 'wvch-menu-sep', 'aria-hidden': 'true' }));
            kids.push(_('button', {
                class: 'wvch-menu-row wvch-menu-row-danger',
                type: 'button',
                onclick: function (e) { e.stopPropagation(); closeSettingsMenu(); endSession(); }
            }, [
                _('span', { class: 'wvch-menu-icon', html: svg('end') }),
                _('span', { class: 'wvch-menu-label', text: t().endChatMenu })
            ]));
        }

        el.settingsMenu = _('div', {
            class: 'wvch-menu',
            role: 'menu',
            onclick: function (e) { e.stopPropagation(); }
        }, kids);

        el.panel.appendChild(el.settingsMenu);
    }

    function toggleSound() {
        S.soundEnabled = !S.soundEnabled;
        lsSet(LS_SOUND, S.soundEnabled ? '1' : '0');
        if (S.soundEnabled) {
            // Give an audible cue that it just turned on.
            playNotificationSound();
        }
        renderSettingsMenu();
    }

    function closeSettingsMenu() {
        if (!S.settingsOpen) return;
        S.settingsOpen = false;
        removeSettingsMenu();
        var btn = el.panel && el.panel.querySelector('.wvch-header-settings');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function switchToChat() {
        S.view = 'chat';
        renderView();
    }

    function renderView() {
        if (!el.body || !el.composer) return;
        el.body.innerHTML = '';
        el.composer.innerHTML = '';
        el.thread = null;
        el.typingBubble = null;

        if (!S.booted) {
            el.body.appendChild(_('div', { class: 'wvch-loading', text: '…' }));
            return;
        }
        switch (S.view) {
            case 'prechat':  return renderPrechat();
            case 'welcome':  renderWelcome(); renderComposer(); return;
            case 'chat':     renderThread();  renderComposer(); return;
            case 'closed':   return renderClosed();
            case 'offline':  return renderOffline();
        }
    }

    // ── Welcome (empty state — spec §2) ───────────────────────────────
    function renderWelcome() {
        var wrap = _('div', { class: 'wvch-welcome' });

        // Visitor came back via the header ⤺ arrow while a live conversation
        // is still open — surface a one-click "resume" pill so they don't
        // silently lose their thread.
        if (S.convUuid && S.status && S.status !== 'closed') {
            var resumeBtn = _('button', {
                class: 'wvch-resume',
                type: 'button',
                'aria-label': t().resumeChat,
                onclick: function () { S.view = 'chat'; renderView(); }
            }, [
                _('span', { class: 'wvch-resume-dot', 'aria-hidden': 'true' }),
                _('span', { class: 'wvch-resume-text', text: t().resumeChat }),
                _('span', { class: 'wvch-resume-chev', 'aria-hidden': 'true', html: svg('arrow') })
            ]);
            wrap.appendChild(resumeBtn);
        }

        wrap.appendChild(_('div', { class: 'wvch-welcome-title', text: t().welcomeTitle }));
        var subText = (S.widget && S.widget.welcome_message) || t().welcomeSub;
        wrap.appendChild(_('div', { class: 'wvch-welcome-sub', text: subText }));

        var list = _('div', { class: 'wvch-topics', role: 'list' });
        t().topics.forEach(function (topic, i) {
            var badge = _('span', { class: 'wvch-topic-num', text: toDigit(i + 1) });
            var card = _('button', {
                class: 'wvch-topic wvch-topic-' + topic.tint,
                type: 'button',
                role: 'listitem',
                'aria-label': topic.label,
                onclick: function () { onTopicClick(topic); }
            }, [
                badge,
                _('span', { class: 'wvch-topic-label', text: topic.label }),
                _('span', { class: 'wvch-topic-arrow', html: svg('arrow') })
            ]);
            card.style.setProperty('--wvch-stagger', (i * 70) + 'ms');
            list.appendChild(card);
        });
        wrap.appendChild(list);

        el.body.appendChild(wrap);
    }

    function onTopicClick(topic) {
        // Every topic goes through the real backend so answers come from the
        // AI / assigned agent — the typing dots animate until the reply lands.
        if (topic.id === 't-agent') {
            requestAgent();
            return;
        }
        sendMessage(topic.label);
    }

    // ── Thread ────────────────────────────────────────────────────────
    function renderThread() {
        el.thread = _('div', { class: 'wvch-thread' });
        el.body.appendChild(el.thread);
        S.messages.forEach(function (m) { appendMessage(m, { skipAnim: true }); });
        if (S.typing) drawTyping();
        scrollToBottom(true);
    }

    function appendMessage(m, opts) {
        if (!el.thread) return;
        opts = opts || {};
        var side;
        if (m.sender_type === 'visitor')      side = 'right';
        else if (m.sender_type === 'system')  side = 'center';
        else                                  side = 'left';

        var row = _('div', { class: 'wvch-msg wvch-msg-' + side, 'data-mid': String(m.id) });

        if (m.sender_type === 'system') {
            row.appendChild(_('div', { class: 'wvch-system', text: m.body }));
            el.thread.appendChild(row);
            scrollToBottom();
            m._rendered = true;
            return;
        }

        // Avatar for the bot/agent side. Turns green after the first human handoff.
        if (side === 'left') {
            var isHuman = S.humanOnce || m.sender_type === 'agent';
            row.appendChild(_('div', {
                class: 'wvch-avatar' + (isHuman ? ' wvch-avatar-human' : ' wvch-avatar-bot'),
                'aria-hidden': 'true',
                text: initialsOf(m)
            }));
        }

        var bubble = _('div', { class: 'wvch-bubble wvch-bubble-' + side });
        if (m.pending) bubble.classList.add('wvch-bubble-pending');
        if (m.failed)  bubble.classList.add('wvch-bubble-failed');
        bubble.appendChild(_('div', { class: 'wvch-bubble-body', text: m.body }));
        row.appendChild(bubble);

        // Fresh bot replies get the typewriter effect (spec §2). History replays
        // instantly so re-opens don't feel slow.
        if (!opts.skipAnim && side === 'left' && !m._rendered && (m.body || '').length > 0) {
            typewriter(bubble.querySelector('.wvch-bubble-body'), m.body);
        }
        m._rendered = true;

        el.thread.appendChild(row);
        scrollToBottom();
    }

    function typewriter(node, text) {
        node.textContent = '';
        var caret = document.createElement('span');
        caret.className = 'wvch-caret';
        node.appendChild(caret);
        var i = 0, step = 2;
        var timer = setInterval(function () {
            i = Math.min(text.length, i + step);
            caret.remove();
            node.textContent = text.slice(0, i);
            node.appendChild(caret);
            scrollToBottom();
            if (i >= text.length) {
                clearInterval(timer);
                setTimeout(function () { if (caret.parentNode) caret.remove(); }, 400);
            }
        }, 16);
    }

    function showTyping() {
        S.typing = true;
        S.typingSince = Date.now();
        drawTyping();
    }
    function hideTyping() {
        S.typing = false;
        if (el.typingBubble && el.typingBubble.parentNode) el.typingBubble.parentNode.removeChild(el.typingBubble);
        el.typingBubble = null;
    }
    function drawTyping() {
        if (!el.thread) return;
        if (el.typingBubble && el.typingBubble.parentNode) return; // already visible
        var row = _('div', { class: 'wvch-msg wvch-msg-left wvch-typing-row' }, [
            _('div', {
                class: 'wvch-avatar ' + (S.humanOnce ? 'wvch-avatar-human' : 'wvch-avatar-bot'),
                'aria-hidden': 'true',
                text: '·'
            }),
            _('div', { class: 'wvch-bubble wvch-bubble-left wvch-typing' }, [
                _('span', { class: 'wvch-dot' }),
                _('span', { class: 'wvch-dot' }),
                _('span', { class: 'wvch-dot' })
            ])
        ]);
        el.typingBubble = row;
        el.thread.appendChild(row);
        scrollToBottom();
    }

    // ── Status bar (delivery shimmer + human-handoff card) ────────────
    function renderStatus() {
        if (!el.statusBar) return;
        el.statusBar.innerHTML = '';
        // Only surface pending/handoff cards inside the actual chat view — on
        // welcome (visitor tapped ← back) they'd look orphaned above the topics.
        if (S.view !== 'chat') return;
        if (S.status === 'pending') {
            el.statusBar.appendChild(_('div', { class: 'wvch-connecting' }, [
                _('div', { class: 'wvch-connecting-bar' }, [_('span', { class: 'wvch-connecting-shine' })]),
                _('div', { class: 'wvch-connecting-row' }, [
                    _('span', { class: 'wvch-spinner', 'aria-hidden': 'true' }),
                    _('span', { text: t().connecting })
                ])
            ]));
        } else if (S.status === 'assigned' && S.agent) {
            var name = S.agent.name || S.agent.display_name || '';
            var role = S.agent.role_title || S.agent.title || t().handoffRoleFallback;
            el.statusBar.appendChild(_('div', { class: 'wvch-handoff' }, [
                _('div', { class: 'wvch-handoff-avatar', text: initialsOf({ sender: S.agent, body: name }) }),
                _('div', { class: 'wvch-handoff-meta' }, [
                    _('div', { class: 'wvch-handoff-name', text: (t().handoffPrefix + ' ' + name).trim() }),
                    _('div', { class: 'wvch-handoff-role', text: role })
                ]),
                _('span', { class: 'wvch-handoff-check', html: svg('check') })
            ]));
        }
    }

    // ── Prechat form ──────────────────────────────────────────────────
    function renderPrechat() {
        var nameInput  = _('input', { type: 'text',  class: 'wvch-input',      placeholder: t().prechatName  });
        var emailInput = _('input', { type: 'email', class: 'wvch-input',      placeholder: t().prechatEmail });
        var btn = _('button', {
            class: 'wvch-btn wvch-btn-primary',
            type: 'button',
            text: t().prechatStart,
            onclick: function () {
                btn.disabled = true; btn.textContent = t().prechatStarting;
                submitPrechat(nameInput.value, emailInput.value)
                    .catch(function () { btn.disabled = false; btn.textContent = t().prechatStart; });
            }
        });
        el.body.appendChild(_('div', { class: 'wvch-prechat' }, [
            _('div', { class: 'wvch-welcome-title', text: t().prechatTitle }),
            _('div', { class: 'wvch-welcome-sub',   text: t().prechatSub }),
            nameInput, emailInput, btn
        ]));
    }

    function renderClosed() {
        el.body.appendChild(_('div', { class: 'wvch-closed' }, [
            _('div', { class: 'wvch-welcome-title', text: t().closedTitle }),
            _('div', { class: 'wvch-welcome-sub',   text: t().closedSub }),
            _('button', {
                class: 'wvch-btn wvch-btn-primary',
                type: 'button',
                text: t().startNew,
                onclick: startNewChat
            })
        ]));
    }
    function renderOffline() {
        var msg = (S.widget && S.widget.offline_message) || t().welcomeSub;
        el.body.appendChild(_('div', { class: 'wvch-closed' }, [
            _('div', { class: 'wvch-welcome-title', text: t().offlineTitle }),
            _('div', { class: 'wvch-welcome-sub',   text: msg })
        ]));
    }

    // ── Composer ──────────────────────────────────────────────────────
    function renderComposer() {
        if (!el.composer) return;
        el.composer.innerHTML = '';
        if (S.view === 'closed' || S.view === 'prechat' || S.view === 'offline') return;

        var textarea = _('textarea', {
            class: 'wvch-composer-input',
            rows: '1',
            placeholder: t().composerPlaceholder,
            'aria-label': t().composerPlaceholder
        });
        var sendBtn = _('button', {
            class: 'wvch-composer-send',
            type: 'button',
            'aria-label': t().sendAria,
            html: svg('send'),
            disabled: 'disabled'
        });
        function tryUnlock() {
            var has = textarea.value.trim().length > 0;
            if (has) sendBtn.removeAttribute('disabled');
            else     sendBtn.setAttribute('disabled', 'disabled');
        }
        function submit() {
            var body = textarea.value;
            if (!body.trim()) return;
            textarea.value = '';
            autoGrow(textarea);
            tryUnlock();
            sendMessage(body);
        }
        textarea.addEventListener('input', function () { autoGrow(textarea); tryUnlock(); });
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
        });
        sendBtn.addEventListener('click', submit);

        el.composer.appendChild(_('div', { class: 'wvch-composer-shell' }, [textarea, sendBtn]));

        // Focus on desktop only — don't shove up mobile keyboards behind the user's back
        if (window.innerWidth >= 640) setTimeout(function () { textarea.focus(); }, 60);
    }

    function autoGrow(t) {
        t.style.height = 'auto';
        t.style.height = Math.min(140, t.scrollHeight) + 'px';
    }

    function scrollToBottom(force) {
        var body = el.body; if (!body) return;
        if (force) { body.scrollTop = body.scrollHeight; return; }
        var near = body.scrollHeight - body.scrollTop - body.clientHeight < 120;
        if (near) body.scrollTop = body.scrollHeight;
    }
    function togglePanel() {
        S.open = !S.open;
        lsSet(LS_OPEN, S.open ? '1' : null);
        if (S.open && !S.booted && !S.booting) {
            render();
            ensureSession().then(render).catch(function () {
                if (el.body) {
                    el.body.innerHTML = '';
                    el.body.appendChild(_('div', { class: 'wvch-loading', text: t().couldNotConnect }));
                }
            });
        } else {
            render();
        }
    }

    // ── Small helpers ─────────────────────────────────────────────────
    function initialsOf(m) {
        var name = '';
        if (m && m.sender && m.sender.name)      name = m.sender.name;
        else if (m && m.sender_name)             name = m.sender_name;
        if (!name) return '·';
        var parts = name.trim().split(/\s+/);
        var a = parts[0].charAt(0);
        var b = parts[1] ? parts[1].charAt(0) : '';
        return (a + b).toUpperCase() || '·';
    }
    function toDigit(n) {
        // AR-Indic digits under the Arabic locale, Western digits under EN
        if (S.lang !== 'ar') return String(n);
        var map = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        return String(n).split('').map(function (d) { return map[+d] || d; }).join('');
    }
    function hexToRgb(hex) {
        var h = (hex || '').replace('#', '');
        if (h.length === 3) h = h.split('').map(function (c) { return c + c; }).join('');
        var n = parseInt(h, 16);
        if (isNaN(n)) return { r: 46, g: 91, b: 255 };
        return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
    }
    function rgba(hex, a) {
        var c = hexToRgb(hex);
        return 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + a + ')';
    }
    function shade(hex, percent) {
        var c = hexToRgb(hex);
        function s(v) { return Math.max(0, Math.min(255, v + Math.round(v * percent / 100))); }
        return 'rgb(' + s(c.r) + ',' + s(c.g) + ',' + s(c.b) + ')';
    }

    // ── Fonts (IBM Plex Sans Arabic) ──────────────────────────────────
    function injectFonts() {
        if (document.getElementById('wvch-fonts')) return;
        var pre1 = document.createElement('link');
        pre1.rel = 'preconnect'; pre1.href = 'https://fonts.googleapis.com';
        var pre2 = document.createElement('link');
        pre2.rel = 'preconnect'; pre2.href = 'https://fonts.gstatic.com'; pre2.crossOrigin = 'anonymous';
        var link = document.createElement('link');
        link.id = 'wvch-fonts';
        link.rel = 'stylesheet';
        link.href = 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap';
        document.head.appendChild(pre1);
        document.head.appendChild(pre2);
        document.head.appendChild(link);
    }

    // ── Styles (spec §3 design system + §4 animation table) ──────────
    function injectStyles() {
        if (document.getElementById('wvch-styles')) return;
        var css = [
            "#wvch-root {",
            "  --wvch-accent: #2E5BFF;",
            "  --wvch-accent-2: #4B4BE0;",
            "  --wvch-accent-soft: rgba(46,91,255,.12);",
            "  --wvch-accent-ring: rgba(46,91,255,.28);",
            "  --wvch-text: #10162B;",
            "  --wvch-text-2: #6E7691;",
            "  --wvch-text-3: #A5AEC6;",
            "  --wvch-line: #E7EAF3;",
            "  --wvch-panel-bg: #F7F8FC;",
            "  --wvch-card: #FFFFFF;",
            "  --wvch-success: #17A85C;",
            "  --wvch-success-2: #2ED47A;",
            "  --wvch-tint-blue: #EDF1FF;",
            "  --wvch-tint-orange: #FFF1EC;",
            "  --wvch-tint-green: #EAF7F1;",
            "  --wvch-tint-purple: #F1EDFF;",
            "  font-family: 'IBM Plex Sans Arabic', 'IBM Plex Sans', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;",
            "  color: var(--wvch-text);",
            "  -webkit-font-smoothing: antialiased;",
            "  -moz-osx-font-smoothing: grayscale;",
            "}",
            "#wvch-root, #wvch-root *, #wvch-root *::before, #wvch-root *::after { box-sizing: border-box; }",
            "#wvch-root button { font: inherit; color: inherit; }",

            /* ---------- Launcher (spec §2 floating bubble) ---------- */
            "#wvch-root .wvch-launcher {",
            "  position: fixed; bottom: 24px;",
            "  width: 62px; height: 62px; border-radius: 22px;",
            "  border: none; cursor: pointer;",
            "  background: linear-gradient(135deg, var(--wvch-accent), var(--wvch-accent-2));",
            "  color: #fff;",
            "  box-shadow: 0 14px 34px -10px " + "rgba(46,91,255,.55)" + ";",
            "  z-index: 2147483000;",
            "  display: inline-flex; align-items: center; justify-content: center;",
            "  animation: wvch-float 4.5s ease-in-out infinite;",
            "  transition: transform .18s ease;",
            "}",
            "#wvch-root[data-position='right'] .wvch-launcher { right: 32px; }",
            "#wvch-root[data-position='left']  .wvch-launcher { left: 32px; }",
            "#wvch-root .wvch-launcher:hover { transform: translateY(-2px); }",
            "#wvch-root .wvch-launcher:active { transform: scale(.96); }",
            "#wvch-root .wvch-launcher-ring {",
            "  position: absolute; inset: -6px; border-radius: 26px;",
            "  border: 2px solid var(--wvch-accent);",
            "  opacity: 0; pointer-events: none;",
            "  animation: wvch-pulse 2.6s ease-out infinite;",
            "}",
            "#wvch-root .wvch-launcher-icon { display: inline-flex; align-items: center; justify-content: center; transition: opacity .18s ease, transform .18s ease; }",
            "#wvch-root .wvch-launcher-icon-close { position: absolute; opacity: 0; transform: rotate(-90deg); }",
            "#wvch-root .wvch-launcher-open .wvch-launcher-icon-chat  { opacity: 0; transform: rotate(90deg); }",
            "#wvch-root .wvch-launcher-open .wvch-launcher-icon-close { opacity: 1; transform: rotate(0); }",
            "#wvch-root .wvch-launcher-open .wvch-launcher-ring { animation: none; opacity: 0; }",

            "@keyframes wvch-float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }",
            "@keyframes wvch-pulse { 0% { opacity: .55; transform: scale(.9); } 70% { opacity: 0; transform: scale(1.25); } 100% { opacity: 0; transform: scale(1.25); } }",

            /* ---------- Floating hint pill ---------- */
            "#wvch-root .wvch-hint {",
            "  position: fixed; bottom: 40px;",
            "  padding: 8px 14px;",
            "  background: #fff; color: var(--wvch-text);",
            "  border-radius: 999px;",
            "  box-shadow: 0 12px 28px -12px rgba(16,22,43,.35);",
            "  font-size: 13px; font-weight: 500;",
            "  opacity: 0; transform: translateY(4px);",
            "  transition: opacity .28s ease, transform .28s ease;",
            "  z-index: 2147482999;",
            "  pointer-events: none;",
            "}",
            "#wvch-root[data-position='right'] .wvch-hint { right: 104px; }",
            "#wvch-root[data-position='left']  .wvch-hint { left: 104px; }",
            "#wvch-root .wvch-hint.wvch-hint-in { opacity: 1; transform: translateY(0); }",

            /* ---------- Panel ---------- */
            "#wvch-root .wvch-panel {",
            "  position: fixed; bottom: 104px;",
            "  width: 404px; max-width: calc(100vw - 32px);",
            "  height: 660px; max-height: calc(100vh - 130px);",
            "  background: var(--wvch-panel-bg);",
            "  border-radius: 26px;",
            "  box-shadow: 0 40px 80px -28px rgba(16,22,43,.42);",
            "  overflow: hidden;",
            "  display: flex; flex-direction: column;",
            "  z-index: 2147483000;",
            "  transform-origin: bottom right;",
            "  animation: wvch-panel-in 520ms cubic-bezier(.22,1.2,.36,1);",
            "}",
            "#wvch-root[data-position='right'] .wvch-panel { right: 32px; transform-origin: bottom right; }",
            "#wvch-root[data-position='left']  .wvch-panel { left: 32px;  transform-origin: bottom left; }",
            "@keyframes wvch-panel-in { from { opacity: 0; transform: translateY(24px) scale(.92); } to { opacity: 1; transform: translateY(0) scale(1); } }",
            "@media (max-width: 480px) {",
            "  #wvch-root .wvch-panel { width: calc(100vw - 16px); height: calc(100vh - 100px); left: 8px; right: 8px; bottom: 92px; border-radius: 22px; }",
            "  #wvch-root .wvch-launcher { width: 58px; height: 58px; }",
            "}",

            /* ---------- Header ---------- */
            "#wvch-root .wvch-header {",
            "  background: linear-gradient(135deg, var(--wvch-accent), var(--wvch-accent-2));",
            "  color: #fff;",
            "  padding: 16px 18px 18px;",
            "  display: flex; align-items: center; gap: 12px;",
            "}",
            "#wvch-root .wvch-header-brand {",
            "  position: relative; width: 40px; height: 40px; border-radius: 14px;",
            "  background: rgba(255,255,255,.16);",
            "  display: inline-flex; align-items: center; justify-content: center;",
            "  flex-shrink: 0;",
            "}",
            "#wvch-root .wvch-header-brand-badge {",
            "  font-size: 15px; font-weight: 700; color: #fff; letter-spacing: .02em;",
            "}",
            "#wvch-root .wvch-header-brand-dot {",
            "  position: absolute; width: 12px; height: 12px; border-radius: 50%;",
            "  background: var(--wvch-success-2);",
            "  border: 2px solid var(--wvch-accent-2);",
            "  bottom: -2px;",
            "}",
            "#wvch-root[dir='ltr'] .wvch-header-brand-dot { right: -2px; }",
            "#wvch-root[dir='rtl'] .wvch-header-brand-dot { left: -2px; }",
            "#wvch-root .wvch-header-titles { flex: 1; min-width: 0; line-height: 1.25; }",
            "#wvch-root .wvch-header-name    { font-size: 15px; font-weight: 700; }",
            "#wvch-root .wvch-header-promise { font-size: 12.5px; opacity: .88; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }",
            "#wvch-root .wvch-header-actions { display: inline-flex; gap: 6px; align-items: center; }",
            "#wvch-root .wvch-header-lang, #wvch-root .wvch-header-close, #wvch-root .wvch-header-btn {",
            "  background: rgba(255,255,255,.14); border: none;",
            "  color: #fff; cursor: pointer;",
            "  height: 34px; min-width: 34px; padding: 0 10px;",
            "  border-radius: 12px;",
            "  display: inline-flex; align-items: center; justify-content: center;",
            "  font-size: 13px; font-weight: 600;",
            "  transition: background .18s ease, transform .18s ease;",
            "}",
            "#wvch-root .wvch-header-lang:hover, #wvch-root .wvch-header-close:hover, #wvch-root .wvch-header-btn:hover { background: rgba(255,255,255,.24); }",
            "#wvch-root .wvch-header-close:hover { transform: rotate(90deg); }",
            "#wvch-root .wvch-header-back:hover { transform: translateX(-2px); }",
            "#wvch-root[dir='rtl'] .wvch-header-back svg { transform: scaleX(-1); }",
            "#wvch-root[dir='rtl'] .wvch-header-back:hover { transform: translateX(2px); }",
            "#wvch-root .wvch-header-end:hover { background: rgba(220,53,69,.55); }",
            "#wvch-root .wvch-header-settings:hover svg { transform: rotate(35deg); }",
            "#wvch-root .wvch-header-settings svg { transition: transform .25s ease; }",

            /* ---------- Settings menu ---------- */
            "#wvch-root .wvch-menu {",
            "  position: absolute; top: 72px; z-index: 10;",
            "  min-width: 240px; max-width: calc(100% - 24px);",
            "  background: #fff; color: var(--wvch-text);",
            "  border-radius: 14px; padding: 6px;",
            "  box-shadow: 0 18px 40px -14px rgba(16,22,43,.28), 0 2px 6px rgba(16,22,43,.08);",
            "  border: 1px solid rgba(16,22,43,.06);",
            "  animation: wvch-menu-in 180ms cubic-bezier(.2,1.2,.4,1);",
            "}",
            "#wvch-root[dir='ltr'] .wvch-menu { right: 12px; transform-origin: top right; }",
            "#wvch-root[dir='rtl'] .wvch-menu { left: 12px;  transform-origin: top left; }",
            "@keyframes wvch-menu-in { from { opacity: 0; transform: translateY(-4px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }",
            "#wvch-root .wvch-menu-row {",
            "  display: flex; align-items: center; gap: 10px; width: 100%;",
            "  padding: 10px 12px; border-radius: 10px;",
            "  background: transparent; border: none; cursor: pointer;",
            "  color: var(--wvch-text); font: inherit; font-size: 13.5px;",
            "  text-align: inherit;",
            "  transition: background .15s ease;",
            "}",
            "#wvch-root .wvch-menu-row:hover { background: var(--wvch-accent-soft); }",
            "#wvch-root .wvch-menu-icon { display: inline-flex; color: var(--wvch-text-2); flex-shrink: 0; }",
            "#wvch-root .wvch-menu-label { flex: 1; min-width: 0; font-weight: 500; }",
            "#wvch-root .wvch-menu-sep { height: 1px; background: var(--wvch-line); margin: 4px 8px; }",
            "#wvch-root .wvch-menu-row-danger { color: #b91c1c; }",
            "#wvch-root .wvch-menu-row-danger .wvch-menu-icon { color: #b91c1c; }",
            "#wvch-root .wvch-menu-row-danger:hover { background: #fef2f2; }",

            /* iOS-style toggle switch */
            "#wvch-root .wvch-toggle {",
            "  position: relative; display: inline-block;",
            "  width: 34px; height: 20px; border-radius: 999px;",
            "  background: #cbd5e1; flex-shrink: 0;",
            "  transition: background .18s ease;",
            "}",
            "#wvch-root .wvch-toggle-knob {",
            "  position: absolute; top: 2px; left: 2px;",
            "  width: 16px; height: 16px; border-radius: 50%;",
            "  background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);",
            "  transition: transform .18s ease;",
            "}",
            "#wvch-root .wvch-toggle-on { background: var(--wvch-accent); }",
            "#wvch-root .wvch-toggle-on .wvch-toggle-knob { transform: translateX(14px); }",
            "#wvch-root[dir='rtl'] .wvch-toggle-on .wvch-toggle-knob { transform: translateX(-14px); }",

            /* ---------- Status bar ---------- */
            "#wvch-root .wvch-statusbar { padding: 0 18px; }",
            "#wvch-root .wvch-statusbar:empty { display: none; }",

            "#wvch-root .wvch-connecting { margin-top: 12px; background: #fff; border-radius: 14px; padding: 12px 14px; box-shadow: 0 1px 0 var(--wvch-line); }",
            "#wvch-root .wvch-connecting-bar { position: relative; height: 6px; border-radius: 999px; background: var(--wvch-accent-soft); overflow: hidden; }",
            "#wvch-root .wvch-connecting-shine { position: absolute; top: 0; bottom: 0; width: 40%; background: linear-gradient(90deg, transparent, var(--wvch-accent), transparent); animation: wvch-shine 1.5s linear infinite; }",
            "@keyframes wvch-shine { from { transform: translateX(-100%); } to { transform: translateX(260%); } }",
            "#wvch-root .wvch-connecting-row { margin-top: 10px; display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--wvch-text-2); }",
            "#wvch-root .wvch-spinner { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--wvch-accent-soft); border-top-color: var(--wvch-accent); animation: wvch-spin .8s linear infinite; }",
            "@keyframes wvch-spin { to { transform: rotate(360deg); } }",

            "#wvch-root .wvch-handoff { margin-top: 12px; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, var(--wvch-success-2), var(--wvch-success)); color: #fff; padding: 12px 14px; border-radius: 16px; box-shadow: 0 12px 22px -14px rgba(23,168,92,.65); animation: wvch-handoff-in 400ms cubic-bezier(.2,1.2,.4,1); }",
            "@keyframes wvch-handoff-in { from { opacity: 0; transform: translateY(6px) scale(.96); } to { opacity: 1; transform: translateY(0) scale(1); } }",
            "#wvch-root .wvch-handoff-avatar { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.22); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0; }",
            "#wvch-root .wvch-handoff-meta { flex: 1; min-width: 0; line-height: 1.25; }",
            "#wvch-root .wvch-handoff-name { font-size: 13.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }",
            "#wvch-root .wvch-handoff-role { font-size: 11.5px; opacity: .9; margin-top: 2px; }",
            "#wvch-root .wvch-handoff-check { display: inline-flex; width: 26px; height: 26px; border-radius: 50%; background: rgba(255,255,255,.22); align-items: center; justify-content: center; flex-shrink: 0; }",

            /* ---------- Body ---------- */
            "#wvch-root .wvch-body { flex: 1; min-height: 0; overflow-y: auto; padding: 18px 18px 14px; }",
            "#wvch-root .wvch-body::-webkit-scrollbar { width: 8px; }",
            "#wvch-root .wvch-body::-webkit-scrollbar-thumb { background: #d5dbe8; border-radius: 999px; }",
            "#wvch-root .wvch-loading { padding: 40px 20px; text-align: center; color: var(--wvch-text-2); font-size: 13px; }",

            /* ---------- Welcome (spec §2) ---------- */
            "#wvch-root .wvch-resume {",
            "  display: flex; align-items: center; gap: 10px; width: 100%;",
            "  padding: 10px 14px; margin-bottom: 14px;",
            "  background: var(--wvch-accent-soft); color: var(--wvch-accent);",
            "  border: 1px solid var(--wvch-accent-ring); border-radius: 14px;",
            "  cursor: pointer; font: inherit; font-size: 13px; font-weight: 600;",
            "  text-align: inherit;",
            "  transition: background .18s ease, transform .18s ease;",
            "}",
            "#wvch-root .wvch-resume:hover { background: var(--wvch-accent-ring); }",
            "#wvch-root .wvch-resume-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--wvch-success); box-shadow: 0 0 0 3px rgba(23,168,92,.18); flex-shrink: 0; }",
            "#wvch-root .wvch-resume-text { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }",
            "#wvch-root .wvch-resume-chev { display: inline-flex; opacity: .8; }",
            "#wvch-root[dir='rtl'] .wvch-resume-chev svg { transform: scaleX(-1); }",
            "#wvch-root .wvch-welcome-title { font-size: 22px; font-weight: 700; color: var(--wvch-text); line-height: 1.3; }",
            "#wvch-root .wvch-welcome-sub   { font-size: 13.5px; color: var(--wvch-text-2); margin-top: 6px; line-height: 1.55; }",
            "#wvch-root .wvch-topics { display: flex; flex-direction: column; gap: 9px; margin-top: 18px; }",
            "#wvch-root .wvch-topic {",
            "  --wvch-tint: var(--wvch-tint-blue);",
            "  --wvch-num-bg: var(--wvch-accent);",
            "  display: flex; align-items: center; gap: 12px;",
            "  padding: 14px; border-radius: 16px;",
            "  background: var(--wvch-card); border: 1px solid var(--wvch-line);",
            "  cursor: pointer; text-align: inherit;",
            "  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;",
            "  opacity: 0; transform: translateY(8px);",
            "  animation: wvch-topic-in 450ms cubic-bezier(.2,1.1,.4,1) forwards;",
            "  animation-delay: var(--wvch-stagger, 0ms);",
            "}",
            "@keyframes wvch-topic-in { to { opacity: 1; transform: translateY(0); } }",
            "#wvch-root .wvch-topic:hover { transform: translateY(-1px); box-shadow: 0 12px 22px -18px rgba(16,22,43,.35); border-color: transparent; }",
            "#wvch-root .wvch-topic:active { transform: scale(.98); }",
            "#wvch-root .wvch-topic-num { width: 32px; height: 32px; border-radius: 12px; background: var(--wvch-tint); color: var(--wvch-num-bg); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13.5px; flex-shrink: 0; }",
            "#wvch-root .wvch-topic-label { flex: 1; font-size: 14px; font-weight: 500; color: var(--wvch-text); }",
            "#wvch-root .wvch-topic-arrow { color: var(--wvch-text-3); display: inline-flex; }",
            "#wvch-root[dir='rtl'] .wvch-topic-arrow { transform: scaleX(-1); }",
            "#wvch-root .wvch-topic-blue   { --wvch-tint: var(--wvch-tint-blue);   --wvch-num-bg: #2E5BFF; }",
            "#wvch-root .wvch-topic-orange { --wvch-tint: var(--wvch-tint-orange); --wvch-num-bg: #E5734A; }",
            "#wvch-root .wvch-topic-green  { --wvch-tint: var(--wvch-tint-green);  --wvch-num-bg: #17A85C; }",
            "#wvch-root .wvch-topic-purple { --wvch-tint: var(--wvch-tint-purple); --wvch-num-bg: #6E4BE0; }",

            /* ---------- Thread ---------- */
            "#wvch-root .wvch-thread { display: flex; flex-direction: column; gap: 12px; padding-bottom: 6px; }",
            "#wvch-root .wvch-msg { display: flex; gap: 8px; align-items: flex-end; animation: wvch-bubble-in 380ms cubic-bezier(.2,1.1,.4,1); }",
            "#wvch-root .wvch-msg-right { justify-content: flex-end; }",
            "#wvch-root .wvch-msg-left  { justify-content: flex-start; }",
            "#wvch-root .wvch-msg-center{ justify-content: center; }",
            "@keyframes wvch-bubble-in { from { opacity: 0; transform: translateY(6px) scale(.98); } to { opacity: 1; transform: none; } }",
            "#wvch-root .wvch-avatar { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0; }",
            "#wvch-root .wvch-avatar-bot   { background: linear-gradient(135deg, var(--wvch-accent), var(--wvch-accent-2)); }",
            "#wvch-root .wvch-avatar-human { background: linear-gradient(135deg, var(--wvch-success-2), var(--wvch-success)); }",
            "#wvch-root .wvch-bubble { max-width: 78%; padding: 10px 14px; font-size: 14.5px; line-height: 1.7; word-break: break-word; border-radius: 16px; }",
            "#wvch-root .wvch-bubble-left  { background: #fff; color: var(--wvch-text); border: 1px solid var(--wvch-line); }",
            "#wvch-root[dir='rtl'] .wvch-bubble-left  { border-top-right-radius: 5px; }",
            "#wvch-root[dir='ltr'] .wvch-bubble-left  { border-top-left-radius: 5px; }",
            "#wvch-root .wvch-bubble-right { background: linear-gradient(135deg, var(--wvch-accent), var(--wvch-accent-2)); color: #fff; box-shadow: 0 12px 22px -14px " + "rgba(46,91,255,.85)" + "; border: none; }",
            "#wvch-root[dir='rtl'] .wvch-bubble-right { border-top-left-radius: 5px; }",
            "#wvch-root[dir='ltr'] .wvch-bubble-right { border-top-right-radius: 5px; }",
            "#wvch-root .wvch-bubble-body { white-space: pre-wrap; }",
            "#wvch-root .wvch-bubble-pending { opacity: .78; }",
            "#wvch-root .wvch-bubble-failed  { background: #fdecec; color: #b91c1c; border: 1px solid #f7c8c8; box-shadow: none; }",
            "#wvch-root .wvch-system { font-size: 11.5px; color: var(--wvch-text-3); background: transparent; padding: 4px 12px; border: 1px dashed var(--wvch-line); border-radius: 999px; }",

            /* Blinking caret during typewriter effect */
            "#wvch-root .wvch-caret { display: inline-block; width: 6px; height: 1em; margin-inline-start: 2px; vertical-align: -2px; background: var(--wvch-text-3); animation: wvch-caret 800ms steps(1) infinite; }",
            "@keyframes wvch-caret { 50% { opacity: 0; } }",

            /* Typing dots */
            "#wvch-root .wvch-typing { display: inline-flex; align-items: center; gap: 4px; padding: 12px 14px; }",
            "#wvch-root .wvch-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--wvch-text-3); animation: wvch-bounce 1.1s ease-in-out infinite; }",
            "#wvch-root .wvch-dot:nth-child(2) { animation-delay: .16s; }",
            "#wvch-root .wvch-dot:nth-child(3) { animation-delay: .32s; }",
            "@keyframes wvch-bounce { 0%, 80%, 100% { transform: translateY(0); opacity: .45; } 40% { transform: translateY(-3px); opacity: 1; } }",

            /* ---------- Composer ---------- */
            "#wvch-root .wvch-composer { padding: 12px 18px 16px; background: transparent; }",
            "#wvch-root .wvch-composer-shell {",
            "  display: flex; align-items: flex-end; gap: 8px;",
            "  background: #fff;",
            "  border: 1px solid var(--wvch-line);",
            "  border-radius: 18px;",
            "  padding: 8px 10px;",
            "  transition: border-color .18s ease, box-shadow .18s ease;",
            "}",
            "#wvch-root .wvch-composer-shell:focus-within { border-color: var(--wvch-accent); box-shadow: 0 0 0 4px var(--wvch-accent-ring); }",
            "#wvch-root .wvch-composer-input {",
            "  flex: 1; min-height: 36px; max-height: 140px;",
            "  border: none; outline: none; resize: none;",
            "  background: transparent; color: var(--wvch-text);",
            "  font: inherit; font-size: 14px; line-height: 1.55;",
            "  padding: 6px 8px;",
            "}",
            "#wvch-root .wvch-composer-input::placeholder { color: var(--wvch-text-3); }",
            "#wvch-root .wvch-composer-send {",
            "  width: 40px; height: 40px; border-radius: 14px;",
            "  border: none; cursor: pointer;",
            "  background: #E7EAF3; color: #fff;",
            "  display: inline-flex; align-items: center; justify-content: center;",
            "  transform: scale(.92); transition: transform .2s cubic-bezier(.22,1.2,.36,1), background .2s ease, box-shadow .2s ease;",
            "}",
            "#wvch-root .wvch-composer-send:not([disabled]) {",
            "  background: linear-gradient(135deg, var(--wvch-accent), var(--wvch-accent-2));",
            "  transform: scale(1);",
            "  box-shadow: 0 10px 22px -12px rgba(46,91,255,.75);",
            "}",
            "#wvch-root .wvch-composer-send:not([disabled]):hover { transform: scale(1.06); }",
            "#wvch-root .wvch-composer-send:not([disabled]):active { transform: scale(.94); }",
            "#wvch-root .wvch-composer-send[disabled] { cursor: default; }",
            "#wvch-root[dir='rtl'] .wvch-composer-send svg { transform: scaleX(-1); }",

            /* ---------- Prechat / Closed / Offline ---------- */
            "#wvch-root .wvch-prechat { display: flex; flex-direction: column; gap: 10px; }",
            "#wvch-root .wvch-prechat .wvch-input { border: 1px solid var(--wvch-line); border-radius: 12px; padding: 10px 12px; font: inherit; font-size: 14px; outline: none; }",
            "#wvch-root .wvch-prechat .wvch-input:focus { border-color: var(--wvch-accent); box-shadow: 0 0 0 4px var(--wvch-accent-ring); }",
            "#wvch-root .wvch-btn { border: none; border-radius: 14px; padding: 12px 16px; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }",
            "#wvch-root .wvch-btn-primary { background: linear-gradient(135deg, var(--wvch-accent), var(--wvch-accent-2)); color: #fff; box-shadow: 0 12px 22px -14px rgba(46,91,255,.75); }",
            "#wvch-root .wvch-btn-primary:hover { filter: brightness(1.04); }",
            "#wvch-root .wvch-btn-primary:disabled { opacity: .7; cursor: not-allowed; }",
            "#wvch-root .wvch-closed { text-align: center; padding: 40px 12px 24px; display: flex; flex-direction: column; gap: 12px; align-items: center; }",

            /* ---------- Powered-by footer ---------- */
            "#wvch-root .wvch-branding { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px; font-size: 11.5px; color: var(--wvch-text-2); background: #fff; border-top: 1px solid var(--wvch-line); }",
            "#wvch-root .wvch-branding b { color: var(--wvch-text); font-weight: 700; }",
            "#wvch-root .wvch-branding-bolt { display: inline-flex; color: var(--wvch-accent); }",

            /* Reduce motion — respect the user */
            "@media (prefers-reduced-motion: reduce) {",
            "  #wvch-root .wvch-launcher { animation: none; }",
            "  #wvch-root .wvch-launcher-ring { animation: none; }",
            "  #wvch-root .wvch-panel { animation: none; }",
            "  #wvch-root .wvch-topic { animation: none; opacity: 1; transform: none; }",
            "  #wvch-root .wvch-msg { animation: none; }",
            "  #wvch-root .wvch-connecting-shine { animation: none; }",
            "  #wvch-root .wvch-spinner { animation: none; }",
            "}"
        ].join('\n');

        var styleEl = document.createElement('style');
        styleEl.id = 'wvch-styles';
        styleEl.textContent = css;
        document.head.appendChild(styleEl);
    }

    // ── Boot ──────────────────────────────────────────────────────────
    function boot() {
        ensureRoot();
        render();
        // Auto-open respects a stored open state or the `startOpen` config knob
        if (!S.open && CONFIG.startOpen === true) {
            S.open = true;
            lsSet(LS_OPEN, '1');
        }
        if (S.open) {
            render();
            ensureSession().then(render).catch(function () { S.open = false; render(); });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // ── Public API ────────────────────────────────────────────────────
    function bindApi(target) {
        target.open   = function () { if (!S.open) togglePanel(); };
        target.close  = function () { if (S.open)  togglePanel(); };
        target.reset  = function () { lsSet(LS_TOKEN, null); lsSet(LS_CONV, null); lsSet(LS_OPEN, null); lsSet(LS_HUMAN, null); location.reload(); };
        target.setLang= function (l) { switchLang(l); };
    }
    bindApi(window.WavadeskChat);
    bindApi(window.TshlBotChat);
})();
