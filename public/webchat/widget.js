/*!
 * WavaDesk Web Live-Chat widget.
 * Vanilla JS, IIFE, no framework, no build step.
 * Ships as a committed static asset; git pull deploys it as-is.
 *
 * Embed:
 *   <script>window.WavadeskChat = { key: "wck_..." };</script>
 *   <script src="https://your-app-domain/webchat/widget.js" async></script>
 */
(function () {
    'use strict';

    // ── Config guard ──────────────────────────────────────────────────
    var CONFIG = window.WavadeskChat || {};
    if (!CONFIG.key || typeof CONFIG.key !== 'string' || CONFIG.key.indexOf('wck_') !== 0) {
        console.warn('WavadeskChat: missing or invalid window.WavadeskChat.key');
        return;
    }
    if (window.__wvchLoaded) return;      // prevent double-boot if snippet is pasted twice
    window.__wvchLoaded = true;

    // ── Derive API base from own script src (works cross-origin) ──────
    var API_BASE = (function () {
        var found = document.querySelector('script[src*="/webchat/widget.js"]');
        var src = found ? found.src : '';
        return src.replace(/\/webchat\/widget\.js(\?.*)?$/, '');
    })();

    // ── localStorage helpers (keyed per widget key so multiple sites work) ─
    var LS_TOKEN = 'wvch:v1:token:' + CONFIG.key;
    var LS_CONV  = 'wvch:v1:conv:' + CONFIG.key;
    var LS_OPEN  = 'wvch:v1:open:' + CONFIG.key;
    function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function lsSet(k, v) { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} }

    // ── State ─────────────────────────────────────────────────────────
    var S = {
        open:          lsGet(LS_OPEN) === '1',
        booted:        false,
        booting:       false,
        view:          'welcome',           // welcome | prechat | chat | closed | offline
        visitorToken:  lsGet(LS_TOKEN),
        convUuid:      lsGet(LS_CONV),
        widget:        null,
        reverb:        null,
        status:        null,                // bot | pending | assigned | closed
        agent:         null,
        messages:      [],
        lastMessageId: 0,
        sending:       false,
        wsConnected:   false,
        pusher:        null,
        pollTimer:     null,
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
                    S.status = data.active_conversation.status;
                    lsSet(LS_CONV, S.convUuid);
                }
                S.booted = true;
                S.booting = false;
                applyThemeFromWidget();
                if (S.convUuid && S.status !== 'closed' && S.status !== 'bot') {
                    return loadMessages().then(function () {
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
            S.status = data.status || 'bot';
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
                if (S.view !== 'chat') { S.view = 'chat'; render(); } else { renderStatus(); }
                subscribeRealtime();
                startPolling();
            });
    }

    function sendMessage(body) {
        var text = (body || '').trim();
        if (!text || S.sending) return Promise.resolve();
        S.sending = true;
        var localId = 'local-' + Date.now();
        S.messages.push({ id: localId, sender_type: 'visitor', body: text, created_at: new Date().toISOString(), pending: true });
        renderMessages(); scrollToBottom();

        return openConversation()
            .then(function (uuid) {
                return api('/conversations/' + uuid + '/messages', { method: 'POST', body: { body: text } });
            })
            .then(function (data) {
                for (var i = 0; i < S.messages.length; i++) {
                    if (S.messages[i].id === localId) {
                        S.messages[i] = {
                            id: data.message.id,
                            sender_type: 'visitor',
                            body: data.message.body,
                            created_at: data.message.created_at
                        };
                        break;
                    }
                }
                if (data.message.id > S.lastMessageId) S.lastMessageId = data.message.id;
                if (S.status === 'bot') { S.status = 'pending'; renderStatus(); }
                if (S.view !== 'chat') { S.view = 'chat'; render(); }
                if (!S.pusher) { subscribeRealtime(); startPolling(); }
                renderMessages();
            })
            .catch(function () {
                for (var j = 0; j < S.messages.length; j++) {
                    if (S.messages[j].id === localId) { S.messages[j].failed = true; break; }
                }
                renderMessages();
            })
            .then(function () { S.sending = false; renderComposer(); });
    }

    // ── Message polling (fallback for no-WS) ──────────────────────────
    function loadMessages() {
        if (!S.convUuid) return Promise.resolve();
        return api('/conversations/' + S.convUuid + '/messages?after=' + S.lastMessageId)
            .then(function (data) {
                var added = false;
                (data.messages || []).forEach(function (m) {
                    if (m.id <= S.lastMessageId) return;
                    if (S.messages.some(function (x) { return x.id === m.id; })) return;
                    S.messages.push(m);
                    if (m.id > S.lastMessageId) S.lastMessageId = m.id;
                    added = true;
                });
                if (data.conversation && data.conversation.status !== S.status) {
                    S.status = data.conversation.status;
                    if (S.status === 'closed') { S.view = 'closed'; render(); }
                    else renderStatus();
                }
                if (added) { renderMessages(); scrollToBottom(); }
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
                    if (S.messages.some(function (x) { return x.id === m.id; })) return;
                    S.messages.push({
                        id: m.id, sender_type: m.sender_type, sender_id: m.sender_id,
                        body: m.body, created_at: m.created_at
                    });
                    if (m.id > S.lastMessageId) S.lastMessageId = m.id;
                    renderMessages(); scrollToBottom();
                });
                ch.bind('webchat.conversation.claimed', function (payload) {
                    S.status = 'assigned';
                    S.agent = payload.agent || null;
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

    // ── Start a fresh chat (after closed) ─────────────────────────────
    function startNewChat() {
        if (S.pusher) { try { S.pusher.disconnect(); } catch (e) {} S.pusher = null; }
        stopPolling();
        S.convUuid = null; S.messages = []; S.lastMessageId = 0;
        S.status = null; S.agent = null;
        lsSet(LS_CONV, null);
        S.view = S.widget && S.widget.pre_chat_ask_email ? 'prechat' : 'welcome';
        render();
    }

    // ── DOM rendering (imperative, no framework) ──────────────────────
    var el = { root: null, launcher: null, panel: null, body: null, composer: null, statusBar: null };
    function _(tag, attrs, kids) {
        var e = document.createElement(tag);
        if (attrs) for (var k in attrs) {
            if (k === 'class') e.className = attrs[k];
            else if (k === 'html') e.innerHTML = attrs[k];
            else if (k === 'text') e.textContent = attrs[k];
            else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
            else e.setAttribute(k, attrs[k]);
        }
        if (kids) kids.forEach(function (c) { c && e.appendChild(c); });
        return e;
    }
    function svg(icon) {
        var SVGS = {
            chat:    '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 3c5.5 0 10 3.6 10 8s-4.5 8-10 8c-1.3 0-2.5-.2-3.6-.5L3 20l1.4-4.5C3 14 2 12.6 2 11c0-4.4 4.5-8 10-8z"/></svg>',
            message: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v10c0 1.1-.9 2-2 2H7l-5 4V6c0-1.1.9-2 2-2z"/></svg>',
            help:    '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm.9 15.6h-1.8v-1.8h1.8v1.8zm1.9-6.6l-.8.8c-.6.6-1 1.1-1 2.2h-1.8v-.4c0-.9.4-1.6 1-2.2l1.1-1.1c.3-.3.5-.7.5-1.2 0-1-.8-1.8-1.8-1.8s-1.8.8-1.8 1.8H8.4c0-2 1.6-3.6 3.6-3.6s3.6 1.6 3.6 3.6c0 .8-.3 1.5-.8 2z"/></svg>',
            sparkle: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2l1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8L12 2zm7 12l.9 2.6L22.5 17l-2.6.9L19 20.5l-.9-2.6L15.5 17l2.6-.9L19 14z"/></svg>',
            close:   '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 6.4L17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg>',
            send:    '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 20l19-8L3 4v6l14 2-14 2z"/></svg>',
            bolt:    '<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M13 3v7h5l-8 11v-7H5l8-11z"/></svg>'
        };
        return SVGS[icon] || '';
    }
    function ensureRoot() {
        if (el.root) return;
        // dir="ltr" pins the widget's own directionality regardless of the
        // host page (e.g. <html dir="rtl">). Without this, RTL host pages
        // mirror the header controls, composer, and message rows.
        el.root = _('div', { id: 'wvch-root', dir: 'ltr' });
        document.body.appendChild(el.root);
        injectStyles();
    }
    function applyThemeFromWidget() {
        if (!el.root || !S.widget) return;
        el.root.style.setProperty('--wvch-color', S.widget.theme_color || '#2563eb');
        el.root.setAttribute('data-position', S.widget.position || 'right');
        el.root.setAttribute('data-bubble',   S.widget.bubble_style || 'soft');
    }
    function render() {
        ensureRoot();
        if (S.widget && S.widget.enabled === false) {
            // If the tenant disabled the widget after page load, hide everything.
            if (el.launcher) el.launcher.style.display = 'none';
            if (el.panel) el.panel.style.display = 'none';
            return;
        }
        renderLauncher();
        renderPanel();
    }
    function renderLauncher() {
        if (el.launcher && el.launcher.parentNode) el.launcher.parentNode.removeChild(el.launcher);
        var label = null;
        if (S.widget && S.widget.launcher_text) {
            label = _('span', { class: 'wvch-launcher-label', text: S.widget.launcher_text });
        }
        var iconKey = (S.widget && S.widget.launcher_icon) || 'chat';
        var bubble = _('button', {
            class: 'wvch-launcher',
            'aria-label': (S.widget && S.widget.launcher_text) || 'Open chat',
            'aria-expanded': String(!!S.open),
            onclick: togglePanel
        }, [
            label,
            _('span', { class: 'wvch-launcher-icon', html: svg(iconKey) })
        ]);
        el.launcher = bubble;
        el.root.appendChild(bubble);
    }
    function renderPanel() {
        if (!S.open) {
            if (el.panel && el.panel.parentNode) el.panel.parentNode.removeChild(el.panel);
            el.panel = null; return;
        }
        if (el.panel && el.panel.parentNode) el.panel.parentNode.removeChild(el.panel);

        var titleName = (S.widget && S.widget.name) || 'Live Chat';
        var subtitle  = (S.widget && S.widget.header_subtitle) || '';
        var headerInner = [_('div', { class: 'wvch-header-name', text: titleName })];
        if (subtitle) {
            headerInner.push(_('div', { class: 'wvch-header-sub', text: subtitle }));
        }
        var header = _('div', { class: 'wvch-header' }, [
            _('div', { class: 'wvch-header-titles' }, headerInner),
            _('button', {
                class: 'wvch-header-close',
                'aria-label': 'Close chat',
                onclick: togglePanel,
                html: svg('close')
            })
        ]);

        el.body = _('div', { class: 'wvch-body' });
        el.statusBar = _('div', { class: 'wvch-statusbar' });
        el.composer = _('div', { class: 'wvch-composer' });

        var panelKids = [header, el.statusBar, el.body, el.composer];
        if (S.widget && S.widget.show_branding) {
            panelKids.push(_('div', { class: 'wvch-branding' }, [
                _('span', { class: 'wvch-branding-bolt', html: svg('bolt') }),
                _('span', { html: 'Powered by <b>wavadesk</b>' })
            ]));
        }

        el.panel = _('div', { class: 'wvch-panel', role: 'dialog', 'aria-label': titleName }, panelKids);
        el.root.appendChild(el.panel);

        renderStatus();
        renderView();
    }
    function renderView() {
        el.body.innerHTML = '';
        el.composer.innerHTML = '';
        if (!S.booted) {
            el.body.appendChild(_('div', { class: 'wvch-loading', text: 'Loading…' }));
            return;
        }
        switch (S.view) {
            case 'prechat':  return renderPrechat();
            case 'welcome':  return renderWelcome();
            case 'chat':     renderMessages(); renderComposer(); return;
            case 'closed':   return renderClosed();
            case 'offline':  return renderOffline();
        }
    }
    function renderPrechat() {
        var nameInput  = _('input', { type: 'text',  class: 'wvch-input', placeholder: 'Your name (optional)' });
        var emailInput = _('input', { type: 'email', class: 'wvch-input', placeholder: 'Your email (optional)' });
        var btn = _('button', {
            class: 'wvch-btn wvch-btn-primary',
            text: 'Start chat',
            onclick: function () {
                btn.disabled = true; btn.textContent = 'Starting…';
                submitPrechat(nameInput.value, emailInput.value)
                    .catch(function () { btn.disabled = false; btn.textContent = 'Start chat'; });
            }
        });
        el.body.appendChild(_('div', { class: 'wvch-prechat' }, [
            _('div', { class: 'wvch-prechat-title', text: 'Before we start' }),
            _('div', { class: 'wvch-prechat-sub', text: 'Leave your details so we can get back to you.' }),
            nameInput, emailInput, btn
        ]));
    }
    function renderWelcome() {
        var welcome = (S.widget && S.widget.welcome_message) || 'How can we help?';
        var chips = (S.widget && S.widget.suggestions) || [];

        var chipRow = _('div', { class: 'wvch-chips' });
        chips.slice(0, 12).forEach(function (label) {
            chipRow.appendChild(_('button', {
                class: 'wvch-chip',
                text: label,
                onclick: function () { sendMessage(label); }
            }));
        });

        var humanBtn = _('button', {
            class: 'wvch-btn wvch-btn-primary wvch-human-btn',
            text: 'Talk to a human',
            onclick: function () { requestAgent(); }
        });

        el.body.appendChild(_('div', { class: 'wvch-welcome' }, [
            _('div', { class: 'wvch-welcome-msg', text: welcome }),
            chipRow,
            humanBtn
        ]));

        renderComposer();
    }
    function renderMessages() {
        if (!el.body) return;
        // Only rebuild if we're in chat view — welcome/closed views own body
        var list = el.body.querySelector('.wvch-thread');
        if (!list) {
            el.body.innerHTML = '';
            list = _('div', { class: 'wvch-thread' });
            el.body.appendChild(list);
        }
        list.innerHTML = '';
        S.messages.forEach(function (m) {
            var side = m.sender_type === 'visitor' ? 'right' : (m.sender_type === 'system' ? 'center' : 'left');
            var bubble = _('div', { class: 'wvch-bubble' }, [
                _('div', { class: 'wvch-bubble-body', text: m.body })
            ]);
            if (m.pending) bubble.classList.add('wvch-bubble-pending');
            if (m.failed)  bubble.classList.add('wvch-bubble-failed');
            var row = _('div', { class: 'wvch-msg wvch-msg-' + side });
            if (m.sender_type === 'system') {
                row.appendChild(_('div', { class: 'wvch-system', text: m.body }));
            } else {
                row.appendChild(bubble);
            }
            list.appendChild(row);
        });
    }
    function renderClosed() {
        el.body.appendChild(_('div', { class: 'wvch-closed' }, [
            _('div', { class: 'wvch-closed-title', text: 'Chat ended' }),
            _('div', { class: 'wvch-closed-sub', text: 'Thanks for reaching out. Feel free to start a new chat any time.' }),
            _('button', {
                class: 'wvch-btn wvch-btn-primary',
                text: 'Start a new chat',
                onclick: startNewChat
            })
        ]));
    }
    function renderOffline() {
        var msg = (S.widget && S.widget.offline_message) || 'We are offline right now.';
        el.body.appendChild(_('div', { class: 'wvch-closed' }, [
            _('div', { class: 'wvch-closed-title', text: 'We are offline' }),
            _('div', { class: 'wvch-closed-sub', text: msg })
        ]));
    }
    function renderStatus() {
        if (!el.statusBar) return;
        el.statusBar.innerHTML = '';
        if (S.status === 'pending') {
            el.statusBar.appendChild(_('div', { class: 'wvch-status wvch-status-pending' }, [
                _('span', { class: 'wvch-status-dot' }),
                _('span', { text: 'Connecting you to an agent…' })
            ]));
        } else if (S.status === 'assigned' && S.agent) {
            el.statusBar.appendChild(_('div', { class: 'wvch-status wvch-status-connected' }, [
                _('span', { class: 'wvch-status-dot' }),
                _('span', { text: "You're connected to " + S.agent.name })
            ]));
        }
    }
    function renderComposer() {
        if (!el.composer) return;
        el.composer.innerHTML = '';
        if (S.view === 'closed' || S.view === 'prechat' || S.view === 'offline') return;

        var textarea = _('textarea', {
            class: 'wvch-input wvch-input-composer',
            rows: '1',
            placeholder: 'Type your message…',
            'aria-label': 'Type your message'
        });
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var body = textarea.value;
                if (body.trim()) { textarea.value = ''; sendMessage(body); }
            }
        });
        var sendBtn = _('button', {
            class: 'wvch-btn wvch-btn-icon wvch-composer-send',
            'aria-label': 'Send',
            onclick: function () {
                var body = textarea.value;
                if (body.trim()) { textarea.value = ''; sendMessage(body); }
            },
            html: svg('send')
        });
        el.composer.appendChild(textarea);
        el.composer.appendChild(sendBtn);

        // focus after render (unless mobile — avoid blocking scroll)
        if (window.innerWidth >= 640) setTimeout(function () { textarea.focus(); }, 50);
    }
    function scrollToBottom() {
        var list = el.body && el.body.querySelector('.wvch-thread');
        if (list) list.scrollTop = list.scrollHeight;
    }
    function togglePanel() {
        S.open = !S.open;
        lsSet(LS_OPEN, S.open ? '1' : null);
        if (S.open && !S.booted && !S.booting) {
            render();               // shows loading state
            ensureSession().then(render).catch(function () {
                if (el.body) el.body.innerHTML = '<div class="wvch-loading">Could not connect. Please try again.</div>';
            });
        } else {
            render();
        }
    }

    // ── Styles ─────────────────────────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('wvch-styles')) return;
        var css = [
            "#wvch-root { --wvch-color: #2563eb; --wvch-radius: 12px; --wvch-bubble-radius: 14px; --wvch-chip-radius: 999px; --wvch-shadow: 0 12px 30px rgba(0,0,0,.15); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; direction: ltr; text-align: left; unicode-bidi: isolate; }",
            "#wvch-root, #wvch-root *, #wvch-root *::before, #wvch-root *::after { box-sizing: border-box; direction: ltr; }",
            /* Bubble-style variants — driven by data-bubble on the root */
            "#wvch-root[data-bubble='soft']    { --wvch-radius: 12px; --wvch-bubble-radius: 14px; --wvch-chip-radius: 999px; }",
            "#wvch-root[data-bubble='rounded'] { --wvch-radius: 20px; --wvch-bubble-radius: 20px; --wvch-chip-radius: 999px; }",
            "#wvch-root[data-bubble='square']  { --wvch-radius: 4px;  --wvch-bubble-radius: 4px;  --wvch-chip-radius: 4px; }",

            /* Launcher */
            "#wvch-root .wvch-launcher { position: fixed; bottom: 20px; display: inline-flex; align-items: center; gap: 10px; padding: 0 18px; height: 56px; border-radius: 999px; border: none; background: var(--wvch-color); color: #fff; cursor: pointer; box-shadow: var(--wvch-shadow); font-size: 15px; z-index: 2147483000; transition: transform .15s ease; }",
            "#wvch-root .wvch-launcher:hover { transform: translateY(-2px); }",
            "#wvch-root[data-position='right'] .wvch-launcher { right: 20px; }",
            "#wvch-root[data-position='left']  .wvch-launcher { left: 20px; }",
            "#wvch-root .wvch-launcher-icon { display: inline-flex; align-items: center; }",
            "#wvch-root .wvch-launcher-label { white-space: nowrap; font-weight: 500; }",

            /* Panel */
            "#wvch-root .wvch-panel { position: fixed; bottom: 92px; width: 360px; max-width: calc(100vw - 40px); height: 560px; max-height: calc(100vh - 120px); background: #fff; border-radius: var(--wvch-radius); box-shadow: var(--wvch-shadow); overflow: hidden; display: flex; flex-direction: column; z-index: 2147483000; animation: wvch-in .18s ease-out; }",
            "@keyframes wvch-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }",
            "#wvch-root[data-position='right'] .wvch-panel { right: 20px; }",
            "#wvch-root[data-position='left']  .wvch-panel { left: 20px; }",
            "@media (max-width: 640px) { #wvch-root .wvch-panel { width: calc(100vw - 20px); height: calc(100vh - 100px); right: 10px; left: 10px; bottom: 82px; } #wvch-root[data-position='left'] .wvch-panel { right: 10px; left: 10px; } }",

            /* Header */
            "#wvch-root .wvch-header { background: var(--wvch-color); color: #fff; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }",
            "#wvch-root .wvch-header-titles { min-width: 0; }",
            "#wvch-root .wvch-header-name { font-weight: 600; font-size: 15px; line-height: 1.2; }",
            "#wvch-root .wvch-header-sub  { font-size: 12px; opacity: .85; margin-top: 2px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }",
            "#wvch-root .wvch-header-close { background: transparent; border: none; color: #fff; cursor: pointer; padding: 4px; opacity: .85; display: inline-flex; flex-shrink: 0; }",
            "#wvch-root .wvch-header-close:hover { opacity: 1; }",

            /* Status bar */
            "#wvch-root .wvch-statusbar { padding: 0 12px; }",
            "#wvch-root .wvch-status { display: flex; align-items: center; gap: 8px; padding: 8px 10px; margin-top: 8px; border-radius: 8px; font-size: 12px; background: #f3f4f6; color: #4b5563; }",
            "#wvch-root .wvch-status-dot { width: 8px; height: 8px; border-radius: 50%; }",
            "#wvch-root .wvch-status-pending .wvch-status-dot { background: #f59e0b; animation: wvch-pulse 1.4s ease-in-out infinite; }",
            "#wvch-root .wvch-status-connected .wvch-status-dot { background: #10b981; }",
            "@keyframes wvch-pulse { 0%,100% { opacity: 1; } 50% { opacity: .4; } }",

            /* Body */
            "#wvch-root .wvch-body { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 14px 8px; background: #f9fafb; }",
            "#wvch-root .wvch-loading { padding: 30px 20px; text-align: center; color: #6b7280; font-size: 13px; }",

            /* Welcome */
            "#wvch-root .wvch-welcome-msg { font-size: 14px; color: #111827; line-height: 1.5; margin-bottom: 12px; white-space: pre-wrap; }",
            "#wvch-root .wvch-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }",
            "#wvch-root .wvch-chip { background: #fff; border: 1px solid var(--wvch-color); color: var(--wvch-color); padding: 6px 12px; border-radius: var(--wvch-chip-radius); font-size: 12px; cursor: pointer; font-family: inherit; }",
            "#wvch-root .wvch-chip:hover { background: var(--wvch-color); color: #fff; }",
            "#wvch-root .wvch-human-btn { width: 100%; }",

            /* Prechat form */
            "#wvch-root .wvch-prechat { display: flex; flex-direction: column; gap: 10px; }",
            "#wvch-root .wvch-prechat-title { font-size: 15px; font-weight: 600; color: #111827; }",
            "#wvch-root .wvch-prechat-sub { font-size: 12px; color: #6b7280; margin-bottom: 4px; }",

            /* Thread */
            "#wvch-root .wvch-thread { display: flex; flex-direction: column; gap: 6px; padding-bottom: 6px; }",
            "#wvch-root .wvch-msg { display: flex; }",
            "#wvch-root .wvch-msg-right { justify-content: flex-end; }",
            "#wvch-root .wvch-msg-left  { justify-content: flex-start; }",
            "#wvch-root .wvch-msg-center { justify-content: center; }",
            "#wvch-root .wvch-bubble { max-width: 78%; padding: 8px 12px; border-radius: var(--wvch-bubble-radius); background: #fff; color: #111827; font-size: 13.5px; line-height: 1.45; box-shadow: 0 1px 2px rgba(0,0,0,.04); word-break: break-word; }",
            "#wvch-root .wvch-msg-right .wvch-bubble { background: var(--wvch-color); color: #fff; }",
            "#wvch-root .wvch-bubble-pending { opacity: .6; }",
            "#wvch-root .wvch-bubble-failed { background: #fee2e2; color: #b91c1c; }",
            "#wvch-root .wvch-system { font-size: 11px; color: #6b7280; background: transparent; border: 1px dashed #d1d5db; padding: 3px 10px; border-radius: 999px; }",

            /* Closed */
            "#wvch-root .wvch-closed { text-align: center; padding: 24px 12px; }",
            "#wvch-root .wvch-closed-title { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 6px; }",
            "#wvch-root .wvch-closed-sub { font-size: 13px; color: #6b7280; margin-bottom: 14px; line-height: 1.5; }",

            /* Composer */
            "#wvch-root .wvch-composer { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid #e5e7eb; background: #fff; }",
            "#wvch-root .wvch-input { flex: 1; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 12px; font-family: inherit; font-size: 13.5px; outline: none; resize: none; }",
            "#wvch-root .wvch-input:focus { border-color: var(--wvch-color); }",
            "#wvch-root .wvch-input-composer { max-height: 100px; min-height: 38px; }",

            /* Buttons */
            "#wvch-root .wvch-btn { border: none; border-radius: 8px; padding: 8px 14px; font-family: inherit; font-size: 13.5px; font-weight: 500; cursor: pointer; }",
            "#wvch-root .wvch-btn-primary { background: var(--wvch-color); color: #fff; }",
            "#wvch-root .wvch-btn-primary:hover { filter: brightness(1.05); }",
            "#wvch-root .wvch-btn-primary:disabled { opacity: .6; cursor: not-allowed; }",
            "#wvch-root .wvch-btn-icon { padding: 8px 10px; background: var(--wvch-color); color: #fff; display: inline-flex; align-items: center; }",
            "#wvch-root .wvch-composer-send:hover { filter: brightness(1.05); }",

            /* Powered-by footer */
            "#wvch-root .wvch-branding { display: flex; align-items: center; justify-content: center; gap: 4px; padding: 6px; font-size: 11px; color: #6b7280; background: #fff; border-top: 1px solid #e5e7eb; }",
            "#wvch-root .wvch-branding b { color: #111827; font-weight: 600; }",
            "#wvch-root .wvch-branding-bolt { display: inline-flex; color: var(--wvch-color); }"
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
        if (S.open) {
            ensureSession().then(render).catch(function () { S.open = false; render(); });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // Expose a tiny public API for debugging / programmatic control
    window.WavadeskChat.open  = function () { if (!S.open) togglePanel(); };
    window.WavadeskChat.close = function () { if (S.open)  togglePanel(); };
    window.WavadeskChat.reset = function () { lsSet(LS_TOKEN, null); lsSet(LS_CONV, null); lsSet(LS_OPEN, null); location.reload(); };
})();
