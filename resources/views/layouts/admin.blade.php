<!DOCTYPE html>
<html lang="{{ $currentLocale ?? app()->getLocale() }}" dir="{{ data_get(config('locales.supported', []), app()->getLocale() . '.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.dashboard')) — {{ Auth::user()->tenant->name ?? config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">

    @livewireStyles

    {{-- Alpine.js is provided by Livewire v3 — do not load CDN separately --}}

    {{-- Laravel Echo + Pusher (for Reverb real-time) --}}
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script>
    window._echoConnected = false;
    window._echoStateListeners = [];
    function _notifyEchoState(connected) {
        window._echoConnected = connected;
        window._echoStateListeners.forEach(function(cb) { try { cb(connected); } catch(e) {} });
    }

    window.addEventListener('DOMContentLoaded', function () {
        if (typeof Pusher === 'undefined') { _notifyEchoState(false); return; }
        try {
            var _pusher = new Pusher('{{ config("broadcasting.connections.reverb.key", "local") }}', {
                wsHost:            '{{ config("broadcasting.connections.reverb.options.host", "localhost") }}',
                wsPort:            {{ config("broadcasting.connections.reverb.options.port", 8080) }},
                wssPort:           {{ config("broadcasting.connections.reverb.options.port", 8080) }},
                forceTLS:          {{ config("broadcasting.connections.reverb.options.scheme", "http") === "https" ? "true" : "false" }},
                enabledTransports: ['ws', 'wss'],
                cluster:           'mt1',
                authEndpoint:      '/broadcasting/auth',
                auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } },
            });

            _pusher.connection.bind('state_change', function(states) {
                _notifyEchoState(states.current === 'connected');
            });
            _pusher.connection.bind('error', function() { _notifyEchoState(false); });

            function makeChannelShim(ch) {
                var shim = {
                    listen: function(event, cb) {
                        var name = event.startsWith('.') ? event.slice(1) : 'App\\Events\\' + event;
                        ch.bind(name, cb);
                        return shim;
                    }
                };
                return shim;
            }

            window.Echo = {
                private: function(channel) { return makeChannelShim(_pusher.subscribe('private-' + channel)); },
                channel: function(channel) { return makeChannelShim(_pusher.subscribe(channel)); },
            };
        } catch(e) {
            console.warn('Echo init failed:', e);
            _notifyEchoState(false);
        }
    });
    </script>

    <style>
        /* ============================================================
           DESIGN TOKENS — WhatsApp SaaS (distinct from ERP purple theme)
           Concept: Dark slate sidebar + Emerald green accents + Clean white content
        ============================================================ */
        :root {
            --brand:          #10b981;
            --brand-dark:     #059669;
            --brand-light:    #d1fae5;
            --brand-xlight:   #ecfdf5;

            --sidebar-bg:     #0d1117;
            --sidebar-border: rgba(255,255,255,0.06);
            --sidebar-text:   #8b949e;
            --sidebar-hover:  rgba(16,185,129,0.12);
            --sidebar-active: rgba(16,185,129,0.18);
            --sidebar-active-text: #10b981;
            --sidebar-width:  268px;

            --topbar-height:  60px;
            --topbar-bg:      #ffffff;
            --topbar-border:  #e5e7eb;

            --page-bg:        #f3f4f6;
            --content-bg:     #ffffff;
            --card-bg:        #ffffff;
            --card-border:    #e5e7eb;
            --card-shadow:    0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --card-shadow-md: 0 4px 16px rgba(0,0,0,.08);

            --text-primary:   #111827;
            --text-secondary: #6b7280;
            --text-muted:     #9ca3af;

            --green:   #059669; --green-bg:   #d1fae5;
            --red:     #dc2626; --red-bg:     #fee2e2;
            --orange:  #d97706; --orange-bg:  #fef3c7;
            --blue:    #2563eb; --blue-bg:    #dbeafe;
            --purple:  #7c3aed; --purple-bg:  #ede9fe;
            --teal:    #0891b2; --teal-bg:    #cffafe;
            --gray:    #6b7280; --gray-bg:    #f3f4f6;

            --radius-sm:  6px;
            --radius:     10px;
            --radius-lg:  16px;
            --radius-xl:  24px;
            --radius-full:9999px;

            --transition: all 150ms ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        [x-cloak] { display: none !important; }

        html { font-size: 14px; }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
        }

        html[dir="rtl"] body {
            font-family: 'Cairo', sans-serif;
            line-height: 1.65;
            letter-spacing: 0;
        }

        /* ============================================================
           SIDEBAR
        ============================================================ */
        .sidebar {
            position: fixed;
            left: 0; top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 250ms cubic-bezier(.4,0,.2,1);
        }

        html[dir="rtl"] .sidebar { left: auto; right: 0; }

        .sidebar.collapsed { transform: translateX(-100%); }
        html[dir="rtl"] .sidebar.collapsed { transform: translateX(100%); }

        /* Logo zone */
        .sidebar-logo-zone {
            padding: 24px 20px 20px;
            border-bottom: 1px solid var(--sidebar-border);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .sidebar-logo-icon {
            width: 36px; height: 36px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .sidebar-logo-text { display: flex; flex-direction: column; }
        .sidebar-logo-name { font-size: 15px; font-weight: 700; color: #f0f6fc; letter-spacing: -.3px; }
        .sidebar-logo-sub  { font-size: 11px; color: var(--sidebar-text); letter-spacing: .3px; text-transform: uppercase; }

        /* Tenant badge */
        .sidebar-tenant {
            margin: 14px 12px 0;
            background: rgba(16,185,129,.08);
            border: 1px solid rgba(16,185,129,.2);
            border-radius: var(--radius);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-tenant-avatar {
            width: 30px; height: 30px;
            background: var(--brand);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #fff;
            flex-shrink: 0;
        }

        .sidebar-tenant-name {
            font-size: 12px; font-weight: 600; color: #cdd9e5;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        .sidebar-tenant-plan {
            font-size: 10px; color: var(--brand); font-weight: 500;
        }

        /* Nav */
        .sidebar-nav {
            flex: 1;
            padding: 12px 0;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(139,148,158,.45) transparent;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(139,148,158,.35);
            border-radius: 999px;
            border: 2px solid transparent;
            background-clip: content-box;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(16,185,129,.35);
            background-clip: content-box;
        }

        .sidebar-section-label {
            font-size: 10px; font-weight: 600; color: rgba(139,148,158,.5);
            letter-spacing: 1px; text-transform: uppercase;
            padding: 16px 20px 6px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            margin: 1px 8px;
            border-radius: var(--radius);
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .sidebar-nav a i {
            font-size: 16px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-nav a:hover {
            background: var(--sidebar-hover);
            color: #cdd9e5;
        }

        .sidebar-nav a.active {
            background: var(--sidebar-active);
            color: var(--sidebar-active-text);
            font-weight: 600;
            box-shadow: inset 3px 0 0 var(--sidebar-active-text);
        }

        .sidebar-nav a.active,
        .sidebar-nav a:focus-visible {
            scroll-margin-block: 140px;
        }

        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            inset-block: 8px;
            left: 0;
            width: 3px;
            border-radius: 0 999px 999px 0;
            background: var(--sidebar-active-text);
        }

        .sidebar-nav a:focus-visible {
            outline: 2px solid rgba(16,185,129,.55);
            outline-offset: 2px;
        }

        html[dir="rtl"] .sidebar-nav a.active::before {
            left: auto;
            right: 0;
            border-radius: 999px 0 0 999px;
        }

        .sidebar-nav a .nav-badge {
            margin-left: auto;
            background: var(--brand);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-nav a .nav-badge.red { background: var(--red); }

        /* Status dot */
        .nav-status-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            margin-left: auto;
            flex-shrink: 0;
        }

        .nav-status-dot.green  { background: var(--green); box-shadow: 0 0 0 2px rgba(5,150,105,.3); }
        .nav-status-dot.yellow { background: var(--orange); }
        .nav-status-dot.red    { background: var(--red); }

        /* Footer */
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid var(--sidebar-border);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .sidebar-user:hover { background: var(--sidebar-hover); }

        .sidebar-user-avatar {
            width: 32px; height: 32px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #fff;
            flex-shrink: 0;
            overflow: hidden;
        }

        .sidebar-user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .sidebar-user-info { flex: 1; overflow: hidden; }
        .sidebar-user-name { font-size: 12.5px; font-weight: 600; color: #cdd9e5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: 11px; color: var(--sidebar-text); text-transform: capitalize; }

        .sidebar-user-actions { display: flex; gap: 4px; }
        .sidebar-user-btn {
            width: 26px; height: 26px;
            background: transparent;
            border: none;
            color: var(--sidebar-text);
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            transition: var(--transition);
        }
        .sidebar-user-btn:hover { background: rgba(255,255,255,.08); color: #cdd9e5; }

        /* ============================================================
           MAIN LAYOUT
        ============================================================ */
        .main-wrap {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin 250ms cubic-bezier(.4,0,.2,1);
        }

        html[dir="rtl"] .main-wrap { margin-left: 0; margin-right: var(--sidebar-width); }

        .main-wrap.sidebar-collapsed { margin-left: 0; }
        html[dir="rtl"] .main-wrap.sidebar-collapsed { margin-right: 0; }

        /* ============================================================
           TOPBAR
        ============================================================ */
        .topbar {
            height: var(--topbar-height);
            background: var(--topbar-bg);
            border-bottom: 1px solid var(--topbar-border);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-toggle {
            width: 36px; height: 36px;
            background: transparent;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary);
            font-size: 18px;
            transition: var(--transition);
            flex-shrink: 0;
        }
        .topbar-toggle:hover { background: var(--page-bg); color: var(--text-primary); }

        .topbar-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
        }

        .topbar-breadcrumb a,
        .topbar-breadcrumb span {
            font-size: 13px;
            color: var(--text-secondary);
            text-decoration: none;
        }

        .topbar-breadcrumb a:hover { color: var(--brand); }

        .topbar-breadcrumb .sep { color: var(--text-muted); }

        .topbar-breadcrumb .current {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }

        html[dir="rtl"] .topbar-right { margin-left: 0; margin-right: auto; }

        .topbar-btn {
            width: 36px; height: 36px;
            background: transparent;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary);
            font-size: 16px;
            transition: var(--transition);
            position: relative;
            text-decoration: none;
        }
        .topbar-btn:hover { background: var(--page-bg); color: var(--brand); border-color: var(--brand-light); }

        .topbar-notif-dot {
            position: absolute;
            top: 6px; right: 6px;
            width: 7px; height: 7px;
            background: var(--red);
            border-radius: 50%;
            border: 1.5px solid #fff;
        }


        /* Notification badge on bell */
        .notif-badge {
            position: absolute;
            top: 4px; right: 4px;
            min-width: 16px; height: 16px;
            background: var(--red);
            border-radius: var(--radius-full);
            border: 1.5px solid #fff;
            font-size: 9px; font-weight: 700; color: #fff;
            display: flex; align-items: center; justify-content: center;
            line-height: 1;
            padding: 0 3px;
        }

        /* Notification dropdown panel */
        .notif-wrap { position: relative; }
        .notif-panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 340px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg, 10px);
            box-shadow: 0 8px 32px rgba(0,0,0,.12);
            z-index: 1100;
            overflow: hidden;
            display: none;
        }
        .notif-panel.open { display: block; }
        html[dir="rtl"] .notif-panel { right: auto; left: 0; }

        .notif-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--card-border);
        }
        .notif-panel-header-title {
            font-size: .875rem; font-weight: 600; color: var(--text-primary);
        }
        .notif-mark-all {
            font-size: .75rem; color: var(--brand);
            background: none; border: none; cursor: pointer; padding: 0;
        }
        .notif-mark-all:hover { text-decoration: underline; }

        .notif-list { max-height: 340px; overflow-y: auto; }
        .notif-item {
            display: flex; gap: .75rem; align-items: flex-start;
            padding: .875rem 1rem;
            border-bottom: 1px solid var(--card-border);
            cursor: default;
            transition: background .15s;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread { background: rgba(var(--brand-rgb, 16,185,129), .04); }
        .notif-item:hover { background: var(--page-bg); }

        .notif-item-icon {
            width: 34px; height: 34px; border-radius: var(--radius-full);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }
        .notif-item-icon.manual  { background: #ecfdf5; color: #059669; }
        .notif-item-icon.renewal { background: #fffbeb; color: #d97706; }
        .notif-item-icon.system  { background: #eff6ff; color: #3b82f6; }

        .notif-item-body { flex: 1; min-width: 0; }
        .notif-item-title {
            font-size: .8125rem; font-weight: 600; color: var(--text-primary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .notif-item-body-text {
            font-size: .75rem; color: var(--text-secondary);
            margin-top: 2px;
            display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden;
        }
        .notif-item-time {
            font-size: .6875rem; color: var(--text-muted);
            margin-top: 4px; white-space: nowrap;
        }
        .notif-unread-dot {
            width: 7px; height: 7px;
            background: var(--brand); border-radius: 50%;
            flex-shrink: 0; margin-top: 6px;
        }

        .notif-panel-footer {
            padding: .625rem 1rem;
            border-top: 1px solid var(--card-border);
            text-align: center;
        }
        .notif-panel-footer a {
            font-size: .8125rem; color: var(--brand);
            text-decoration: none;
        }
        .notif-panel-footer a:hover { text-decoration: underline; }

        .notif-empty {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--text-muted);
            font-size: .875rem;
        }
        .notif-empty i { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .5; }

        /* Impersonation banner */
        .impersonation-banner {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-bottom: 1px solid #f59e0b;
            padding: 8px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #92400e;
        }

        .impersonation-banner i { color: #f59e0b; font-size: 16px; }
        .impersonation-banner a { color: #b45309; font-weight: 600; text-decoration: underline; }

        /* ============================================================
           PAGE CONTENT
        ============================================================ */
        .page-content {
            flex: 1;
            padding: 24px 28px;
            width: 100%;
            min-width: 0;
        }

        /* ============================================================
           PAGE HEADER
        ============================================================ */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .page-header-left {}

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -.4px;
            line-height: 1.2;
        }

        .page-subtitle {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        /* ============================================================
           BUTTONS
        ============================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: var(--radius);
            font-size: 13.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
            line-height: 1;
        }

        .btn i { font-size: 15px; }

        .btn-primary {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }
        .btn-primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,.35); }
        .btn-primary:active { transform: translateY(0); box-shadow: none; }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border-color: var(--card-border);
        }
        .btn-outline:hover { background: var(--page-bg); color: var(--text-primary); border-color: #d1d5db; }

        .btn-danger {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
        }
        .btn-danger:hover { background: #b91c1c; border-color: #b91c1c; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(220,38,38,.3); }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border-color: transparent;
        }
        .btn-ghost:hover { background: var(--page-bg); color: var(--text-primary); }

        .btn-sm { padding: 5px 12px; font-size: 12.5px; }
        .btn-sm i { font-size: 13px; }

        .btn-icon {
            padding: 8px;
            border-radius: var(--radius);
        }

        /* Loading state */
        .btn.loading {
            pointer-events: none;
            opacity: .72;
        }

        .btn-spinner {
            width: 14px; height: 14px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
            flex-shrink: 0;
        }

        /* ============================================================
           CARDS
        ============================================================ */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid var(--card-border);
        }

        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: var(--brand);
            font-size: 17px;
        }

        .card-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .card-body { padding: 20px; }

        /* ============================================================
           STAT CARDS
        ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover { box-shadow: var(--card-shadow-md); transform: translateY(-2px); }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--brand);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .stat-card.red::before   { background: var(--red); }
        .stat-card.orange::before{ background: var(--orange); }
        .stat-card.blue::before  { background: var(--blue); }
        .stat-card.purple::before{ background: var(--purple); }

        .stat-card-icon {
            width: 44px; height: 44px;
            background: var(--brand-xlight);
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
        }
        .stat-card-icon i { font-size: 20px; color: var(--brand); }
        .stat-card.red    .stat-card-icon { background: var(--red-bg); }
        .stat-card.red    .stat-card-icon i { color: var(--red); }
        .stat-card.orange .stat-card-icon { background: var(--orange-bg); }
        .stat-card.orange .stat-card-icon i { color: var(--orange); }
        .stat-card.blue   .stat-card-icon { background: var(--blue-bg); }
        .stat-card.blue   .stat-card-icon i { color: var(--blue); }
        .stat-card.purple .stat-card-icon { background: var(--purple-bg); }
        .stat-card.purple .stat-card-icon i { color: var(--purple); }

        .stat-card-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -1px;
            line-height: 1;
        }

        .stat-card-label { font-size: 12.5px; color: var(--text-secondary); font-weight: 500; }

        .stat-card-trend {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 11.5px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: var(--radius-full);
        }

        .stat-card-trend.up   { background: var(--green-bg); color: var(--green); }
        .stat-card-trend.down { background: var(--red-bg); color: var(--red); }
        .stat-card-trend.neutral { background: var(--gray-bg); color: var(--gray); }

        /* ============================================================
           DATA TABLE
        ============================================================ */
        .table-wrap {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .table-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--card-border);
            flex-wrap: wrap;
        }

        .filter-input {
            height: 36px;
            padding: 0 12px 0 36px;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            font-size: 13px;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--page-bg);
            outline: none;
            transition: var(--transition);
            min-width: 220px;
        }
        .filter-input:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px rgba(16,185,129,.1); }

        .filter-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .filter-input-wrap i {
            position: absolute;
            left: 10px;
            color: var(--text-muted);
            font-size: 15px;
            pointer-events: none;
        }

        /* Toolbar native select (fallback style + class used before ss-wrap init) */
        .toolbar-select {
            height: 36px; padding: 0 12px;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            font-size: 13px; font-family: inherit;
            background: var(--page-bg); color: var(--text-primary);
            outline: none; cursor: pointer;
            min-width: 120px;
        }
        .toolbar-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(16,185,129,.1); }

        /* Toolbar date inputs */
        .table-toolbar input[type="date"] {
            height: 36px; padding: 0 10px;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            font-size: 13px; font-family: inherit;
            background: var(--page-bg); color: var(--text-primary);
            outline: none; transition: var(--transition);
        }
        .table-toolbar input[type="date"]:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px rgba(16,185,129,.1); }

        /* ss-wrap inside .table-toolbar — height/border match the 36px toolbar row */
        .table-toolbar .ss-wrap { min-width: 130px; }
        .table-toolbar .ss-input { height: 36px; line-height: 36px; border-width: 1px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead tr {
            background: #f9fafb;
            border-bottom: 1px solid var(--card-border);
        }

        .data-table th {
            padding: 11px 16px;
            text-align: left;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .6px;
            white-space: nowrap;
        }

        html[dir="rtl"] .data-table th { text-align: right; }

        .data-table th.sortable {
            cursor: pointer;
            user-select: none;
        }
        .data-table th.sortable:hover { color: var(--brand); }

        .data-table td {
            padding: 13px 16px;
            font-size: 13.5px;
            color: var(--text-primary);
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .data-table tr:last-child td { border-bottom: none; }

        .data-table tbody tr {
            transition: var(--transition);
        }
        .data-table tbody tr:hover { background: #f9fafb; }

        .data-table td .text-muted { font-size: 12px; color: var(--text-muted); }

        /* Styled checkboxes — table bulk select */
        input[type="checkbox"].row-cb,
        input[type="checkbox"].header-cb {
            appearance: none; -webkit-appearance: none;
            width: 16px; height: 16px;
            border: 1.5px solid var(--card-border);
            border-radius: 4px;
            background: var(--card-bg);
            cursor: pointer;
            vertical-align: middle;
            flex-shrink: 0;
            position: relative;
            transition: border-color .15s, background .15s;
            display: inline-block;
        }
        input[type="checkbox"].row-cb:hover:not(:checked),
        input[type="checkbox"].header-cb:hover:not(:checked) { border-color: var(--brand); }
        input[type="checkbox"].row-cb:checked,
        input[type="checkbox"].header-cb:checked {
            background: var(--brand);
            border-color: var(--brand);
        }
        input[type="checkbox"].row-cb:checked::after,
        input[type="checkbox"].header-cb:checked::after {
            content: ''; display: block;
            width: 4px; height: 7px;
            border: 2px solid #fff; border-top: none; border-left: none;
            position: absolute; top: 1px; left: 4px;
            transform: rotate(45deg);
        }
        input[type="checkbox"].row-cb:indeterminate,
        input[type="checkbox"].header-cb:indeterminate {
            background: var(--brand);
            border-color: var(--brand);
        }
        input[type="checkbox"].row-cb:indeterminate::after,
        input[type="checkbox"].header-cb:indeterminate::after {
            content: ''; display: block;
            width: 8px; height: 2px;
            background: #fff;
            position: absolute; top: 5px; left: 3px;
            border-radius: 2px;
        }

        /* ============================================================
           BADGES
        ============================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: .2px;
        }

        .badge i { font-size: 11px; }

        .badge-green  { background: var(--green-bg);  color: var(--green); }
        .badge-red    { background: var(--red-bg);    color: var(--red); }
        .badge-orange { background: var(--orange-bg); color: var(--orange); }
        .badge-blue   { background: var(--blue-bg);   color: var(--blue); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-teal   { background: var(--teal-bg);   color: var(--teal); }
        .badge-gray   { background: var(--gray-bg);   color: var(--gray); }
        .badge-brand  { background: var(--brand-light); color: var(--brand-dark); }

        /* ============================================================
           ACTION BUTTONS (table rows)
        ============================================================ */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px; height: 30px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            background: transparent;
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .action-btn:hover { background: var(--brand-xlight); color: var(--brand); border-color: var(--brand-light); }
        .action-btn.danger:hover { background: var(--red-bg); color: var(--red); border-color: #fca5a5; }

        /* ============================================================
           FORMS
        ============================================================ */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-grid.cols-1 { grid-template-columns: 1fr; }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-label .req { color: var(--red); margin-left: 2px; }

        .form-control {
            height: 40px;
            padding: 0 12px;
            border: 1.5px solid var(--card-border);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: inherit;
            color: var(--text-primary);
            background: #fff;
            outline: none;
            transition: var(--transition);
            width: 100%;
        }
        .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
        .form-control.error { border-color: var(--red); box-shadow: 0 0 0 3px rgba(220,38,38,.1); }

        textarea.form-control { height: auto; padding: 10px 12px; resize: vertical; min-height: 90px; }

        select.form-control { cursor: pointer; }

        .form-hint { font-size: 12px; color: var(--text-muted); }
        .form-error { font-size: 12px; color: var(--red); display: flex; align-items: center; gap: 4px; }

        /* Toggle */
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .toggle-label input[type="checkbox"] {
            appearance: none;
            width: 40px; height: 22px;
            background: #d1d5db;
            border-radius: var(--radius-full);
            position: relative;
            transition: background 150ms;
            flex-shrink: 0;
            cursor: pointer;
        }

        .toggle-label input[type="checkbox"]::after {
            content: '';
            position: absolute;
            width: 18px; height: 18px;
            background: #fff;
            border-radius: 50%;
            top: 2px; left: 2px;
            transition: transform 150ms;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }

        .toggle-label input[type="checkbox"]:checked {
            background: var(--brand);
        }

        .toggle-label input[type="checkbox"]:checked::after {
            transform: translateX(18px);
        }

        .toggle-text { font-size: 13.5px; font-weight: 500; color: var(--text-primary); }

        /* ============================================================
           PAGINATION
        ============================================================ */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-top: 1px solid var(--card-border);
        }

        .pagination-info { font-size: 13px; color: var(--text-muted); }

        .pagination-btns {
            display: flex;
            gap: 4px;
        }

        .page-btn {
            min-width: 32px; height: 32px;
            padding: 0 8px;
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            background: #fff;
            font-size: 12.5px;
            font-family: inherit;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        .page-btn:hover { background: var(--page-bg); color: var(--brand); border-color: var(--brand-light); }
        .page-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); }

        /* ============================================================
           MODALS
        ============================================================ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 200ms ease;
        }

        .modal-overlay.show {
            opacity: 1;
            pointer-events: all;
        }

        .modal-box {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            box-shadow: 0 20px 60px rgba(0,0,0,.15);
            width: 100%;
            max-width: 480px;
            padding: 28px;
            text-align: center;
            transform: translateY(10px) scale(.97);
            transition: transform 200ms ease;
        }

        .modal-overlay.show .modal-box {
            transform: translateY(0) scale(1);
        }

        /* Form-style modal (modal-card pattern) */
        .modal-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            box-shadow: 0 20px 60px rgba(0,0,0,.15);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.125rem 1.5rem;
            border-bottom: 1px solid var(--card-border);
        }
        .modal-header h3 {
            font-size: .9375rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1.25rem;
            padding: .25rem;
            display: flex;
            align-items: center;
            border-radius: .5rem;
            transition: background .1s, color .1s;
            line-height: 1;
        }
        .modal-close:hover { background: var(--page-bg); color: var(--text-primary); }
        .modal-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .5rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--card-border);
        }

        .modal-icon {
            width: 56px; height: 56px;
            border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
        }

        .modal-icon.danger { background: var(--red-bg); color: var(--red); }
        .modal-icon.warning { background: var(--orange-bg); color: var(--orange); }
        .modal-icon.info { background: var(--brand-xlight); color: var(--brand); }

        .modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
        .modal-box p { font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 22px;
            justify-content: center;
        }

        .modal-actions .btn { flex: 1; justify-content: center; }

        /* ============================================================
           TOAST
        ============================================================ */
        .toast-stack {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 99999;
        }

        html[dir="rtl"] .toast-stack { right: auto; left: 24px; }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            background: #1e2530;
            border-radius: var(--radius-lg);
            border-left: 3px solid var(--brand);
            box-shadow: 0 8px 24px rgba(0,0,0,.2);
            min-width: 280px;
            max-width: 380px;
            animation: toastIn .25s ease;
        }

        .toast.error { border-left-color: var(--red); }
        .toast.warning { border-left-color: var(--orange); }

        .toast-icon {
            width: 28px; height: 28px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .toast .toast-icon { background: rgba(16,185,129,.2); color: var(--brand); }
        .toast.error .toast-icon { background: rgba(220,38,38,.2); color: var(--red); }
        .toast.warning .toast-icon { background: rgba(217,119,6,.2); color: var(--orange); }

        .toast-text { flex: 1; }
        .toast-title { font-size: 13px; font-weight: 600; color: #f0f6fc; }
        .toast-msg { font-size: 12px; color: #8b949e; margin-top: 2px; }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ============================================================
           STATUS INDICATORS (instances)
        ============================================================ */
        .status-dot {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 600;
        }

        .status-dot::before {
            content: '';
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .status-dot.green::before  { background: var(--green); box-shadow: 0 0 0 2px rgba(5,150,105,.2); animation: pulse 2s infinite; }
        .status-dot.yellow::before { background: var(--orange); }
        .status-dot.red::before    { background: var(--red); }
        .status-dot.gray::before   { background: var(--gray); }

        .status-dot.green  { color: var(--green); }
        .status-dot.yellow { color: var(--orange); }
        .status-dot.red    { color: var(--red); }
        .status-dot.gray   { color: var(--gray); }

        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 2px rgba(5,150,105,.2); }
            50%      { box-shadow: 0 0 0 4px rgba(5,150,105,.1); }
        }

        /* ============================================================
           EMPTY STATE
        ============================================================ */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            text-align: center;
        }

        .empty-state-icon {
            width: 64px; height: 64px;
            background: var(--page-bg);
            border-radius: var(--radius-xl);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .empty-state h4 { font-size: 15px; font-weight: 700; margin-bottom: 6px; }
        .empty-state p  { font-size: 13px; color: var(--text-secondary); max-width: 280px; margin-bottom: 20px; }

        /* ============================================================
           SEARCHABLE SELECT (ss-wrap)
        ============================================================ */
        .ss-wrap { position: relative; }
        .ss-input {
            width: 100%; padding: 0 34px 0 12px;
            border: 1px solid #d8dcef;
            border-radius: 8px; font-size: 13px; font-family: inherit;
            background: #f8fafc; color: #1f2937;
            outline: none; transition: border-color .18s, box-shadow .18s; cursor: pointer;
            height: 38px; line-height: 38px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: block;
        }
        .ss-input:focus, .ss-wrap.open .ss-input { border-color: #b8bfe2; box-shadow: 0 0 0 2px rgba(184,191,226,.22); }
        .ss-wrap.error .ss-input { border-color: var(--red); box-shadow: 0 0 0 2px rgba(220,38,38,.12); }
        .ss-chevron {
            position: absolute; right: 9px; top: 50%; transform: translateY(-50%);
            pointer-events: none; color: var(--text-muted); font-size: 16px; transition: transform .2s;
        }
        .ss-wrap.open .ss-chevron { transform: translateY(-50%) rotate(180deg); }
        .ss-dropdown {
            position: absolute; left: 0; top: calc(100% + 4px); width: 100%; min-width: 180px;
            background: #ffffff; border: 1px solid #d8dcef;
            border-radius: 8px; box-shadow: 0 12px 28px rgba(15,23,42,.12);
            z-index: 9999; display: none; overflow: hidden;
        }
        .ss-wrap.open-up .ss-dropdown {
            top: auto;
            bottom: calc(100% + 4px);
        }
        .ss-wrap.open .ss-dropdown { display: block; }
        .ss-search-row { padding: 8px 10px; border-bottom: 1px solid #e5e7f2; background: #f8fafc; }
        .ss-search-inner {
            display: flex; align-items: center; gap: 6px;
            border: 1px solid #d8dcef;
            border-radius: 7px; padding: 6px 9px; background: #ffffff;
        }
        .ss-search-inner i { color: #9ca3af; font-size: 13px; flex-shrink: 0; }
        .ss-search-inner input {
            border: none; outline: none; font-size: 13px;
            font-family: inherit; background: transparent; flex: 1;
            color: #1f2937; min-width: 0;
        }
        .ss-list { max-height: 160px; overflow-y: auto; overscroll-behavior: contain; }
        .ss-item {
            padding: 10px 13px; font-size: 13px; cursor: pointer;
            border-bottom: 1px solid #eceff8; color: #1f2937;
            display: flex; align-items: center; gap: 8px; transition: background .12s;
        }
        .ss-item:last-child { border-bottom: none; }
        .ss-item:hover, .ss-item.active { background: #f4f6ff; color: #111827; }
        .ss-item.ss-selected { background: #eef2ff; color: #1f2937; font-weight: 500; }
        .ss-item.ss-selected::after { content: '\EB80'; font-family: "remixicon"; margin-left: auto; font-size: 14px; color: #6366f1; font-weight: 400; }
        .ss-empty { padding: 12px 14px; font-size: 13px; color: var(--text-muted); text-align: center; }

        /* ============================================================
           LOADING SPINNER
        ============================================================ */
        .spinner-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .spinner {
            width: 32px; height: 32px;
            border: 3px solid var(--card-border);
            border-top-color: var(--brand);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ============================================================
           PROGRESS BAR
        ============================================================ */
        .progress-bar {
            height: 6px;
            background: var(--card-border);
            border-radius: var(--radius-full);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--brand);
            border-radius: var(--radius-full);
            transition: width 500ms ease;
        }

        .progress-fill.warning { background: var(--orange); }
        .progress-fill.danger  { background: var(--red); }

        /* ============================================================
           TABS
        ============================================================ */
        .tab-nav {
            display: flex;
            gap: 2px;
            background: var(--page-bg);
            padding: 4px;
            border-radius: var(--radius);
            border: 1px solid var(--card-border);
            width: fit-content;
        }

        .tab-btn {
            padding: 6px 16px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 500;
            font-family: inherit;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .tab-btn.active {
            background: var(--card-bg);
            color: var(--brand);
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ============================================================
           BULK BAR
        ============================================================ */
        .bulk-bar {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: var(--sidebar-bg);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: var(--radius-xl);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,.3);
            z-index: 500;
            transition: transform 300ms cubic-bezier(.4,0,.2,1);
            min-width: 320px;
        }

        .bulk-bar.visible {
            transform: translateX(-50%) translateY(0);
        }

        .bulk-count {
            font-size: 13px;
            font-weight: 600;
            color: #cdd9e5;
        }

        .bulk-sep { color: rgba(255,255,255,.15); }

        .bulk-actions { display: flex; gap: 6px; }

        .bulk-close {
            width: 28px; height: 28px;
            background: rgba(255,255,255,.08);
            border: none;
            border-radius: var(--radius-sm);
            color: #8b949e;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            margin-left: auto;
            transition: var(--transition);
        }

        .bulk-close:hover { background: rgba(255,255,255,.15); color: #cdd9e5; }

        /* ============================================================
           QR CODE MODAL
        ============================================================ */
        .qr-container {
            background: #fff;
            border: 2px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 16px auto;
        }

        /* ============================================================
           AI MODE SELECTOR
        ============================================================ */
        .ai-mode-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 8px;
        }

        .ai-mode-card {
            border: 2px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 16px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .ai-mode-card:hover { border-color: var(--brand-light); background: var(--brand-xlight); }

        .ai-mode-card.selected {
            border-color: var(--brand);
            background: var(--brand-xlight);
        }

        .ai-mode-card i {
            font-size: 24px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 8px;
        }

        .ai-mode-card.selected i { color: var(--brand); }

        .ai-mode-card h4 { font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .ai-mode-card p  { font-size: 11.5px; color: var(--text-secondary); }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            html[dir="rtl"] .sidebar { transform: translateX(100%); }
            .sidebar.open { transform: translateX(0); }
            .main-wrap { margin-left: 0 !important; }
            html[dir="rtl"] .main-wrap { margin-right: 0 !important; }
            .page-content { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; gap: 12px; }
            .page-header-actions { width: 100%; }
            .ai-mode-grid { grid-template-columns: 1fr; }
        }
    </style>

    @stack('styles')
</head>
<body>

    {{-- SIDEBAR --}}
    <aside class="sidebar" id="sidebar">
        @php
            $panelPrefix = auth()->user()->routeNamePrefix();
            $navActive = function (array $patterns) {
                foreach ($patterns as $pattern) {
                    if (request()->routeIs($pattern)) {
                        return 'active';
                    }
                }
                return '';
            };
        @endphp
        <div class="sidebar-logo-zone">
            <a href="{{ route($panelPrefix . '.dashboard') }}" class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <img src="{{ asset('images/wavadesk-icon-teal.svg') }}" alt="wavadesk" width="36" height="36">
                </div>
                <div class="sidebar-logo-text">
                    <span class="sidebar-logo-name">wavadesk</span>
                    <span class="sidebar-logo-sub">Platform</span>
                </div>
            </a>
        </div>

        @auth
        <div class="sidebar-tenant">
            <div class="sidebar-tenant-avatar">
                {{ strtoupper(substr(Auth::user()->isSuperAdmin() ? __('ui.sidebar.platform_short') : (Auth::user()->tenant->name ?? __('ui.sidebar.tenant_short')), 0, 1)) }}
            </div>
            <div style="overflow:hidden;">
                <div class="sidebar-tenant-name">{{ Auth::user()->isSuperAdmin() ? __('ui.sidebar.platform_owner_name') : (Auth::user()->tenant->name ?? __('ui.sidebar.tenant_short')) }}</div>
                <div class="sidebar-tenant-plan">{{ Auth::user()->isSuperAdmin() ? __('ui.sidebar.global_scope') : ucfirst(Auth::user()->tenant->subscription_status ?? 'trial') }}</div>
            </div>
        </div>
        @endauth

        <nav class="sidebar-nav">
            @php
                $sp = fn(string $p) => Auth::user()->hasSuperAdminPermission($p);
            @endphp
            @if(Auth::user()->isSuperAdmin())
                <div class="sidebar-section-label">{{ __('ui.sidebar.platform_owner') }}</div>
                <div style="font-size:.6875rem;color:var(--sidebar-text);opacity:.85;line-height:1.35;padding:.125rem .75rem .625rem">
                    {{ __('ui.sidebar.platform_owner_desc') }}
                </div>
            {{-- @elseif(Auth::user()->isAdmin())
                <div class="sidebar-section-label">{{ __('ui.sidebar.tenant_admin') }}</div>
                <div style="font-size:.6875rem;color:var(--sidebar-text);opacity:.85;line-height:1.35;padding:.125rem .75rem .625rem">
                    {{ __('ui.sidebar.tenant_admin_desc') }}
                </div> --}}
            @elseif(Auth::user()->isSupervisor())
                <div class="sidebar-section-label">{{ __('ui.sidebar.supervisor') }}</div>
                <div style="font-size:.6875rem;color:var(--sidebar-text);opacity:.85;line-height:1.35;padding:.125rem .75rem .625rem">
                    {{ __('ui.sidebar.supervisor_desc') }}
                </div>
            @elseif(Auth::user()->isAgent())
                <div class="sidebar-section-label">{{ __('ui.sidebar.support_agent') }}</div>
                <div style="font-size:.6875rem;color:var(--sidebar-text);opacity:.85;line-height:1.35;padding:.125rem .75rem .625rem">
                    {{ __('ui.sidebar.support_agent_desc') }}
                </div>
            @else
                <div class="sidebar-section-label">{{ __('ui.sidebar.workspace') }}</div>
            @endif

            <a href="{{ route($panelPrefix . '.dashboard') }}" class="{{ $navActive([$panelPrefix . '.dashboard']) }}">
                <i class="ri-dashboard-3-line"></i>
                <span>{{ __('ui.dashboard') }}</span>
            </a>

            @if(Auth::user()->isSuperAdmin())
            @if($sp('platform_tenants'))
            <a href="{{ route($panelPrefix . '.platform.tenants') }}" class="{{ $navActive([$panelPrefix . '.platform.tenants*']) }}">
                <i class="ri-building-2-line"></i>
                <span>{{ __('ui.sidebar.tenants') }}</span>
            </a>
            @endif

            @if($sp('platform_plans'))
            <a href="{{ route($panelPrefix . '.platform.plans') }}" class="{{ $navActive([$panelPrefix . '.platform.plans*']) }}">
                <i class="ri-price-tag-3-line"></i>
                <span>{{ __('ui.sidebar.subscription_plans') }}</span>
            </a>
            @endif

            @if($sp('platform_system_health'))
            <a href="{{ route($panelPrefix . '.platform.system-health') }}" class="{{ $navActive([$panelPrefix . '.platform.system-health*']) }}">
                <i class="ri-pulse-line"></i>
                <span>{{ __('ui.sidebar.system_health') }}</span>
            </a>
            @endif

            @if($sp('platform_legal_pages'))
            <a href="{{ route($panelPrefix . '.platform.legal-pages.index') }}" class="{{ $navActive([$panelPrefix . '.platform.legal-pages*']) }}">
                <i class="ri-file-shield-2-line"></i>
                <span>{{ __('ui.sidebar.legal_pages') }}</span>
            </a>
            @endif

            @if(Auth::user()->isMasterSuperAdmin())
            <a href="{{ route($panelPrefix . '.super-admins.index') }}" class="{{ $navActive([$panelPrefix . '.super-admins.*']) }}">
                <i class="ri-shield-user-line"></i>
                <span>{{ __('ui.sidebar.super_admins') }}</span>
            </a>
            @endif
            @endif

            @if(!Auth::user()->isSuperAdmin() || $sp('conversations'))
            <a href="{{ route($panelPrefix . '.conversations.index') }}" class="{{ $navActive([$panelPrefix . '.conversations.*']) }}">
                <i class="ri-message-3-line"></i>
                <span>{{ __('ui.sidebar.conversations') }}</span>
                @php
                    $poolCountQuery = \App\Models\Conversation::pool();
                    if(Auth::user()->isSupervisor() || Auth::user()->isAgent()) {
                        $poolCountQuery->whereIn('team_id', Auth::user()->teams->pluck('id'));
                    }
                    $poolCount = $poolCountQuery->count();
                @endphp
                @if($poolCount > 0)
                    <span class="nav-badge">{{ $poolCount }}</span>
                @endif
            </a>

            @if(!Auth::user()->isSuperAdmin() || $sp('customers'))
            <a href="{{ route($panelPrefix . '.customers.index') }}" class="{{ $navActive([$panelPrefix . '.customers.*']) }}">
                <i class="ri-contacts-line"></i>
                <span>{{ __('ui.sidebar.customers') }}</span>
            </a>
            @endif
            @endif {{-- end conversations/customers block --}}

            @if(Auth::user()->isSupervisor())
            <a href="{{ route($panelPrefix . '.teams.index') }}" class="{{ $navActive([$panelPrefix . '.teams.*']) }}">
                <i class="ri-team-line"></i>
                <span>{{ __('ui.sidebar.teams') }}</span>
            </a>
            @endif

            @if(Auth::user()->hasAnyRole(['admin', 'super_admin']))
                @php $showMgmt = !Auth::user()->isSuperAdmin() || $sp('instances') || $sp('teams') || $sp('users'); @endphp
                @if($showMgmt)
                <div class="sidebar-section-label">{{ __('ui.sidebar.management') }}</div>
                @endif

            @if(!Auth::user()->isSuperAdmin() || $sp('instances'))
            <a href="{{ route($panelPrefix . '.instances.index') }}" class="{{ $navActive([$panelPrefix . '.instances.*']) }}">
                <i class="ri-smartphone-line"></i>
                <span>{{ __('ui.sidebar.whatsapp_instances') }}</span>
            </a>
            @endif

            @if(!Auth::user()->isSuperAdmin() || $sp('teams'))
            <a href="{{ route($panelPrefix . '.teams.index') }}" class="{{ $navActive([$panelPrefix . '.teams.*']) }}">
                <i class="ri-team-line"></i>
                <span>{{ __('ui.sidebar.teams') }}</span>
            </a>
            @endif

            @if(!Auth::user()->isSuperAdmin() || $sp('users'))
            <a href="{{ route($panelPrefix . '.users.index') }}" class="{{ $navActive([$panelPrefix . '.users.*']) }}">
                <i class="ri-user-settings-line"></i>
                <span>{{ __('ui.sidebar.agents_users') }}</span>
            </a>
            @endif

            @if(Auth::user()->isAdmin())
                <div class="sidebar-section-label">{{ __('ui.sidebar.ai_knowledge') }}</div>

                <a href="{{ route($panelPrefix . '.knowledge.index') }}" class="{{ $navActive([$panelPrefix . '.knowledge.*']) }}">
                    <i class="ri-book-2-line"></i>
                    <span>{{ __('ui.sidebar.knowledge_base') }}</span>
                </a>

                <a href="{{ route($panelPrefix . '.ai-settings.index') }}" class="{{ $navActive([$panelPrefix . '.ai-settings.*']) }}">
                    <i class="ri-sparkling-2-line"></i>
                    <span>{{ __('ui.sidebar.ai_settings') }}</span>
                </a>

                <a href="{{ route($panelPrefix . '.saved-replies.index') }}" class="{{ $navActive([$panelPrefix . '.saved-replies.*']) }}">
                    <i class="ri-chat-3-line"></i>
                    <span>{{ __('ui.sidebar.saved_replies') }}</span>
                </a>

                @if(Auth::user()->tenant?->plan?->reservations_enabled)
                <a href="{{ route($panelPrefix . '.reservations.index') }}" class="{{ $navActive([$panelPrefix . '.reservations.*']) }}">
                    <i class="ri-calendar-check-line"></i>
                    <span>{{ __('ui.sidebar.reservations') }}</span>
                </a>
                @endif
            @endif

            @if(!Auth::user()->isSuperAdmin() || $sp('notifications'))
            <a href="{{ route($panelPrefix . '.notifications.index') }}" class="{{ $navActive([$panelPrefix . '.notifications.*']) }}">
                <i class="ri-notification-3-line"></i>
                <span>{{ __('ui.sidebar.notifications') }}</span>
            </a>
            @endif

            <div class="sidebar-section-label">{{ __('ui.sidebar.account') }}</div>

            @if(Auth::user()->isAdmin() || (Auth::user()->isSuperAdmin() && $sp('reports')))
            <a href="{{ route($panelPrefix . '.reports.index') }}" class="{{ $navActive([$panelPrefix . '.reports.*']) }}">
                <i class="ri-bar-chart-2-line"></i>
                <span>{{ __('ui.sidebar.reports') }}</span>
            </a>
            @endif

            @if(!Auth::user()->isSuperAdmin() || $sp('audit_log'))
            <a href="{{ route($panelPrefix . '.audit-log.index') }}" class="{{ $navActive([$panelPrefix . '.audit-log.*']) }}">
                <i class="ri-file-list-3-line"></i>
                <span>{{ __('ui.sidebar.audit_log') }}</span>
            </a>
            @endif

            @if(Auth::user()->isSuperAdmin() && $sp('billing'))
                <a href="{{ route($panelPrefix . '.billing.index') }}" class="{{ $navActive([$panelPrefix . '.billing.index']) }}">
                    <i class="ri-bank-card-line"></i>
                    <span>{{ __('ui.sidebar.billing') }}</span>
                </a>
                <a href="{{ route($panelPrefix . '.billing.payments') }}" class="{{ $navActive([$panelPrefix . '.billing.payments', $panelPrefix . '.billing.payment.show']) }}">
                    <i class="ri-receipt-line"></i>
                    <span>{{ __('ui.sidebar.payments') }}</span>
                </a>
            @endif

            @if(Auth::user()->isSuperAdmin())
            <a href="{{ route($panelPrefix . '.profile.show') }}" class="{{ $navActive([$panelPrefix . '.profile.*']) }}">
                <i class="ri-settings-3-line"></i>
                <span>{{ __('ui.sidebar.settings') }}</span>
            </a>
            @else
            <a href="{{ route($panelPrefix . '.settings.index') }}" class="{{ $navActive([$panelPrefix . '.settings.*']) }}">
                <i class="ri-settings-3-line"></i>
                <span>{{ __('ui.sidebar.settings') }}</span>
            </a>
            @endif
            @endif

            <a href="/docs/api" target="_blank" rel="noopener" style="margin-top:4px;">
                <i class="ri-code-s-slash-line"></i>
                <span>{{ __('ui.sidebar.api_docs') }}</span>
                <i class="ri-external-link-line" style="margin-left:auto;font-size:11px;opacity:.5;"></i>
            </a>
        </nav>

        @auth
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" onerror="this.style.display='none'">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
                    <div class="sidebar-user-role">{{ __('ui.roles.' . Auth::user()->role, ['role' => str_replace('_', ' ', Auth::user()->role)]) }}</div>
                </div>
                <div class="sidebar-user-actions">
                    <a href="{{ route($panelPrefix . '.profile.show') }}" class="sidebar-user-btn" title="{{ __('ui.profile_page.title') }}">
                        <i class="ri-user-settings-line"></i>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" data-no-loading id="logoutForm">
                        @csrf
                        <button type="submit" class="sidebar-user-btn" id="logoutBtn" title="{{ __('ui.logout') }}">
                            <i class="ri-logout-box-r-line"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endauth
    </aside>

    {{-- MAIN WRAPPER --}}
    <div class="main-wrap" id="mainWrap">

        {{-- IMPERSONATION BANNER --}}
        @if(session('impersonating'))
        <div class="impersonation-banner">
            <i class="ri-user-shared-line"></i>
            <span>{!! __('ui.impersonating', ['name' => '<strong>' . e(session('impersonating_name')) . '</strong>']) !!}</span>
            <a href="{{ route('admin.users.impersonate.leave') }}">{{ __('ui.return_to_account') }}</a>
        </div>
        @endif

        {{-- TOPBAR --}}
        <header class="topbar">
            <button class="topbar-toggle" id="sidebarToggle" aria-label="{{ __('ui.dashboard') }}">
                <i class="ri-menu-3-line"></i>
            </button>

            <nav class="topbar-breadcrumb">
                <a href="{{ route($panelPrefix . '.dashboard') }}">
                    <i class="ri-home-4-line" style="font-size:15px;"></i>
                </a>
                @hasSection('breadcrumb')
                    <span class="sep"><i class="ri-arrow-right-s-line"></i></span>
                    @yield('breadcrumb')
                @endif
            </nav>

            <div class="topbar-right">
                <form method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <input type="hidden" name="redirect" value="{{ url()->full() }}">
                    <select id="locale-switch"
                            name="locale"
                            aria-label="{{ __('ui.language') }}"
                            onchange="this.form.submit()"
                            style="height:36px;padding:0 .625rem;border:1px solid var(--card-border);border-radius:var(--radius);background:var(--topbar-bg);color:var(--text-secondary);font-size:.8125rem;cursor:pointer;">
                        @foreach(($supportedLocales ?? config('locales.supported', [])) as $localeCode => $localeMeta)
                            <option value="{{ $localeCode }}" @selected(($currentLocale ?? app()->getLocale()) === $localeCode)>
                                {{ __('ui.languages.' . $localeCode) }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <a href="{{ route('admin.conversations.index', ['tab' => 'pool']) }}" class="topbar-btn" title="{{ __('ui.inbox') }}">
                    <i class="ri-inbox-line"></i>
                    @if(($poolCount ?? 0) > 0)
                        <span class="topbar-notif-dot"></span>
                    @endif
                </a>

                {{-- Notification bell --}}
                <div class="notif-wrap" id="notifWrap">
                    <button class="topbar-btn" id="notifBtn" title="{{ __('ui.notifications') }}">
                        <i class="ri-notification-3-line"></i>
                        <span class="notif-badge" id="notifBadge" style="display:none">0</span>
                    </button>
                    <div class="notif-panel" id="notifPanel">
                        <div class="notif-panel-header">
                            <span class="notif-panel-header-title">{{ __('ui.notifications') }}</span>
                            <button class="notif-mark-all" id="notifMarkAll" onclick="notifMarkAllRead()">
                                {{ __('ui.notifications_page.mark_all_read') }}
                            </button>
                        </div>
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty">
                                <i class="ri-loader-4-line"></i>
                                {{ __('ui.notifications_page.loading') }}
                            </div>
                        </div>
                        <div class="notif-panel-footer">
                            @if(Auth::user()->hasAnyRole(['admin', 'super_admin']))
                                <a href="{{ route($panelPrefix . '.notifications.index') }}">
                                    {{ __('ui.notifications_page.manage') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </header>

        {{-- PAGE CONTENT --}}
        <main class="page-content">
            @yield('content')
        </main>
    </div>

    {{-- SEND / GENERIC CONFIRM MODAL (global) --}}
    <div class="modal-overlay" id="sendModal" role="dialog" aria-modal="true"
         onclick="if(event.target===this) closeSendModal()">
        <div class="modal-box">
            <div class="modal-icon warning"><i class="ri-send-plane-line"></i></div>
            <h3 id="sendModalTitle">{{ __('ui.confirm_action') }}</h3>
            <p id="sendModalMessage">{{ __('ui.proceed_confirmation') }}</p>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeSendModal()">
                    <i class="ri-close-line"></i> {{ __('ui.cancel') }}
                </button>
                <button type="button" id="sendModalConfirmBtn" class="btn btn-primary">
                    <i class="ri-check-line"></i> {{ __('ui.confirm') }}
                </button>
            </div>
        </div>
    </div>

    {{-- DELETE MODAL (global) --}}
    <div class="modal-overlay" id="deleteModal" role="dialog" aria-modal="true"
         onclick="if(event.target===this) closeDeleteModal()">
        <div class="modal-box">
            <div class="modal-icon danger"><i class="ri-delete-bin-2-line"></i></div>
            <h3 id="deleteModalTitle">{{ __('ui.confirm_deletion') }}</h3>
            <p id="deleteModalMessage">{{ __('ui.irreversible_warning') }}</p>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeDeleteModal()">
                    <i class="ri-close-line"></i> {{ __('ui.cancel') }}
                </button>
                <form id="deleteForm" method="POST" style="display:none;flex:1;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;">
                        <i class="ri-delete-bin-line"></i> {{ __('ui.delete') }}
                    </button>
                </form>
                <button type="button" id="deleteCallbackBtn" class="btn btn-danger" style="display:none;flex:1;justify-content:center;">
                    <i class="ri-delete-bin-line"></i> {{ __('ui.delete') }}
                </button>
            </div>
        </div>
    </div>

    {{-- TOAST STACK --}}
    <div class="toast-stack" id="toastStack"></div>

    {{-- BULK BAR --}}
    <div class="bulk-bar" id="bulkBar">
        <span class="bulk-count">{{ __('ui.bulk_selected_zero', ['count' => 0]) }}</span>
        <span class="bulk-sep">|</span>
        <div class="bulk-actions" id="bulkActions"></div>
        <button type="button" class="bulk-close" onclick="closeBulkBar()">
            <i class="ri-close-line"></i>
        </button>
    </div>

    <script>
    /* ====================================================
       SIDEBAR TOGGLE
    ==================================================== */
    const sidebar   = document.getElementById('sidebar');
    const mainWrap  = document.getElementById('mainWrap');
    const toggleBtn = document.getElementById('sidebarToggle');
    let sidebarOpen = window.innerWidth >= 1024;

    function setSidebar(open) {
        sidebarOpen = open;
        if (window.innerWidth < 1024) {
            sidebar.classList.toggle('open', open);
        } else {
            sidebar.classList.toggle('collapsed', !open);
            mainWrap.classList.toggle('sidebar-collapsed', !open);
        }
        localStorage.setItem('sidebarOpen', open ? '1' : '0');
    }

    if (window.innerWidth >= 1024) {
        const saved = localStorage.getItem('sidebarOpen');
        setSidebar(saved === null ? true : saved === '1');
    }

    toggleBtn?.addEventListener('click', () => setSidebar(!sidebarOpen));

    function scrollSidebarActiveIntoView() {
        const active = sidebar?.querySelector('.sidebar-nav a.active');
        if (active) {
            active.focus({ preventScroll: true });
            active.scrollIntoView({ block: 'center', inline: 'nearest' });
        }
    }

    function deferSidebarFocus() {
        requestAnimationFrame(() => {
            requestAnimationFrame(scrollSidebarActiveIntoView);
        });
    }

    window.addEventListener('DOMContentLoaded', deferSidebarFocus);
    window.addEventListener('load', deferSidebarFocus);
    window.addEventListener('pageshow', deferSidebarFocus);
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) scrollSidebarActiveIntoView();
    });

    /* ====================================================
       GLOBAL SUBMIT LOADING
    ==================================================== */
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if ('noLoading' in form.dataset) return;
        const btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        btn.classList.add('loading');
        btn.innerHTML = '<span class="btn-spinner"></span> {{ __('ui.processing') }}';
    });

    document.getElementById('logoutForm')?.addEventListener('submit', function () {
        const btn = document.getElementById('logoutBtn');
        if (btn) btn.innerHTML = '<span class="btn-spinner" style="width:14px;height:14px;border-width:2px;margin:0;"></span>';
    });

    window.resetSubmitBtn = function(btn, label = 'Save') {
        btn.classList.remove('loading');
        btn.textContent = label;
    };

    /* ====================================================
       TOAST
    ==================================================== */
    window.showToast = function(type, title, msg = '') {
        const icons = { success: 'ri-check-line', error: 'ri-error-warning-line', warning: 'ri-alert-line' };
        const stack = document.getElementById('toastStack');
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'error' ? 'error' : type === 'warning' ? 'warning' : ''}`;
        toast.innerHTML = `
            <div class="toast-icon"><i class="${icons[type] || icons.success}"></i></div>
            <div class="toast-text">
                <div class="toast-title">${title}</div>
                ${msg ? `<div class="toast-msg">${msg}</div>` : ''}
            </div>
        `;
        stack.appendChild(toast);
        setTimeout(() => toast.remove(), 4500);
    };

    /* ====================================================
       SEARCHABLE SELECT — auto-enhances select.form-control
       Skip with data-no-ss attribute on the <select>
    ==================================================== */
    window.initSS = function(container) {
        (container || document).querySelectorAll('select:not([data-no-ss]):not([data-ss-inited])').forEach(function(select) {
            select.setAttribute('data-ss-inited', '1');
            select.style.cssText += ';display:none!important;position:absolute;opacity:0;pointer-events:none';

            var wrap = document.createElement('div');
            wrap.className = 'ss-wrap';

            var displayInput = document.createElement('input');
            displayInput.type = 'text';
            displayInput.className = 'ss-input';
            displayInput.readOnly = true;
            displayInput.tabIndex = 0;
            if (select.classList.contains('error')) wrap.classList.add('error');

            var chevron = document.createElement('i');
            chevron.className = 'ri-arrow-down-s-line ss-chevron';

            var dropdown = document.createElement('div');
            dropdown.className = 'ss-dropdown';

            /* Always show filter field for select2-like UX */
            var useSearch = true;
            var searchRow = null;
            if (useSearch) {
                searchRow = document.createElement('div');
                searchRow.className = 'ss-search-row';
                searchRow.innerHTML = '<div class="ss-search-inner"><i class="ri-search-line"></i><input type="text" placeholder="{{ __('ui.select_filter_hint') }}" autocomplete="off"></div>';
            }

            var list = document.createElement('div');
            list.className = 'ss-list';

            var hasPlaceholder = false;
            var placeholderText = '';
            Array.from(select.options).forEach(function(opt, idx) {
                if (!opt.value && idx === 0) {
                    hasPlaceholder = true;
                    placeholderText = opt.textContent.trim();
                    return;
                }
                var item = document.createElement('div');
                item.className = 'ss-item';
                item.dataset.value = opt.value;
                item.dataset.label = opt.textContent.trim();
                item.textContent = opt.textContent.trim();
                if (opt.selected && opt.value) {
                    item.classList.add('ss-selected');
                    displayInput.value = opt.textContent.trim();
                }
                list.appendChild(item);
            });

            displayInput.placeholder = placeholderText || (hasPlaceholder ? 'Select...' : 'Select...');
            if (!displayInput.value && select.value) {
                var cur = select.options[select.selectedIndex];
                if (cur && cur.value) displayInput.value = cur.textContent.trim();
            }

            if (searchRow) dropdown.appendChild(searchRow);
            dropdown.appendChild(list);
            wrap.appendChild(displayInput);
            wrap.appendChild(chevron);
            wrap.appendChild(dropdown);

            select.parentNode.insertBefore(wrap, select.nextSibling);

            var filterInput = searchRow ? searchRow.querySelector('input') : null;

            function visibleItems() {
                return Array.from(list.querySelectorAll('.ss-item')).filter(function(el) { return el.style.display !== 'none'; });
            }
            function moveActive(delta) {
                var items = visibleItems();
                var idx = items.findIndex(function(el) { return el.classList.contains('active'); });
                if (idx >= 0) items[idx].classList.remove('active');
                var next = items[Math.max(0, Math.min(items.length - 1, idx + delta))];
                if (next) { next.classList.add('active'); next.scrollIntoView({ block: 'nearest' }); }
            }
            function syncDropdownPosition() {
                wrap.classList.remove('open-up');

                var rect = wrap.getBoundingClientRect();
                var spaceBelow = window.innerHeight - rect.bottom;
                var spaceAbove = rect.top;
                var dropdownHeight = dropdown.offsetHeight || 260;

                if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                    wrap.classList.add('open-up');
                }
            }
            function openDropdown() {
                syncDropdownPosition();
                wrap.classList.add('open');
                renderItems('');
                syncDropdownPosition();
                if (filterInput) { filterInput.value = ''; filterInput.focus(); }
            }
            function closeDropdown() {
                wrap.classList.remove('open');
                wrap.classList.remove('open-up');
                list.querySelectorAll('.ss-item.active').forEach(function(el) { el.classList.remove('active'); });
            }
            function renderItems(q) {
                var lower = q.toLowerCase();
                var visible = 0;
                list.querySelectorAll('.ss-item').forEach(function(el) {
                    var matches = !lower || el.dataset.label.toLowerCase().includes(lower);
                    el.style.display = matches ? '' : 'none';
                    if (matches) visible++;
                });
                var emptyEl = list.querySelector('.ss-empty');
                if (!visible) {
                    if (!emptyEl) { emptyEl = document.createElement('div'); emptyEl.className = 'ss-empty'; emptyEl.textContent = @json(__('ui.select_no_results')); list.appendChild(emptyEl); }
                    emptyEl.style.display = '';
                } else if (emptyEl) { emptyEl.style.display = 'none'; }
            }
            function selectItem(value, label) {
                select.value = value;
                displayInput.value = label;
                list.querySelectorAll('.ss-item').forEach(function(el) {
                    el.classList.toggle('ss-selected', el.dataset.value === value);
                });
                wrap.classList.remove('error');
                select.dispatchEvent(new Event('change', { bubbles: true }));
                closeDropdown();
                displayInput.focus();
            }

            list.addEventListener('mousedown', function(e) {
                var item = e.target.closest('.ss-item');
                if (item) { e.preventDefault(); selectItem(item.dataset.value, item.dataset.label); }
            });

            displayInput.addEventListener('click', function() {
                wrap.classList.contains('open') ? closeDropdown() : openDropdown();
            });
            displayInput.addEventListener('keydown', function(e) {
                var isOpen = wrap.classList.contains('open');
                if (!isOpen) {
                    if (['ArrowDown','ArrowUp','Enter',' '].includes(e.key)) { e.preventDefault(); openDropdown(); }
                    return;
                }
                if (e.key === 'Escape' || e.key === 'Tab') closeDropdown();
            });
            if (filterInput) {
                filterInput.addEventListener('input', function() { renderItems(this.value.trim()); });
                filterInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape')  { closeDropdown(); displayInput.focus(); }
                    else if (e.key === 'Enter') { e.preventDefault(); var a = list.querySelector('.ss-item.active'); if (a) selectItem(a.dataset.value, a.dataset.label); }
                    else if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); }
                    else if (e.key === 'ArrowUp')   { e.preventDefault(); moveActive(-1); }
                });
            }
            document.addEventListener('click', function(e) { if (!wrap.contains(e.target) && e.target !== select) closeDropdown(); });
            window.addEventListener('resize', function() {
                if (wrap.classList.contains('open')) syncDropdownPosition();
            });
            window.addEventListener('scroll', function() {
                if (wrap.classList.contains('open')) syncDropdownPosition();
            }, true);
        });
    };

    window.applyServerValidationErrors = function(errors) {
        if (!errors || typeof errors !== 'object') return;

        const escapeSelector = (value) => String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        const mapToBracket = (name) => name
            .replace(/\.(\d+)/g, '[$1]')
            .replace(/\.([^.]+)/g, '[$1]');

        Object.entries(errors).forEach(([fieldName, messages]) => {
            if (!Array.isArray(messages) || !messages.length) return;

            const candidates = [fieldName];
            const bracketName = mapToBracket(fieldName);
            if (bracketName !== fieldName) candidates.push(bracketName);

            let field = null;
            for (const candidate of candidates) {
                field = document.querySelector(`[name="${escapeSelector(candidate)}"]`);
                if (field) break;
            }
            if (!field) return;

            field.classList.add('error');
            const ssWrap = field.nextElementSibling && field.nextElementSibling.classList.contains('ss-wrap')
                ? field.nextElementSibling
                : null;
            if (ssWrap) ssWrap.classList.add('error');

            const formGroup = field.closest('.form-group') || field.parentElement;
            if (!formGroup) return;
            if (formGroup.querySelector('.form-error')) return;

            const errorEl = document.createElement('div');
            errorEl.className = 'form-error';
            errorEl.textContent = messages[0];
            formGroup.appendChild(errorEl);
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        const path = window.location.pathname || '';
        const isCreateOrEditPage = /\/(create|edit)\/?$/.test(path);

        if (isCreateOrEditPage) {
            document.querySelectorAll('form').forEach(form => {
                form.setAttribute('novalidate', 'novalidate');
                form.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));
            });
        }

        window.initSS();
        @if($errors->any())
            window.applyServerValidationErrors(@json($errors->toArray()));
        @endif
    });

    // Flash messages from server
    @if(session('success'))
        window.addEventListener('DOMContentLoaded', () => showToast('success', '{{ session('success') }}'));
    @endif
    @if(session('error'))
        window.addEventListener('DOMContentLoaded', () => showToast('error', '{{ session('error') }}'));
    @endif

    /* ====================================================
       DELETE MODAL
    ==================================================== */
    window.confirmDelete = function(url, opts = {}) {
        const modal = document.getElementById('deleteModal');
        const titleEl = document.getElementById('deleteModalTitle');
        const msgEl   = document.getElementById('deleteModalMessage');
        const form    = document.getElementById('deleteForm');
        const cbBtn   = document.getElementById('deleteCallbackBtn');

        titleEl.textContent = opts.title || @json(__('ui.confirm_deletion'));
        msgEl.textContent   = opts.message || @json(__('ui.irreversible_warning'));

        if (url) {
            form.action = url;
            form.style.display = 'flex';
            cbBtn.style.display = 'none';
        } else {
            form.style.display = 'none';
            cbBtn.style.display = 'flex';
            cbBtn.onclick = () => { opts.callback?.(); closeDeleteModal(); };
        }

        modal.classList.add('show');
        document.getElementById('deleteModalMessage').focus();
    };

    window.closeDeleteModal = function() {
        document.getElementById('deleteModal').classList.remove('show');
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDeleteModal();
    });

    /* ====================================================
       UNSAVED CHANGES GUARD
    ==================================================== */
    let formDirty = false;
    let allowDirtyNavigation = false;
    const isCreateOrEditPage = /\/(create|edit)\/?$/.test(window.location.pathname || '');

    function shouldTrackUnsaved(target) {
        if (!isCreateOrEditPage || !target?.name) return false;

        const form = target.closest('form');
        if (!form || form.matches('[data-no-unsaved-guard]')) return false;

        return true;
    }

    function shouldInterceptNavigation(link) {
        if (!isCreateOrEditPage || !formDirty || allowDirtyNavigation || !link?.href) return false;
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download') || link.closest('[data-no-unsaved-guard]')) return false;

        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return false;
        }

        return link.href !== window.location.href;
    }

    function confirmDirtyNavigation(callback) {
        confirmSend({
            title: @json(__('ui.leave_page_prompt')),
            message: @json(__('ui.unsaved_changes_warning')),
            callback: function () {
                allowDirtyNavigation = true;
                formDirty = false;
                callback();
            }
        });
    }

    document.addEventListener('input', e => {
        if (shouldTrackUnsaved(e.target)) formDirty = true;
    });
    document.addEventListener('change', e => {
        if (shouldTrackUnsaved(e.target)) formDirty = true;
    });
    document.addEventListener('submit', () => {
        formDirty = false;
        allowDirtyNavigation = true;
    });
    document.addEventListener('click', e => {
        const link = e.target.closest('a[href]');
        if (!shouldInterceptNavigation(link)) return;

        e.preventDefault();
        confirmDirtyNavigation(() => {
            window.location.href = link.href;
        });
    }, true);
    window.markFormClean = () => formDirty = false;

    /* ====================================================
       BULK BAR
    ==================================================== */
    function closeBulkBar() {
        document.getElementById('bulkBar').classList.remove('visible');
        document.querySelectorAll('.row-cb').forEach(cb => cb.checked = false);
        document.querySelector('.header-cb')?.toggleAttribute('checked', false);
    }

    window.createBulkManager = function({ barId, deleteUrl, onDeleted, csvFields, getAllData, exportFileName }) {
        const bar    = document.getElementById(barId);
        const countEl= bar.querySelector('.bulk-count');
        let selected = new Set();

        function update() {
            countEl.textContent = selected.size + ' ' + @json(__('ui.selected_items'));
            bar.classList.toggle('visible', selected.size > 0);
        }

        document.addEventListener('change', e => {
            if (e.target.classList.contains('header-cb')) {
                document.querySelectorAll('.row-cb').forEach(cb => {
                    cb.checked = e.target.checked;
                    e.target.checked ? selected.add(cb.value) : selected.delete(cb.value);
                });
                update();
            } else if (e.target.classList.contains('row-cb')) {
                e.target.checked ? selected.add(e.target.value) : selected.delete(e.target.value);
                update();
            }
        });

        return {
            restore: function() {
                document.querySelectorAll('.row-cb').forEach(cb => {
                    if (selected.has(cb.value)) cb.checked = true;
                });
            },
            getSelected: () => [...selected],
        };
    };

    /* ====================================================
       SEND / GENERIC CONFIRM MODAL
    ==================================================== */
    window.confirmSend = function(opts) {
        const modal   = document.getElementById('sendModal');
        const titleEl = document.getElementById('sendModalTitle');
        const msgEl   = document.getElementById('sendModalMessage');
        const btn     = document.getElementById('sendModalConfirmBtn');

        titleEl.textContent = opts.title   || @json(__('ui.confirm_action'));
        msgEl.textContent   = opts.message || @json(__('ui.proceed_confirmation'));

        btn.onclick = function() {
            closeSendModal();
            if (opts.callback) opts.callback();
        };

        modal.classList.add('show');
    };

    window.closeSendModal = function() {
        document.getElementById('sendModal').classList.remove('show');
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeSendModal();
    });

    /* ====================================================
       COLUMN SORT + PER-COLUMN FILTER HELPERS
    ==================================================== */

    /**
     * Renders a sortable <th> with an embedded per-column filter input.
     * Usage: window.thSort(label, col, sortCol, sortDir, colFilters)
     */
    window.thSort = function(label, col, sortCol, sortDir, colFilters) {
        var active  = sortCol === col;
        var arrowCls = active ? (sortDir === 'asc' ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line') : 'ri-expand-up-down-line';
        var filterVal = (colFilters && colFilters[col]) ? colFilters[col] : '';
        return '<th class="th-sortable" data-col="' + col + '" style="cursor:pointer;user-select:none;white-space:nowrap;padding-bottom:6px;">' +
            '<div style="display:flex;align-items:center;gap:4px;">' +
                '<span>' + label + '</span>' +
                '<i class="' + arrowCls + '" style="font-size:14px;color:' + (active ? 'var(--brand)' : 'var(--text-muted)') + '"></i>' +
            '</div>' +
            '<input type="text" class="col-filter" data-col="' + col + '" value="' + (filterVal.replace ? filterVal.replace(/"/g, '&quot;') : '') + '" ' +
                'placeholder="{{ __('ui.select_filter_hint') }}" onclick="event.stopPropagation()" ' +
                'style="margin-top:4px;width:100%;height:26px;padding:0 6px;font-size:11px;border:1px solid var(--card-border);border-radius:var(--radius-sm);background:var(--page-bg);color:var(--text-primary);outline:none;font-family:inherit;" ' +
                'onfocus="this.style.borderColor=\'var(--brand)\'" onblur="this.style.borderColor=\'var(--card-border)\'">' +
        '</th>';
    };

    /**
     * Sorts an array of objects by a key.
     * Usage: window.sortArr(data, 'name', 'asc')
     */
    window.sortArr = function(data, col, dir) {
        if (!col) return data;
        var d = dir === 'desc' ? -1 : 1;
        return data.slice().sort(function(a, b) {
            var av = a[col] != null ? a[col] : '';
            var bv = b[col] != null ? b[col] : '';
            if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * d;
            return String(av).toLowerCase() < String(bv).toLowerCase() ? -d : String(av).toLowerCase() > String(bv).toLowerCase() ? d : 0;
        });
    };

    /**
     * Filters an array of objects by per-column substring match.
     * Usage: window.filterByCol(data, { name: 'alice', email: 'gmail' })
     */
    window.filterByCol = function(data, colFilters) {
        if (!colFilters) return data;
        return data.filter(function(row) {
            return Object.keys(colFilters).every(function(col) {
                var q = (colFilters[col] || '').toLowerCase().trim();
                if (!q) return true;
                var v = row[col] != null ? String(row[col]).toLowerCase() : '';
                return v.includes(q);
            });
        });
    };

    /* ====================================================
       TOGGLE SWITCHES — AJAX auto-submit
    ==================================================== */
    document.addEventListener('change', function(e) {
        const toggle = e.target.closest('[data-toggle-url]');
        if (!toggle) return;
        fetch(toggle.dataset.toggleUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
            body: JSON.stringify({ value: toggle.checked ? 1 : 0 }),
        }).then(r => r.json()).then(d => {
            showToast(d.ok ? 'success' : 'error', d.message || 'Updated');
        }).catch(() => showToast('error', 'Request failed'));
    });
    </script>

    @stack('scripts')
    @livewireScripts

    <script>
    /* ====================================================
       NOTIFICATION BELL — real-time via Reverb + AJAX fallback
    ==================================================== */
    (function() {
        const btn   = document.getElementById('notifBtn');
        const panel = document.getElementById('notifPanel');
        const badge = document.getElementById('notifBadge');
        const list  = document.getElementById('notifList');

        if (!btn || !panel) return;

        const typeIcons = {
            manual:  'ri-notification-3-line',
            renewal: 'ri-refresh-line',
            system:  'ri-information-line',
        };
        let unreadCount = 0;

        /* ---- helpers ---- */
        function escapeHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function timeAgo(iso) {
            const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
            if (diff < 60)    return '{{ __('ui.notifications_page.just_now') }}';
            if (diff < 3600)  return Math.floor(diff / 60) + 'm';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h';
            return Math.floor(diff / 86400) + 'd';
        }

        function setBadge(count) {
            unreadCount = Math.max(0, count);
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function buildItem(n) {
            return `
                <div class="notif-item ${n.read_at ? '' : 'unread'}" data-id="${n.id}"
                     onclick="notifMarkRead(${n.id}, this)">
                    <div class="notif-item-icon ${n.type}">
                        <i class="${typeIcons[n.type] || typeIcons.manual}"></i>
                    </div>
                    <div class="notif-item-body">
                        <div class="notif-item-title">${escapeHtml(n.title)}</div>
                        <div class="notif-item-body-text">${escapeHtml(n.body)}</div>
                        <div class="notif-item-time">${timeAgo(n.created_at)}</div>
                    </div>
                    ${!n.read_at ? '<div class="notif-unread-dot"></div>' : ''}
                </div>`;
        }

        function renderList(items) {
            if (!items.length) {
                list.innerHTML = '<div class="notif-empty"><i class="ri-notification-off-line"></i>{{ __('ui.notifications_page.empty') }}</div>';
                return;
            }
            list.innerHTML = items.map(buildItem).join('');
        }

        /* ---- AJAX fetch ---- */
        async function fetchCount() {
            try {
                const r = await fetch('/api/notifications/unread-count', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                setBadge(d.count || 0);
            } catch (e) { /* silent */ }
        }

        async function fetchNotifications() {
            list.innerHTML = '<div class="notif-empty"><i class="ri-loader-4-line"></i>{{ __('ui.notifications_page.loading') }}</div>';
            try {
                const r = await fetch('/api/notifications', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                renderList(d.data || []);
            } catch (e) {
                list.innerHTML = '<div class="notif-empty"><i class="ri-error-warning-line"></i>Failed to load</div>';
            }
        }

        /* ---- mark read ---- */
        window.notifMarkRead = async function(id, el) {
            if (!el.classList.contains('unread')) return;
            el.classList.remove('unread');
            el.querySelector('.notif-unread-dot')?.remove();
            setBadge(unreadCount - 1);
            try {
                await fetch(`/api/notifications/${id}/read`, {
                    method:  'PATCH',
                    headers: {
                        'X-CSRF-TOKEN':     document.querySelector('meta[name=csrf-token]')?.content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } catch (e) { /* silent */ }
        };

        window.notifMarkAllRead = async function() {
            list.querySelectorAll('.notif-item.unread').forEach(el => {
                el.classList.remove('unread');
                el.querySelector('.notif-unread-dot')?.remove();
            });
            setBadge(0);
            try {
                await fetch('/api/notifications/read-all', {
                    method:  'POST',
                    headers: {
                        'X-CSRF-TOKEN':     document.querySelector('meta[name=csrf-token]')?.content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } catch (e) { /* silent */ }
        };

        /* ---- real-time: prepend incoming notification ---- */
        function onNotificationCreated(data) {
            // Bump badge
            setBadge(unreadCount + 1);

            // Prepend to open panel list
            const emptyEl = list.querySelector('.notif-empty');
            if (emptyEl) emptyEl.remove();
            list.insertAdjacentHTML('afterbegin', buildItem(data));

            // Keep max 20 items in DOM
            const items = list.querySelectorAll('.notif-item');
            if (items.length > 20) items[items.length - 1].remove();

            // Toast
            if (typeof showToast === 'function') {
                showToast('success', escapeHtml(data.title), escapeHtml((data.body || '').substring(0, 80)));
            }
        }

        /* ---- WebSocket subscription ---- */
        function subscribeEcho() {
            if (!window.Echo) return;
            window.Echo
                .private('user.{{ Auth::id() }}')
                .listen('.notification.created', function(data) {
                    onNotificationCreated(data);
                });
        }

        // Subscribe when Echo connects (may already be connected on DOMContentLoaded)
        if (window._echoConnected) {
            subscribeEcho();
        } else {
            window._echoStateListeners = window._echoStateListeners || [];
            window._echoStateListeners.push(function(connected) {
                if (connected) subscribeEcho();
            });
        }

        // Fallback: also try after DOMContentLoaded in case the listener fires before Echo init
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(subscribeEcho, 800);
        });

        /* ---- toggle panel ---- */
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const open = panel.classList.toggle('open');
            if (open) fetchNotifications();
        });

        document.addEventListener('click', function(e) {
            if (!panel.contains(e.target) && e.target !== btn) {
                panel.classList.remove('open');
            }
        });

        /* ---- initial count fetch + poll fallback every 5 min ---- */
        fetchCount();
        setInterval(fetchCount, 300000);
    })();
    </script>
</body>
</html>
