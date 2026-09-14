@extends('layouts.admin')

@section('title', __('ui.webchat_settings.title'))

@section('breadcrumb')
    <span>{{ __('ui.sidebar.ai_knowledge') ?? 'AI & Knowledge' }}</span>
    <i class="ri-arrow-right-s-line"></i>
    <span>{{ __('ui.webchat_settings.title') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();

    // Auto-version the embed src from widget.js's mtime so a fresh paste is
    // never cached to an old build. Existing embeds without ?v= still work.
    $widgetFile = public_path('webchat/widget.js');
    $widgetVer  = file_exists($widgetFile) ? substr((string) filemtime($widgetFile), -6) : '1';
    $embedUrl   = $embedBase . '/webchat/widget.js?v=' . $widgetVer;

    // Precompute the ready-to-paste embed snippet with the tenant's public_key
    // and the app's own base URL. The paste target is the tenant's website.
    $embedSnippet = "<script>window.WavadeskChat = { key: \"" . $widget->public_key . "\" };</script>\n"
                  . "<script src=\"" . $embedUrl . "\" async></script>";

    $initial = [
        'name'               => (string) old('name', $widget->name),
        'header_subtitle'    => (string) old('header_subtitle', $widget->header_subtitle ?? ''),
        'enabled'            => (bool)   old('enabled', $widget->enabled),
        'welcome_message'    => (string) old('welcome_message', $widget->welcome_message),
        'suggestions'        => array_values((array) old('suggestions', $widget->suggestions ?? [])),
        'pre_chat_ask_email' => (bool)   old('pre_chat_ask_email', $widget->pre_chat_ask_email),
        'offline_message'    => (string) old('offline_message', $widget->offline_message ?? ''),
        'theme_color'        => (string) old('theme_color', $widget->theme_color),
        'position'           => (string) old('position', $widget->position),
        'launcher_text'      => (string) old('launcher_text', $widget->launcher_text ?? ''),
        'launcher_icon'      => (string) old('launcher_icon', $widget->launcher_icon ?: 'chat'),
        'bubble_style'       => (string) old('bubble_style', $widget->bubble_style ?: 'soft'),
        'show_branding'      => (bool)   old('show_branding', $widget->show_branding),
        'default_lang'       => (string) old('default_lang', $widget->default_lang ?: 'ar'),
        'available_languages'=> array_values((array) old('available_languages', $widget->available_languages ?: ['ar', 'en'])),
        'topics'             => array_values((array) old('topics', $widget->topics ?? [])),
        'allowed_domains'    => array_values((array) old('allowed_domains', $widget->allowed_domains ?? [])),
    ];

    // Palette matches widget.js CSS classes (.wvch-topic-<tint>).
    $topicTints = ['blue', 'orange', 'green', 'purple', 'red', 'gray', 'teal'];
    $topicTintHex = [
        'blue' => '#3b82f6', 'orange' => '#f97316', 'green' => '#10b981',
        'purple' => '#15b6a8', 'red' => '#ef4444', 'gray' => '#6b7280', 'teal' => '#14b8a6',
    ];

    // Theme-colour quick picks shown as swatches next to the hex field.
    $themePresets = ['#0f7e7a', '#15b6a8', '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#0d1417'];

    $langNames = ['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'];

    // A section is display:none when it is not the active one, so a validation
    // error inside a collapsed section would be invisible. Map each field back
    // to its section and open the first one that failed.
    $sectionOfField = [
        'name' => 'widget', 'header_subtitle' => 'widget', 'enabled' => 'widget',
        'welcome_message' => 'welcome', 'suggestions' => 'welcome',
        'pre_chat_ask_email' => 'welcome', 'offline_message' => 'welcome',
        'theme_color' => 'appear', 'position' => 'appear', 'launcher_text' => 'appear',
        'launcher_icon' => 'appear', 'bubble_style' => 'appear', 'show_branding' => 'appear',
        'topics' => 'topics',
        'available_languages' => 'langs', 'default_lang' => 'langs',
        'allowed_domains' => 'security',
    ];
    $openSection = 'widget';
    foreach ($errors->keys() as $key) {
        $root = explode('.', $key)[0];
        if (isset($sectionOfField[$root])) { $openSection = $sectionOfField[$root]; break; }
    }

    $i18n = [
        'copy'        => __('ui.webchat_settings.copy'),
        'copied'      => __('ui.webchat_settings.copied'),
        'untitled'    => __('ui.webchat_settings.topic_untitled'),
        'required'    => __('ui.webchat_settings.topic_required'),
        'fallbackFmt' => __('ui.webchat_settings.topic_fallback', ['lang' => '__LANG__']),
        'composer'    => __('ui.webchat_settings.preview_composer'),
        'poweredBy'   => __('ui.webchat_settings.powered_by', ['app' => config('app.name', 'wavadesk')]),
        'posLeft'     => __('ui.webchat_settings.position_left'),
        'posRight'    => __('ui.webchat_settings.position_right'),
        'cornerSoft'    => __('ui.webchat_settings.bubble_style_soft'),
        'cornerRounded' => __('ui.webchat_settings.bubble_style_rounded'),
        'cornerSquare'  => __('ui.webchat_settings.bubble_style_square'),
    ];
@endphp

{{-- The console layout below fills the viewport and manages its own scroll
     regions, so the shared .page-content padding/height is neutralised for
     this route only. --}}
<script>document.body.classList.add('wc-host');</script>

<div class="wv-console" x-data="wcSettings()" x-cloak>
<form method="POST" action="{{ route($panelPrefix . '.webchat.settings.update') }}" class="wc-shell" @submit="onSubmit($event)">
    @csrf
    @method('PUT')

    {{-- ══ PAGE HEAD ═══════════════════════════════════════════════ --}}
    <div class="wc-phead">
        <div class="m">
            <h1>
                {{ __('ui.webchat_settings.title') }}
                <span class="wc-pill" :class="form.enabled ? 'on' : 'off'">
                    <i></i><span x-text="form.enabled ? @js(__('ui.webchat_settings.pill_live')) : @js(__('ui.webchat_settings.pill_disabled'))"></span>
                </span>
            </h1>
            <p>{{ __('ui.webchat_settings.subtitle') }}</p>
        </div>
        <div class="acts">
            <span class="wc-dirty" :class="dirty ? 'on' : ''">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 8v4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
                {{ __('ui.webchat_settings.unsaved') }}
            </span>
            <button type="button" class="wc-btn g" @click="discard()" :disabled="!dirty">{{ __('ui.webchat_settings.discard') }}</button>
            <button type="submit" class="wc-btn p" :disabled="saving">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                {{ __('ui.webchat_settings.save') }}
            </button>
        </div>
    </div>

    <div class="wc-body">

        {{-- ══ SECTION NAV ══════════════════════════════════════════ --}}
        <nav class="wc-snav">
            <div class="lbl">{{ __('ui.webchat_settings.nav_settings') }}</div>

            <button type="button" class="wc-sn" :class="section === 'widget' ? 'on' : ''" @click="go('widget')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor" stroke-width="2"/><path d="M3 8h18" stroke="currentColor" stroke-width="2"/></svg>
                <span>{{ __('ui.webchat_settings.nav_widget') }}</span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'welcome' ? 'on' : ''" @click="go('welcome')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                <span>{{ __('ui.webchat_settings.nav_welcome') }}</span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'appear' ? 'on' : ''" @click="go('appear')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 3a9 9 0 000 18" fill="currentColor" opacity=".22"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                <span>{{ __('ui.webchat_settings.nav_appearance') }}</span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'topics' ? 'on' : ''" @click="go('topics')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7.5" height="7.5" rx="1.8" stroke="currentColor" stroke-width="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.8" stroke="currentColor" stroke-width="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.8" stroke="currentColor" stroke-width="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.8" stroke="currentColor" stroke-width="2"/></svg>
                <span>{{ __('ui.webchat_settings.nav_topics') }}</span>
                <span class="n" x-text="form.topics.length"></span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'langs' ? 'on' : ''" @click="go('langs')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c2.5 2.4 2.5 15.6 0 18M12 3c-2.5 2.4-2.5 15.6 0 18" stroke="currentColor" stroke-width="2"/></svg>
                <span>{{ __('ui.webchat_settings.nav_languages') }}</span>
                <span class="n" x-text="form.available_languages.length"></span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'install' ? 'on' : ''" @click="go('install')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M8 6l-5 6 5 6M16 6l5 6-5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>{{ __('ui.webchat_settings.nav_install') }}</span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'security' ? 'on' : ''" @click="go('security')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 2l8 4v6c0 5-3.4 8.8-8 10-4.6-1.2-8-5-8-10V6l8-4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                <span>{{ __('ui.webchat_settings.nav_security') }}</span>
                <svg class="warn" width="14" height="14" viewBox="0 0 24 24" fill="none" x-show="form.allowed_domains.length === 0"><path d="M12 3l9.5 17H2.5L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
            </button>
        </nav>

        {{-- ══ FORM ═════════════════════════════════════════════════ --}}
        <div class="wc-form" x-ref="formScroll"><div class="wc-fwrap">

            {{-- ── WIDGET ──────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'widget'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.webchat_settings.card_widget') }}</h2>
                    <p>{{ __('ui.webchat_settings.sec_widget_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <div class="wc-trow hi">
                        <div class="m">
                            <div class="n">{{ __('ui.webchat_settings.label_enabled') }}</div>
                            <div class="s">{{ __('ui.webchat_settings.help_enabled') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.enabled ? 'on' : ''"
                                role="switch" :aria-checked="form.enabled ? 'true' : 'false'"
                                @click="form.enabled = !form.enabled"></button>
                        <input type="hidden" name="enabled" :value="form.enabled ? 1 : 0">
                    </div>
                    @error('enabled') <div class="wc-err">{{ $message }}</div> @enderror
                </div>

                <div class="wc-fld">
                    <label for="wcName">{{ __('ui.webchat_settings.label_name') }}</label>
                    <input id="wcName" class="wc-inp @error('name') err @enderror" type="text" name="name" maxlength="120" x-model="form.name">
                    @error('name') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_name') }}</div>
                </div>

                <div class="wc-fld">
                    <label for="wcTagline">{{ __('ui.webchat_settings.label_header_subtitle') }}</label>
                    <input id="wcTagline" class="wc-inp @error('header_subtitle') err @enderror" type="text" name="header_subtitle"
                           maxlength="160" x-model="form.header_subtitle"
                           placeholder="{{ __('ui.webchat_settings.placeholder_header_subtitle') }}">
                    <div class="wc-cnt"><span x-text="form.header_subtitle.length"></span>/160</div>
                    @error('header_subtitle') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_header_subtitle') }}</div>
                </div>
            </section>

            {{-- ── WELCOME ─────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'welcome'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.webchat_settings.card_welcome') }}</h2>
                    <p>{{ __('ui.webchat_settings.sec_welcome_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <label for="wcWelcome">{{ __('ui.webchat_settings.label_welcome_message') }}</label>
                    <textarea id="wcWelcome" class="wc-inp @error('welcome_message') err @enderror" name="welcome_message"
                              maxlength="4000" x-model="form.welcome_message"></textarea>
                    <div class="wc-cnt"><span x-text="form.welcome_message.length"></span>/4000</div>
                    @error('welcome_message') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_welcome_message') }}</div>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.label_suggestions') }}</span>
                    <div class="wc-hint wc-hint-top">{{ __('ui.webchat_settings.help_suggestions') }}</div>

                    <div class="wc-rep">
                        <template x-if="form.suggestions.length === 0">
                            <div class="wc-empty sm">{{ __('ui.webchat_settings.empty_chips') }}</div>
                        </template>
                        <template x-for="(chip, i) in form.suggestions" :key="'chip' + i">
                            <div class="wc-chiprow">
                                <span class="wc-num" x-text="i + 1"></span>
                                <input class="wc-inp" type="text" maxlength="60"
                                       :name="'suggestions[' + i + ']'"
                                       x-model="form.suggestions[i]"
                                       placeholder="{{ __('ui.webchat_settings.placeholder_chip') }}">
                                <span class="wc-rowacts">
                                    <button type="button" class="wc-ib" :disabled="i === 0" @click="move(form.suggestions, i, -1)" title="↑"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 14l6-6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    <button type="button" class="wc-ib" :disabled="i === form.suggestions.length - 1" @click="move(form.suggestions, i, 1)" title="↓"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 10l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    <button type="button" class="wc-ib del" @click="form.suggestions.splice(i, 1)" title="×"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
                                </span>
                            </div>
                        </template>
                    </div>

                    <button type="button" class="wc-btn g sm wc-mt9" x-show="form.suggestions.length < 12" @click="addChip()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        {{ __('ui.webchat_settings.add_chip') }}
                    </button>

                    @error('suggestions')   <div class="wc-err">{{ $message }}</div> @enderror
                    @error('suggestions.*') <div class="wc-err">{{ $message }}</div> @enderror
                </div>

                <div class="wc-fld">
                    <div class="wc-trow">
                        <div class="m">
                            <div class="n">{{ __('ui.webchat_settings.label_pre_chat') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.pre_chat_ask_email ? 'on' : ''"
                                role="switch" :aria-checked="form.pre_chat_ask_email ? 'true' : 'false'"
                                @click="form.pre_chat_ask_email = !form.pre_chat_ask_email"></button>
                        <input type="hidden" name="pre_chat_ask_email" :value="form.pre_chat_ask_email ? 1 : 0">
                    </div>
                </div>

                <div class="wc-fld">
                    <label for="wcOffline">{{ __('ui.webchat_settings.label_offline_message') }}</label>
                    <textarea id="wcOffline" class="wc-inp wc-inp-sm @error('offline_message') err @enderror" name="offline_message"
                              maxlength="4000" x-model="form.offline_message"></textarea>
                    @error('offline_message') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_offline_message') }}</div>
                </div>
            </section>

            {{-- ── APPEARANCE ──────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'appear'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.webchat_settings.card_appearance') }}</h2>
                    <p>{{ __('ui.webchat_settings.sec_appearance_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.label_theme_color') }}</span>
                    <div class="wc-colorrow">
                        <div class="wc-swatches">
                            @foreach ($themePresets as $preset)
                                <button type="button" class="wc-sw" :class="form.theme_color.toLowerCase() === '{{ $preset }}' ? 'on' : ''"
                                        style="background:{{ $preset }}" title="{{ $preset }}"
                                        @click="form.theme_color = '{{ $preset }}'"></button>
                            @endforeach
                        </div>
                        <div class="wc-hexbox">
                            <input type="color" :value="hexOrDefault(form.theme_color)" @input="form.theme_color = $event.target.value" aria-label="{{ __('ui.webchat_settings.label_theme_color') }}">
                            <input type="text" name="theme_color" maxlength="7" x-model="form.theme_color">
                        </div>
                    </div>
                    <div class="wc-err" x-show="clientError === 'theme_color'">{{ __('ui.webchat_settings.err_theme_color') }}</div>
                    @error('theme_color') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_theme_color') }}</div>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.label_position') }}</span>
                    <div class="wc-seg">
                        <button type="button" :class="form.position === 'left' ? 'on' : ''" @click="form.position = 'left'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2.6" stroke="currentColor" stroke-width="2"/><circle cx="8" cy="16" r="2.6" fill="currentColor"/></svg>
                            {{ __('ui.webchat_settings.position_left') }}
                        </button>
                        <button type="button" :class="form.position === 'right' ? 'on' : ''" @click="form.position = 'right'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2.6" stroke="currentColor" stroke-width="2"/><circle cx="16" cy="16" r="2.6" fill="currentColor"/></svg>
                            {{ __('ui.webchat_settings.position_right') }}
                        </button>
                    </div>
                    <input type="hidden" name="position" :value="form.position">
                    @error('position') <div class="wc-err">{{ $message }}</div> @enderror
                </div>

                <div class="wc-fld">
                    <label for="wcLauncherText">{{ __('ui.webchat_settings.label_launcher_text') }}</label>
                    <input id="wcLauncherText" class="wc-inp @error('launcher_text') err @enderror" type="text" name="launcher_text"
                           maxlength="120" x-model="form.launcher_text"
                           placeholder="{{ __('ui.webchat_settings.placeholder_launcher') }}">
                    @error('launcher_text') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_launcher_text') }}</div>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.label_launcher_icon') }}</span>
                    <div class="wc-picker">
                        @foreach (['chat', 'message', 'help', 'sparkle'] as $iconKey)
                            <button type="button" class="wc-pk" :class="form.launcher_icon === '{{ $iconKey }}' ? 'on' : ''"
                                    @click="form.launcher_icon = '{{ $iconKey }}'">
                                @includeIf('admin.webchat._launcher-icon', ['icon' => $iconKey])
                                <span>{{ __('ui.webchat_settings.launcher_icon_' . $iconKey) }}</span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="launcher_icon" :value="form.launcher_icon">
                    @error('launcher_icon') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_launcher_icon') }}</div>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.label_bubble_style') }}</span>
                    <div class="wc-corners">
                        @foreach (['soft' => '7px', 'rounded' => '11px', 'square' => '2px'] as $styleKey => $swRadius)
                            <button type="button" class="wc-cn" :class="form.bubble_style === '{{ $styleKey }}' ? 'on' : ''"
                                    @click="form.bubble_style = '{{ $styleKey }}'">
                                <span class="sw" style="border-radius:{{ $swRadius }}"></span>
                                <span>{{ __('ui.webchat_settings.bubble_style_' . $styleKey) }}</span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="bubble_style" :value="form.bubble_style">
                    @error('bubble_style') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_bubble_style') }}</div>
                </div>

                <div class="wc-fld">
                    <div class="wc-trow">
                        <div class="m">
                            <div class="n">{{ __('ui.webchat_settings.label_show_branding') }}</div>
                            <div class="s">{{ __('ui.webchat_settings.help_show_branding') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.show_branding ? 'on' : ''"
                                role="switch" :aria-checked="form.show_branding ? 'true' : 'false'"
                                @click="form.show_branding = !form.show_branding"></button>
                        <input type="hidden" name="show_branding" :value="form.show_branding ? 1 : 0">
                    </div>
                </div>
            </section>

            {{-- ── TOPICS ──────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'topics'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.webchat_settings.card_topics') }}</h2>
                    <p>{{ __('ui.webchat_settings.sec_topics_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <div class="wc-rep">
                        <template x-if="form.topics.length === 0">
                            <div class="wc-empty">
                                <div class="t">{{ __('ui.webchat_settings.empty_topics_title') }}</div>
                                <div class="s">{{ __('ui.webchat_settings.empty_topics_hint') }}</div>
                            </div>
                        </template>

                        <template x-for="(topic, t) in form.topics" :key="'topic' + t">
                            <div class="wc-repitem">
                                <div class="wc-rh">
                                    <span class="grip"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="6" r="1.5" fill="currentColor"/><circle cx="15" cy="6" r="1.5" fill="currentColor"/><circle cx="9" cy="12" r="1.5" fill="currentColor"/><circle cx="15" cy="12" r="1.5" fill="currentColor"/><circle cx="9" cy="18" r="1.5" fill="currentColor"/><circle cx="15" cy="18" r="1.5" fill="currentColor"/></svg></span>
                                    <span class="wc-num" x-text="t + 1"></span>
                                    <span class="m"><span class="ttl" x-text="topicTitle(topic)"></span></span>
                                    <span class="wc-rowacts">
                                        <button type="button" class="wc-ib" :disabled="t === 0" @click="move(form.topics, t, -1)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 14l6-6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                        <button type="button" class="wc-ib" :disabled="t === form.topics.length - 1" @click="move(form.topics, t, 1)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 10l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                        <button type="button" class="wc-ib del" @click="form.topics.splice(t, 1)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
                                    </span>
                                </div>

                                <div class="wc-rb">
                                    <div>
                                        <span class="wc-sublabel">{{ __('ui.webchat_settings.topic_card_color') }}</span>
                                        <div class="wc-swatches">
                                            @foreach ($topicTints as $tint)
                                                <button type="button" class="wc-sw dot" :class="topic.tint === '{{ $tint }}' ? 'on' : ''"
                                                        style="background:{{ $topicTintHex[$tint] }}" title="{{ ucfirst($tint) }}"
                                                        @click="topic.tint = '{{ $tint }}'"></button>
                                            @endforeach
                                        </div>
                                        <input type="hidden" :name="'topics[' + t + '][tint]'" :value="topic.tint">
                                    </div>

                                    <div>
                                        <span class="wc-sublabel">{{ __('ui.webchat_settings.topic_on_tap') }}</span>
                                        <div class="wc-radios">
                                            <label class="wc-rad" :class="topic.action === 'message' ? 'on' : ''">
                                                <input type="radio" :name="'topics[' + t + '][action]'" value="message" x-model="topic.action">
                                                {{ __('ui.webchat_settings.topic_action_message') }}
                                            </label>
                                            <label class="wc-rad" :class="topic.action === 'agent' ? 'on' : ''">
                                                <input type="radio" :name="'topics[' + t + '][action]'" value="agent" x-model="topic.action">
                                                {{ __('ui.webchat_settings.topic_action_agent') }}
                                            </label>
                                        </div>
                                    </div>

                                    <div class="wc-langinps">
                                        <span class="wc-sublabel">{{ __('ui.webchat_settings.topic_labels') }}</span>
                                        @foreach ($langNames as $code => $langLabel)
                                            <div class="wc-langinp" :class="form.default_lang === '{{ $code }}' ? 'dflt' : ''"
                                                 x-show="form.available_languages.includes('{{ $code }}')">
                                                <span class="tag">{{ strtoupper($code) }}<template x-if="form.default_lang === '{{ $code }}'"><span>*</span></template></span>
                                                <input class="wc-inp" type="text" maxlength="60"
                                                       :name="'topics[' + t + '][labels][{{ $code }}]'"
                                                       x-model="topic.labels['{{ $code }}']"
                                                       :placeholder="form.default_lang === '{{ $code }}' ? i18n.required : fallbackText()">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" class="wc-btn g sm wc-mt10" x-show="form.topics.length < 6" @click="addTopic()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        {{ __('ui.webchat_settings.add_topic') }}
                    </button>

                    <div class="wc-hint">{{ __('ui.webchat_settings.help_topics') }}</div>

                    @error('topics')               <div class="wc-err">{{ $message }}</div> @enderror
                    @error('topics.*')             <div class="wc-err">{{ $message }}</div> @enderror
                    @error('topics.*.labels')      <div class="wc-err">{{ $message }}</div> @enderror
                </div>
            </section>

            {{-- ── LANGUAGES ───────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'langs'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.webchat_settings.label_languages') }}</h2>
                    <p>{{ __('ui.webchat_settings.sec_languages_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.label_languages') }}</span>
                    <div class="wc-langs">
                        @foreach ($langNames as $code => $langLabel)
                            <button type="button" class="wc-lgc" :class="form.available_languages.includes('{{ $code }}') ? 'on' : ''"
                                    @click="toggleLang('{{ $code }}')">
                                <svg class="tick" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <div class="c">{{ strtoupper($code) }}</div>
                                <div class="n">{{ $langLabel }}</div>
                            </button>
                        @endforeach
                    </div>
                    <template x-for="l in form.available_languages" :key="'lang' + l">
                        <input type="hidden" name="available_languages[]" :value="l">
                    </template>
                    @error('available_languages')   <div class="wc-err">{{ $message }}</div> @enderror
                    @error('available_languages.*') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_languages') }}</div>
                </div>

                <div class="wc-fld">
                    <label for="wcDefLang">{{ __('ui.webchat_settings.label_default_lang') }}</label>
                    {{-- data-no-ss opts out of the admin layout's Select2-like enhancer
                         (initSS in layouts/admin.blade.php): it snapshots options at
                         load time, so reactive :hidden would never reach its list. --}}
                    <select id="wcDefLang" class="wc-inp wc-sel" name="default_lang" data-no-ss x-model="form.default_lang">
                        @foreach ($langNames as $code => $langLabel)
                            <option value="{{ $code }}" :hidden="!form.available_languages.includes('{{ $code }}')">{{ $langLabel }}</option>
                        @endforeach
                    </select>
                    @error('default_lang') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_default_lang') }}</div>
                </div>
            </section>

            {{-- ── INSTALL ─────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'install'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.webchat_settings.card_install') }}</h2>
                    <p>{{ __('ui.webchat_settings.sec_install_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.install_public_key') }}</span>
                    <div class="wc-keybox">
                        <div class="k" x-ref="pubkey">{{ $widget->public_key }}</div>
                        <button type="button" class="wc-btn g" @click="copy($refs.pubkey.textContent.trim(), 'key')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="9" y="9" width="12" height="12" rx="2.2" stroke="currentColor" stroke-width="2"/><path d="M15 5.5A2.5 2.5 0 0012.5 3h-7A2.5 2.5 0 003 5.5v7A2.5 2.5 0 005.5 15" stroke="currentColor" stroke-width="2"/></svg>
                            <span x-text="copied === 'key' ? i18n.copied : i18n.copy"></span>
                        </button>
                    </div>
                    <div class="wc-hint">{{ __('ui.webchat_settings.install_public_key_hint') }}</div>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.install_snippet') }}</span>
                    <div class="wc-codebox">
                        <div class="ch">
                            <span class="t">index.html</span><span class="sp"></span>
                            <button type="button" class="wc-cpbtn" @click="copy($refs.snippet.textContent, 'snippet')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><rect x="9" y="9" width="12" height="12" rx="2.2" stroke="currentColor" stroke-width="2"/><path d="M15 5.5A2.5 2.5 0 0012.5 3h-7A2.5 2.5 0 003 5.5v7A2.5 2.5 0 005.5 15" stroke="currentColor" stroke-width="2"/></svg>
                                <span x-text="copied === 'snippet' ? i18n.copied : i18n.copy"></span>
                            </button>
                        </div>
                        <pre x-ref="snippet">{{ $embedSnippet }}</pre>
                    </div>
                    <div class="wc-hint">{{ __('ui.webchat_settings.install_snippet_hint') }}</div>
                </div>

                <div class="wc-fld" x-show="!form.enabled">
                    <div class="wc-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 8v4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
                        <div>{!! __('ui.webchat_settings.note_disabled_install') !!}</div>
                    </div>
                </div>
            </section>

            {{-- ── SECURITY ────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'security'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.webchat_settings.card_security') }}</h2>
                    <p>{{ __('ui.webchat_settings.sec_security_desc') }}</p>
                </div>

                {{-- UI-007: isDomainAllowed fails closed on an empty list, so an
                     enabled widget with no origins 403s every visitor. --}}
                <div class="wc-fld" x-show="form.enabled && form.allowed_domains.length === 0">
                    <div class="wc-note danger">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 3l9.5 17H2.5L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
                        <div>{{ __('ui.webchat_settings.warn_enabled_without_domains') }}</div>
                    </div>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.webchat_settings.label_allowed_domains') }}</span>

                    <div class="wc-origins" x-show="form.allowed_domains.length > 0">
                        <template x-for="(origin, i) in form.allowed_domains" :key="'origin' + i">
                            <div class="wc-origin">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="globe"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c2.5 2.4 2.5 15.6 0 18M12 3c-2.5 2.4-2.5 15.6 0 18" stroke="currentColor" stroke-width="1.7"/></svg>
                                <span class="u" x-text="origin"></span>
                                <input type="hidden" :name="'allowed_domains[' + i + ']'" :value="origin">
                                <button type="button" class="wc-ib del" @click="form.allowed_domains.splice(i, 1)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
                            </div>
                        </template>
                    </div>

                    <div class="wc-addrow" x-show="form.allowed_domains.length < 32">
                        <input class="wc-inp" type="text" maxlength="255" x-model="newDomain"
                               @keydown.enter.prevent="addDomain()"
                               placeholder="{{ __('ui.webchat_settings.placeholder_domain') }}">
                        <button type="button" class="wc-btn g" @click="addDomain()" :disabled="!newDomain.trim()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                            {{ __('ui.webchat_settings.add') }}
                        </button>
                    </div>

                    <div class="wc-err" x-show="clientError === 'allowed_domains'">{{ __('ui.webchat_settings.err_domains_required') }}</div>
                    @error('allowed_domains')   <div class="wc-err">{{ $message }}</div> @enderror
                    @error('allowed_domains.*') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.webchat_settings.help_allowed_domains') }}</div>
                </div>

                <div class="wc-fld" x-show="form.allowed_domains.length === 0 && !form.enabled">
                    <div class="wc-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 3l9.5 17H2.5L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
                        <div>{!! __('ui.webchat_settings.note_no_origins') !!}</div>
                    </div>
                </div>
            </section>

        </div></div>

        {{-- ══ PREVIEW ══════════════════════════════════════════════ --}}
        <aside class="wc-prev" :class="previewOpen ? 'open' : ''">
            <div class="wc-pvh">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="eye"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                <span class="t">{{ __('ui.webchat_settings.preview') }}</span>
                <span class="cl" x-text="form.default_lang.toUpperCase()"></span>
            </div>

            <div class="wc-pvbody">
                <div class="wc-devsw">
                    <button type="button" :class="device === 'desktop' ? 'on' : ''" @click="device = 'desktop'">{{ __('ui.webchat_settings.device_desktop') }}</button>
                    <button type="button" :class="device === 'mobile' ? 'on' : ''"  @click="device = 'mobile'">{{ __('ui.webchat_settings.device_mobile') }}</button>
                </div>

                {{-- Disabled state --}}
                <div class="wc-pvdis" x-show="!form.enabled">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" style="opacity:.5"><path d="M2 12s3.6-7 10-7c1.4 0 2.7.3 3.8.9M22 12s-3.6 7-10 7c-1.4 0-2.7-.3-3.8-.9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <div class="t">{{ __('ui.webchat_settings.preview_disabled_title') }}</div>
                    <div class="s">{{ __('ui.webchat_settings.preview_disabled_hint') }}</div>
                </div>

                {{-- Widget mock --}}
                <template x-if="form.enabled">
                    <div class="wc-stage">
                        <div class="wc-wgt" :class="device === 'mobile' ? 'mob' : ''"
                             :style="'border-radius:' + panelRadius()" :dir="form.default_lang === 'ar' ? 'rtl' : 'ltr'">
                            <div class="wh" :style="'background:' + safeColor()">
                                <div class="m">
                                    <div class="n" x-text="form.name || 'Live Chat'"></div>
                                    <div class="s" x-show="form.header_subtitle" x-text="form.header_subtitle"></div>
                                </div>
                                <svg class="x" width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
                            </div>

                            <div class="wb">
                                <div class="wmsg" :style="bubbleStyle()" x-text="form.welcome_message"></div>

                                <div class="wchips" x-show="visibleChips().length > 0">
                                    <template x-for="(chip, i) in visibleChips()" :key="'pchip' + i">
                                        <span class="wchip" :style="'border-radius:' + (form.bubble_style === 'square' ? '3px' : '99px')" x-text="chip"></span>
                                    </template>
                                </div>

                                <div class="wtopics" x-show="form.topics.length > 0">
                                    <template x-for="(topic, i) in form.topics" :key="'ptopic' + i">
                                        <span class="wtopic" :style="'background:' + tintHex(topic.tint) + ';border-radius:' + radius()">
                                            <template x-if="topic.action === 'agent'">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="2.4"/><path d="M5.5 20a6.5 6.5 0 0113 0" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                            </template>
                                            <template x-if="topic.action !== 'agent'">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/></svg>
                                            </template>
                                            <span x-text="topicPreviewLabel(topic)"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <div class="wf">
                                <span class="fi" x-text="i18n.composer"></span>
                                <span class="sb" :style="'background:' + safeColor()"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M3 12L21 4l-8 17-2-7-8-2z" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/></svg></span>
                            </div>

                            <div class="credit" x-show="form.show_branding" x-text="i18n.poweredBy"></div>
                        </div>

                        <div class="wc-launcher" :class="form.position === 'left' ? 'left' : ''">
                            <span class="llabel" x-show="form.launcher_text" x-text="form.launcher_text"></span>
                            <span class="lbub" :style="'background:' + safeColor()">
                                @foreach (['chat', 'message', 'help', 'sparkle'] as $iconKey)
                                    <template x-if="form.launcher_icon === '{{ $iconKey }}'">
                                        @includeIf('admin.webchat._launcher-icon', ['icon' => $iconKey, 'size' => 20])
                                    </template>
                                @endforeach
                            </span>
                        </div>
                    </div>
                </template>

                <div class="wc-pvmeta">
                    <div class="kv"><span class="k">{{ __('ui.webchat_settings.meta_theme') }}</span><span class="v" x-text="form.theme_color"></span></div>
                    <div class="kv"><span class="k">{{ __('ui.webchat_settings.meta_position') }}</span><span class="v" x-text="form.position === 'left' ? i18n.posLeft : i18n.posRight"></span></div>
                    <div class="kv"><span class="k">{{ __('ui.webchat_settings.meta_corners') }}</span><span class="v" x-text="cornerLabel()"></span></div>
                    <div class="kv"><span class="k">{{ __('ui.webchat_settings.meta_languages') }}</span><span class="v" x-text="form.available_languages.map(l => l.toUpperCase()).join(' · ')"></span></div>
                </div>
            </div>
        </aside>

    </div>
</form>

    {{-- Mobile preview drawer trigger --}}
    <button type="button" class="wc-pvfab" @click="previewOpen = true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
        {{ __('ui.webchat_settings.preview') }}
    </button>
    <div class="wc-scrim" :class="previewOpen ? 'on' : ''" @click="previewOpen = false"></div>
</div>

<script>
function wcSettings() {
    return {
        form:        @json($initial),
        i18n:        @json($i18n),
        tints:       @json($topicTintHex),
        baseline:    '',
        section:     @js($openSection),
        device:      'desktop',
        previewOpen: false,
        newDomain:   '',
        saving:      false,
        copied:      null,
        clientError: null,
        dirty:       false,
        _copyTimer:  null,

        init() {
            this.normalizeTopics();
            this.baseline = JSON.stringify(this.form);
            // One deep watcher drives both the dirty pill and the live preview,
            // so every control stays a plain x-model binding.
            this.$watch('form', () => { this.dirty = JSON.stringify(this.form) !== this.baseline; }, { deep: true });
        },

        // Rows saved before a language was enabled can miss that key entirely;
        // Alpine needs the property to exist before x-model can track it.
        normalizeTopics() {
            this.form.topics = (this.form.topics || []).map(t => ({
                tint:   t.tint || 'blue',
                action: t.action === 'agent' ? 'agent' : 'message',
                labels: Object.assign({ ar: '', en: '', fr: '' }, t.labels || {}),
            }));
        },

        go(s) {
            this.section = s;
            this.clientError = null;
            if (this.$refs.formScroll) this.$refs.formScroll.scrollTop = 0;
        },

        /* ── list helpers ─────────────────────────────────────────── */
        move(list, i, dir) {
            const j = i + dir;
            if (j < 0 || j >= list.length) return;
            const [item] = list.splice(i, 1);
            list.splice(j, 0, item);
        },
        addChip() {
            if (this.form.suggestions.length >= 12) return;
            this.form.suggestions.push('');
            this.$nextTick(() => {
                const rows = this.$el.querySelectorAll('.wc-chiprow .wc-inp');
                if (rows.length) rows[rows.length - 1].focus();
            });
        },
        addTopic() {
            if (this.form.topics.length >= 6) return;
            const palette = Object.keys(this.tints);
            this.form.topics.push({
                tint:   palette[this.form.topics.length % palette.length],
                action: 'message',
                labels: { ar: '', en: '', fr: '' },
            });
        },
        addDomain() {
            let v = this.newDomain.trim().replace(/\/+$/, '');
            if (!v || this.form.allowed_domains.length >= 32) return;
            if (!/^https?:\/\//i.test(v)) v = 'https://' + v;
            if (!this.form.allowed_domains.includes(v)) this.form.allowed_domains.push(v);
            this.newDomain = '';
        },
        toggleLang(code) {
            const langs = this.form.available_languages;
            if (langs.includes(code)) {
                if (langs.length === 1) return;            // never leave the widget language-less
                this.form.available_languages = langs.filter(l => l !== code);
                if (this.form.default_lang === code) this.form.default_lang = this.form.available_languages[0];
            } else {
                const order = ['ar', 'en', 'fr'];
                this.form.available_languages = order.filter(l => langs.includes(l) || l === code);
            }
        },

        /* ── preview helpers ──────────────────────────────────────── */
        radius()      { return { soft: '10px', rounded: '18px', square: '2px' }[this.form.bubble_style] || '10px'; },
        panelRadius() { return this.form.bubble_style === 'square' ? '3px' : '16px'; },
        bubbleStyle() {
            const side = this.form.default_lang === 'ar' ? 'right' : 'left';
            return 'border-radius:' + this.radius() + ';border-bottom-' + side + '-radius:4px';
        },
        safeColor()   { return /^#[0-9a-fA-F]{6}$/.test(this.form.theme_color) ? this.form.theme_color : '#0f7e7a'; },
        hexOrDefault(v) { return /^#[0-9a-fA-F]{6}$/.test(v) ? v : '#0f7e7a'; },
        tintHex(tint) { return this.tints[tint] || this.tints.blue; },
        visibleChips() { return this.form.suggestions.filter(c => (c || '').trim() !== ''); },
        cornerLabel() {
            return { soft: this.i18n.cornerSoft, rounded: this.i18n.cornerRounded, square: this.i18n.cornerSquare }[this.form.bubble_style] || '';
        },
        fallbackText() { return this.i18n.fallbackFmt.replace('__LANG__', this.form.default_lang.toUpperCase()); },
        topicTitle(topic) {
            const l = topic.labels || {};
            return (l[this.form.default_lang] || l.en || l.ar || l.fr || '').trim() || this.i18n.untitled;
        },
        topicPreviewLabel(topic) {
            const l = topic.labels || {};
            return (l[this.form.default_lang] || l.en || l.ar || l.fr || '').trim() || this.i18n.untitled;
        },

        /* ── actions ──────────────────────────────────────────────── */
        discard() {
            this.form = JSON.parse(this.baseline);
            this.clientError = null;
            this.dirty = false;
        },

        onSubmit(e) {
            // The inactive sections are display:none, so a native `required`
            // would block submission with nothing visible. Validate here and
            // jump to the offending section instead.
            let problem = null;
            if (!this.form.name.trim())                                      problem = ['widget', 'name'];
            else if (!this.form.welcome_message.trim())                      problem = ['welcome', 'welcome_message'];
            else if (!/^#[0-9a-fA-F]{6}$/.test(this.form.theme_color))       problem = ['appear', 'theme_color'];
            else if (this.form.enabled && this.form.allowed_domains.length === 0) problem = ['security', 'allowed_domains'];

            if (problem) {
                e.preventDefault();
                this.section     = problem[0];
                this.clientError = problem[1];
                this.previewOpen = false;
                if (this.$refs.formScroll) this.$refs.formScroll.scrollTop = 0;
                return;
            }
            this.saving = true;
        },

        copy(value, target) {
            if (!value) return;
            const done = () => {
                this.copied = target;
                if (this._copyTimer) clearTimeout(this._copyTimer);
                this._copyTimer = setTimeout(() => { this.copied = null; }, 1800);
            };
            const fallback = () => {
                const t = document.createElement('textarea');
                t.value = value; document.body.appendChild(t);
                t.select(); document.execCommand('copy'); document.body.removeChild(t);
                done();
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(fallback);
            } else {
                fallback();
            }
        },
    };
}
</script>

@push('styles')
    {{-- Shared Wavadesk console shell (page head + section nav + form pane +
         preview pane). Also used by /ai-settings and /saved-replies. --}}
    <link rel="stylesheet" href="{{ asset('css/wavadesk-console.css') }}?v={{ filemtime(public_path('css/wavadesk-console.css')) }}">
@endpush
@endsection
