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
        'suggestions'        => (array)  old('suggestions', $widget->suggestions ?? []),
        'pre_chat_ask_email' => (bool)   old('pre_chat_ask_email', $widget->pre_chat_ask_email),
        'offline_message'    => (string) old('offline_message', $widget->offline_message ?? ''),
        'theme_color'        => (string) old('theme_color', $widget->theme_color),
        'position'           => (string) old('position', $widget->position),
        'launcher_text'      => (string) old('launcher_text', $widget->launcher_text ?? ''),
        'launcher_icon'      => (string) old('launcher_icon', $widget->launcher_icon ?: 'chat'),
        'bubble_style'       => (string) old('bubble_style', $widget->bubble_style ?: 'soft'),
        'show_branding'      => (bool)   old('show_branding', $widget->show_branding),
        'default_lang'       => (string) old('default_lang', $widget->default_lang ?: 'ar'),
        'available_languages'=> (array)  old('available_languages', $widget->available_languages ?: ['ar', 'en']),
        'topics'             => (array)  old('topics', $widget->topics ?? []),
        'allowed_domains'    => (array)  old('allowed_domains', $widget->allowed_domains ?? []),
    ];

    // Palette matches widget.js CSS classes (.wvch-topic-<tint>).
    $topicTints = ['blue', 'orange', 'green', 'purple', 'red', 'gray', 'teal'];
    $topicTintHex = [
        'blue' => '#3b82f6', 'orange' => '#f97316', 'green' => '#10b981',
        'purple' => '#15b6a8', 'red' => '#ef4444', 'gray' => '#6b7280', 'teal' => '#14b8a6',
    ];

    $i18n = [
        'copy'    => __('ui.webchat_settings.copy'),
        'copied'  => __('ui.webchat_settings.copied'),
    ];
@endphp

<div x-data="webchatSettings()" x-init="init()" x-cloak class="wcs">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.webchat_settings.title') }}</div>
            <div class="page-subtitle">{{ __('ui.webchat_settings.subtitle') }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route($panelPrefix . '.webchat.settings.update') }}" class="wcs-form" @submit="saving = true">
        @csrf
        @method('PUT')

        <div class="wcs-grid">

            {{-- ── LEFT COLUMN: form cards ───────────────────────────── --}}
            <div class="wcs-col-form">

                {{-- Widget card --}}
                <div class="card wcs-card">
                    <div class="card-header"><div class="card-title">{{ __('ui.webchat_settings.card_widget') }}</div></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_name') }}</label>
                            <input type="text" name="name" x-model="form.name" class="form-control @error('name') error @enderror" required maxlength="120">
                            @error('name') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-help">{{ __('ui.webchat_settings.help_name') }}</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_header_subtitle') }}</label>
                            <input type="text" name="header_subtitle" x-model="form.header_subtitle"
                                   class="form-control @error('header_subtitle') error @enderror"
                                   maxlength="160"
                                   :placeholder="'{{ __('ui.webchat_settings.placeholder_header_subtitle') }}'">
                            @error('header_subtitle') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-help">{{ __('ui.webchat_settings.help_header_subtitle') }}</div>
                        </div>

                        <div class="form-group wcs-toggle-row">
                            <label class="wcs-toggle">
                                <input type="checkbox" name="enabled" value="1" x-model="form.enabled">
                                <span class="wcs-toggle-track"><span class="wcs-toggle-thumb"></span></span>
                                <span class="wcs-toggle-labels">
                                    <span class="wcs-toggle-label">{{ __('ui.webchat_settings.label_enabled') }}</span>
                                    <span class="wcs-toggle-help">{{ __('ui.webchat_settings.help_enabled') }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Welcome card --}}
                <div class="card wcs-card">
                    <div class="card-header"><div class="card-title">{{ __('ui.webchat_settings.card_welcome') }}</div></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_welcome_message') }}</label>
                            <textarea name="welcome_message" x-model="form.welcome_message" rows="3" class="form-control @error('welcome_message') error @enderror" required maxlength="4000"></textarea>
                            @error('welcome_message') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-help">{{ __('ui.webchat_settings.help_welcome_message') }}</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_suggestions') }}</label>
                            <div class="form-help wcs-help-top">{{ __('ui.webchat_settings.help_suggestions') }}</div>

                            <div class="wcs-chips" x-show="form.suggestions.length > 0">
                                <template x-for="(chip, idx) in form.suggestions" :key="idx">
                                    <div class="wcs-chip">
                                        <input type="text"
                                               :name="'suggestions[' + idx + ']'"
                                               x-model="form.suggestions[idx]"
                                               maxlength="60"
                                               class="wcs-chip-input">
                                        <button type="button" class="wcs-chip-btn" @click="moveChipUp(idx)"    :disabled="idx === 0"                        title="↑"><i class="ri-arrow-up-s-line"></i></button>
                                        <button type="button" class="wcs-chip-btn" @click="moveChipDown(idx)"  :disabled="idx === form.suggestions.length-1" title="↓"><i class="ri-arrow-down-s-line"></i></button>
                                        <button type="button" class="wcs-chip-btn wcs-chip-btn-danger" @click="removeChip(idx)" title="×"><i class="ri-close-line"></i></button>
                                    </div>
                                </template>
                            </div>

                            <div class="wcs-chip-add" x-show="form.suggestions.length < 12">
                                <input type="text"
                                       x-model="newChip"
                                       @keydown.enter.prevent="addChip()"
                                       :placeholder="'{{ __('ui.webchat_settings.placeholder_chip') }}'"
                                       maxlength="60"
                                       class="form-control">
                                <button type="button" @click="addChip()" :disabled="!newChip.trim()" class="btn btn-outline btn-sm">
                                    <i class="ri-add-line"></i> {{ __('ui.webchat_settings.add_chip') }}
                                </button>
                            </div>
                            @error('suggestions') <div class="form-error">{{ $message }}</div> @enderror
                            @error('suggestions.*') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group wcs-toggle-row">
                            <label class="wcs-toggle">
                                <input type="checkbox" name="pre_chat_ask_email" value="1" x-model="form.pre_chat_ask_email">
                                <span class="wcs-toggle-track"><span class="wcs-toggle-thumb"></span></span>
                                <span class="wcs-toggle-labels">
                                    <span class="wcs-toggle-label">{{ __('ui.webchat_settings.label_pre_chat') }}</span>
                                </span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_offline_message') }}</label>
                            <textarea name="offline_message" x-model="form.offline_message" rows="2" class="form-control @error('offline_message') error @enderror" maxlength="4000"></textarea>
                            @error('offline_message') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-help">{{ __('ui.webchat_settings.help_offline_message') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Appearance card --}}
                <div class="card wcs-card">
                    <div class="card-header"><div class="card-title">{{ __('ui.webchat_settings.card_appearance') }}</div></div>
                    <div class="card-body">
                        <div class="wcs-inline-fields">
                            <div class="form-group wcs-color-group">
                                <label class="form-label">{{ __('ui.webchat_settings.label_theme_color') }}</label>
                                <div class="wcs-color-row">
                                    <input type="color" x-model="form.theme_color" class="wcs-color-swatch" aria-label="color picker">
                                    <input type="text" name="theme_color" x-model="form.theme_color" class="form-control @error('theme_color') error @enderror" pattern="^#[0-9a-fA-F]{6}$" required maxlength="7">
                                </div>
                                @error('theme_color') <div class="form-error">{{ $message }}</div> @enderror
                                <div class="form-help">{{ __('ui.webchat_settings.help_theme_color') }}</div>
                            </div>

                            <div class="form-group wcs-position-group">
                                <label class="form-label">{{ __('ui.webchat_settings.label_position') }}</label>
                                <div class="wcs-radio-row">
                                    <label class="wcs-radio" :class="form.position === 'left' ? 'wcs-radio-active' : ''">
                                        <input type="radio" name="position" value="left" x-model="form.position">
                                        <i class="ri-arrow-left-down-line"></i>
                                        <span>{{ __('ui.webchat_settings.position_left') }}</span>
                                    </label>
                                    <label class="wcs-radio" :class="form.position === 'right' ? 'wcs-radio-active' : ''">
                                        <input type="radio" name="position" value="right" x-model="form.position">
                                        <i class="ri-arrow-right-down-line"></i>
                                        <span>{{ __('ui.webchat_settings.position_right') }}</span>
                                    </label>
                                </div>
                                @error('position') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_launcher_text') }}</label>
                            <input type="text" name="launcher_text" x-model="form.launcher_text" class="form-control @error('launcher_text') error @enderror" maxlength="120" :placeholder="'{{ __('ui.webchat_settings.placeholder_launcher') }}'">
                            @error('launcher_text') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-help">{{ __('ui.webchat_settings.help_launcher_text') }}</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_launcher_icon') }}</label>
                            <div class="wcs-icon-row">
                                @php
                                    $iconOptions = [
                                        'chat'    => 'ri-chat-3-line',
                                        'message' => 'ri-message-2-line',
                                        'help'    => 'ri-question-line',
                                        'sparkle' => 'ri-sparkling-2-line',
                                    ];
                                @endphp
                                @foreach ($iconOptions as $key => $rmi)
                                    <label class="wcs-icon-tile" :class="form.launcher_icon === '{{ $key }}' ? 'wcs-icon-tile-active' : ''">
                                        <input type="radio" name="launcher_icon" value="{{ $key }}" x-model="form.launcher_icon">
                                        <i class="{{ $rmi }}"></i>
                                        <span>{{ __('ui.webchat_settings.launcher_icon_' . $key) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('launcher_icon') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-help">{{ __('ui.webchat_settings.help_launcher_icon') }}</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_bubble_style') }}</label>
                            <div class="wcs-radio-row wcs-radio-row-3">
                                @foreach (['soft', 'rounded', 'square'] as $style)
                                    <label class="wcs-radio" :class="form.bubble_style === '{{ $style }}' ? 'wcs-radio-active' : ''">
                                        <input type="radio" name="bubble_style" value="{{ $style }}" x-model="form.bubble_style">
                                        <span class="wcs-bubble-preview wcs-bubble-preview-{{ $style }}"></span>
                                        <span>{{ __('ui.webchat_settings.bubble_style_' . $style) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('bubble_style') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-help">{{ __('ui.webchat_settings.help_bubble_style') }}</div>
                        </div>

                        <div class="form-group wcs-toggle-row">
                            <label class="wcs-toggle">
                                <input type="checkbox" name="show_branding" value="1" x-model="form.show_branding">
                                <span class="wcs-toggle-track"><span class="wcs-toggle-thumb"></span></span>
                                <span class="wcs-toggle-labels">
                                    <span class="wcs-toggle-label">{{ __('ui.webchat_settings.label_show_branding') }}</span>
                                    <span class="wcs-toggle-help">{{ __('ui.webchat_settings.help_show_branding') }}</span>
                                </span>
                            </label>
                        </div>

                        {{-- Languages --}}
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_languages') }}</label>
                            <div class="form-help wcs-help-top">{{ __('ui.webchat_settings.help_languages') }}</div>
                            <div class="wcs-lang-grid">
                                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                                    <label class="wcs-lang-tile"
                                           :class="form.available_languages.includes('{{ $code }}') ? 'wcs-lang-tile-active' : ''">
                                        <input type="checkbox"
                                               name="available_languages[]"
                                               value="{{ $code }}"
                                               x-model="form.available_languages"
                                               @change="ensureDefaultLangInList()">
                                        <span class="wcs-lang-code">{{ strtoupper($code) }}</span>
                                        <span class="wcs-lang-label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('available_languages') <div class="form-error">{{ $message }}</div> @enderror
                            @error('available_languages.*') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_default_lang') }}</label>
                            {{-- data-no-ss opts out of the admin layout's Select2-like
                                 enhancer (see initSS in layouts/admin.blade.php). That
                                 enhancer snapshots options at load time, so x-show on
                                 <option> tags never reaches the visible list AND its
                                 dropdown was being clipped by the card overflow.
                                 :hidden reactively removes disabled languages from
                                 the native dropdown as tenants toggle checkboxes. --}}
                            <select name="default_lang" x-model="form.default_lang" data-no-ss class="form-control">
                                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                                    <option value="{{ $code }}"
                                            :hidden="!form.available_languages.includes('{{ $code }}')">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-help">{{ __('ui.webchat_settings.help_default_lang') }}</div>
                            @error('default_lang') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- Welcome topics card --}}
                <div class="card wcs-card">
                    <div class="card-header">
                        <div class="card-title">{{ __('ui.webchat_settings.card_topics') }}</div>
                    </div>
                    <div class="card-body">
                        <div class="form-help wcs-help-top">{{ __('ui.webchat_settings.help_topics') }}</div>

                        {{-- Existing topic rows --}}
                        <div class="wcs-topics" x-show="form.topics.length > 0">
                            <template x-for="(topic, tIdx) in form.topics" :key="tIdx">
                                <div class="wcs-topic-row">
                                    <div class="wcs-topic-head">
                                        <span class="wcs-topic-index" x-text="tIdx + 1"></span>

                                        {{-- Tint picker --}}
                                        <div class="wcs-tint-picker">
                                            @foreach ($topicTints as $tint)
                                                <button type="button"
                                                        class="wcs-tint-dot wcs-tint-dot-{{ $tint }}"
                                                        :class="topic.tint === '{{ $tint }}' ? 'wcs-tint-dot-active' : ''"
                                                        @click="topic.tint = '{{ $tint }}'"
                                                        :aria-label="'{{ ucfirst($tint) }}'"
                                                        title="{{ ucfirst($tint) }}"></button>
                                            @endforeach
                                            <input type="hidden"
                                                   :name="'topics[' + tIdx + '][tint]'"
                                                   :value="topic.tint">
                                        </div>

                                        <div class="wcs-topic-actions">
                                            <button type="button" class="wcs-chip-btn" @click="moveTopicUp(tIdx)"   :disabled="tIdx === 0"                       title="↑"><i class="ri-arrow-up-s-line"></i></button>
                                            <button type="button" class="wcs-chip-btn" @click="moveTopicDown(tIdx)" :disabled="tIdx === form.topics.length - 1" title="↓"><i class="ri-arrow-down-s-line"></i></button>
                                            <button type="button" class="wcs-chip-btn wcs-chip-btn-danger" @click="removeTopic(tIdx)" title="×"><i class="ri-close-line"></i></button>
                                        </div>
                                    </div>

                                    {{-- Action selector: sends the label as a message OR triggers request-agent --}}
                                    <div class="wcs-topic-action-row">
                                        <label class="wcs-topic-action-opt">
                                            <input type="radio" :name="'topics[' + tIdx + '][action]'" value="message" x-model="topic.action">
                                            <span>{{ __('ui.webchat_settings.topic_action_message') }}</span>
                                        </label>
                                        <label class="wcs-topic-action-opt">
                                            <input type="radio" :name="'topics[' + tIdx + '][action]'" value="agent" x-model="topic.action">
                                            <span>{{ __('ui.webchat_settings.topic_action_agent') }}</span>
                                        </label>
                                    </div>

                                    {{-- Per-language label inputs (only for enabled languages) --}}
                                    <div class="wcs-topic-labels">
                                        @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $langLabel)
                                            <div class="wcs-topic-label-group"
                                                 x-show="form.available_languages.includes('{{ $code }}')">
                                                <label class="wcs-topic-label-tag">{{ strtoupper($code) }}</label>
                                                <input type="text"
                                                       :name="'topics[' + tIdx + '][labels][{{ $code }}]'"
                                                       x-model="topic.labels['{{ $code }}']"
                                                       maxlength="60"
                                                       placeholder="{{ $langLabel }}"
                                                       class="form-control wcs-topic-label-input">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="wcs-topics-add" x-show="form.topics.length < 6">
                            <button type="button" class="btn btn-outline btn-sm" @click="addTopic()">
                                <i class="ri-add-line"></i> {{ __('ui.webchat_settings.add_topic') }}
                            </button>
                        </div>

                        @error('topics') <div class="form-error">{{ $message }}</div> @enderror
                        @error('topics.*') <div class="form-error">{{ $message }}</div> @enderror
                        @error('topics.*.labels') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Security card --}}
                <div class="card wcs-card">
                    <div class="card-header"><div class="card-title">{{ __('ui.webchat_settings.card_security') }}</div></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.label_allowed_domains') }}</label>
                            <div class="form-help wcs-help-top">{{ __('ui.webchat_settings.help_allowed_domains') }}</div>

                            {{-- UI-007: warn when the widget is enabled but no
                                 domain is configured. isDomainAllowed fails
                                 closed on empty, so this state 403s every
                                 visitor — the tenant needs to know before they
                                 walk away from the page. --}}
                            <div class="alert alert-warning" style="margin:8px 0;padding:10px 12px;border-radius:8px;background:#fef3c7;color:#92400e;font-size:13.5px"
                                 x-show="form.enabled && form.allowed_domains.length === 0">
                                <i class="ri-error-warning-line"></i>
                                {{ __('ui.webchat_settings.warn_enabled_without_domains') }}
                            </div>

                            <div class="wcs-domains" x-show="form.allowed_domains.length > 0">
                                <template x-for="(dom, idx) in form.allowed_domains" :key="idx">
                                    <div class="wcs-domain">
                                        <i class="ri-global-line wcs-domain-icon"></i>
                                        <input type="url"
                                               :name="'allowed_domains[' + idx + ']'"
                                               x-model="form.allowed_domains[idx]"
                                               maxlength="255"
                                               class="wcs-domain-input">
                                        <button type="button" class="wcs-chip-btn wcs-chip-btn-danger" @click="removeDomain(idx)"><i class="ri-close-line"></i></button>
                                    </div>
                                </template>
                            </div>

                            <div class="wcs-chip-add" x-show="form.allowed_domains.length < 32">
                                <input type="url"
                                       x-model="newDomain"
                                       @keydown.enter.prevent="addDomain()"
                                       :placeholder="'{{ __('ui.webchat_settings.placeholder_domain') }}'"
                                       maxlength="255"
                                       class="form-control">
                                <button type="button" @click="addDomain()" :disabled="!newDomain.trim()" class="btn btn-outline btn-sm">
                                    <i class="ri-add-line"></i> {{ __('ui.webchat_settings.add_domain') }}
                                </button>
                            </div>
                            @error('allowed_domains') <div class="form-error">{{ $message }}</div> @enderror
                            @error('allowed_domains.*') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="wcs-actions">
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <i class="ri-save-line"></i>
                        <span>{{ __('ui.webchat_settings.save') }}</span>
                    </button>
                </div>
            </div>

            {{-- ── RIGHT COLUMN: install + preview ───────────────────── --}}
            <div class="wcs-col-side">

                {{-- Install card --}}
                <div class="card wcs-card">
                    <div class="card-header"><div class="card-title"><i class="ri-code-s-slash-line"></i> {{ __('ui.webchat_settings.card_install') }}</div></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.install_public_key') }}</label>
                            <div class="wcs-copy-row">
                                <input type="text" readonly value="{{ $widget->public_key }}" class="form-control wcs-mono" x-ref="keyInput">
                                <button type="button" class="btn btn-outline btn-sm" @click="copy($refs.keyInput.value, 'key')">
                                    <i class="ri-clipboard-line"></i>
                                    <span x-text="copiedTarget === 'key' ? i18n.copied : i18n.copy"></span>
                                </button>
                            </div>
                            <div class="form-help">{{ __('ui.webchat_settings.install_public_key_hint') }}</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.webchat_settings.install_snippet') }}</label>
                            <textarea readonly rows="4" class="form-control wcs-mono wcs-snippet" x-ref="snippet">{{ $embedSnippet }}</textarea>
                            <div class="wcs-copy-row">
                                <button type="button" class="btn btn-outline btn-sm" @click="copy($refs.snippet.value, 'snippet')">
                                    <i class="ri-clipboard-line"></i>
                                    <span x-text="copiedTarget === 'snippet' ? i18n.copied : i18n.copy"></span>
                                </button>
                            </div>
                            <div class="form-help">{{ __('ui.webchat_settings.install_snippet_hint') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Preview card --}}
                <div class="card wcs-card">
                    <div class="card-header"><div class="card-title"><i class="ri-eye-line"></i> {{ __('ui.webchat_settings.card_preview') }}</div></div>
                    <div class="card-body">
                        <div class="wcs-preview-stage"
                             :class="['wcs-preview-' + form.position, 'wcs-preview-bubble-' + form.bubble_style]">
                            <div class="wcs-preview-card" x-show="form.enabled">
                                <div class="wcs-preview-header" :style="'background:' + form.theme_color">
                                    <div class="wcs-preview-header-inner">
                                        <div>
                                            <div class="wcs-preview-title" x-text="form.name || 'Live Chat'"></div>
                                            <div class="wcs-preview-subtitle" x-show="form.header_subtitle" x-text="form.header_subtitle"></div>
                                        </div>
                                        <div class="wcs-preview-close"><i class="ri-close-line"></i></div>
                                    </div>
                                </div>
                                <div class="wcs-preview-body">
                                    <div class="wcs-preview-welcome" x-text="form.welcome_message || ' '"></div>
                                    <div class="wcs-preview-chips" x-show="form.suggestions.length > 0">
                                        <template x-for="chip in form.suggestions.slice(0, 6)" :key="chip">
                                            <button type="button" class="wcs-preview-chip" :style="'border-color:' + form.theme_color + '; color:' + form.theme_color" x-text="chip"></button>
                                        </template>
                                    </div>
                                </div>
                                <div class="wcs-preview-branding" x-show="form.show_branding">
                                    <i class="ri-flashlight-line"></i>
                                    <span>Powered by <b>{{ config('app.name', 'wavadesk') }}</b></span>
                                </div>
                            </div>

                            <div class="wcs-preview-launcher" :style="'background:' + form.theme_color" x-show="form.enabled">
                                <template x-if="form.launcher_text">
                                    <span class="wcs-preview-launcher-text" x-text="form.launcher_text"></span>
                                </template>
                                <i class="wcs-preview-launcher-icon"
                                   :class="{
                                       'ri-chat-3-line':      form.launcher_icon === 'chat',
                                       'ri-message-2-line':   form.launcher_icon === 'message',
                                       'ri-question-line':    form.launcher_icon === 'help',
                                       'ri-sparkling-2-line': form.launcher_icon === 'sparkle'
                                   }"></i>
                            </div>

                            <div class="wcs-preview-disabled" x-show="!form.enabled">
                                <i class="ri-toggle-line"></i>
                                <span>Widget disabled</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>

<script>
function webchatSettings() {
    return {
        form:         @json($initial),
        i18n:         @json($i18n),
        newChip:      '',
        newDomain:    '',
        saving:       false,
        copiedTarget: null,
        _copiedTimer: null,

        init() {
            // no-op; Alpine handles reactive bindings
        },

        addChip() {
            const v = this.newChip.trim();
            if (!v || this.form.suggestions.length >= 12) return;
            this.form.suggestions.push(v);
            this.newChip = '';
        },
        removeChip(idx) { this.form.suggestions.splice(idx, 1); },
        moveChipUp(idx) {
            if (idx <= 0) return;
            const [c] = this.form.suggestions.splice(idx, 1);
            this.form.suggestions.splice(idx - 1, 0, c);
        },
        moveChipDown(idx) {
            if (idx >= this.form.suggestions.length - 1) return;
            const [c] = this.form.suggestions.splice(idx, 1);
            this.form.suggestions.splice(idx + 1, 0, c);
        },

        addDomain() {
            const v = this.newDomain.trim();
            if (!v || this.form.allowed_domains.length >= 32) return;
            this.form.allowed_domains.push(v);
            this.newDomain = '';
        },
        removeDomain(idx) { this.form.allowed_domains.splice(idx, 1); },

        addTopic() {
            if (this.form.topics.length >= 6) return;
            // Rotate the default tint so successive rows get distinct colors.
            const palette = ['blue','orange','green','purple','red','gray','teal'];
            const tint    = palette[this.form.topics.length % palette.length];
            this.form.topics.push({
                tint:   tint,
                action: 'message',
                labels: { ar: '', en: '', fr: '' },
            });
        },
        removeTopic(idx) { this.form.topics.splice(idx, 1); },
        moveTopicUp(idx) {
            if (idx <= 0) return;
            const [t] = this.form.topics.splice(idx, 1);
            this.form.topics.splice(idx - 1, 0, t);
        },
        moveTopicDown(idx) {
            if (idx >= this.form.topics.length - 1) return;
            const [t] = this.form.topics.splice(idx, 1);
            this.form.topics.splice(idx + 1, 0, t);
        },

        ensureDefaultLangInList() {
            // Keep at least one language enabled + make sure default_lang is
            // in the enabled set (otherwise fall back to the first enabled).
            if (this.form.available_languages.length === 0) {
                this.form.available_languages = [this.form.default_lang || 'ar'];
            }
            if (!this.form.available_languages.includes(this.form.default_lang)) {
                this.form.default_lang = this.form.available_languages[0];
            }
        },

        copy(value, target) {
            if (!value) return;
            const done = () => {
                this.copiedTarget = target;
                if (this._copiedTimer) clearTimeout(this._copiedTimer);
                this._copiedTimer = setTimeout(() => { this.copiedTarget = null; }, 1800);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(() => {
                    // Fallback via a temporary textarea (older browsers, insecure contexts)
                    const t = document.createElement('textarea');
                    t.value = value; document.body.appendChild(t);
                    t.select(); document.execCommand('copy'); document.body.removeChild(t);
                    done();
                });
            } else {
                const t = document.createElement('textarea');
                t.value = value; document.body.appendChild(t);
                t.select(); document.execCommand('copy'); document.body.removeChild(t);
                done();
            }
        },
    };
}
</script>

@push('styles')
<style>
    /* ── WebChat settings (wcs-) scoped styles ──────────────────────── */
    .wcs { display: flex; flex-direction: column; }
    .wcs-form { display: block; }
    .wcs-grid { display: grid; grid-template-columns: 1fr 380px; gap: 1rem; margin-top: 1rem; }
    @media (max-width: 1200px) { .wcs-grid { grid-template-columns: 1fr; } }

    .wcs-col-form, .wcs-col-side { display: flex; flex-direction: column; gap: 1rem; }

    .wcs-card { border-radius: var(--radius-lg); }
    .wcs-help-top { margin-bottom: .5rem; }
    .wcs-inline-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) { .wcs-inline-fields { grid-template-columns: 1fr; } }

    /* Toggle switch */
    .wcs-toggle-row { padding: .5rem 0; }
    .wcs-toggle { display: flex; align-items: center; gap: .75rem; cursor: pointer; }
    .wcs-toggle input { position: absolute; opacity: 0; pointer-events: none; }
    .wcs-toggle-track {
        width: 40px; height: 22px; border-radius: 999px; background: var(--gray-bg);
        position: relative; transition: var(--transition); flex-shrink: 0;
    }
    .wcs-toggle-thumb {
        position: absolute; top: 2px; left: 2px;
        width: 18px; height: 18px; border-radius: 50%; background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,.15); transition: var(--transition);
    }
    .wcs-toggle input:checked + .wcs-toggle-track                { background: var(--brand); }
    .wcs-toggle input:checked + .wcs-toggle-track .wcs-toggle-thumb { transform: translateX(18px); }
    .wcs-toggle-labels { display: flex; flex-direction: column; gap: .125rem; }
    .wcs-toggle-label  { font-size: .875rem; font-weight: 500; color: var(--text-primary); }
    .wcs-toggle-help   { font-size: .75rem; color: var(--text-muted); }

    /* Chips */
    .wcs-chips { display: flex; flex-direction: column; gap: .5rem; margin-bottom: .75rem; }
    .wcs-chip  { display: flex; align-items: center; gap: .375rem;
        background: var(--page-bg); border: 1px solid var(--card-border); border-radius: var(--radius);
        padding: .25rem .375rem;
    }
    .wcs-chip-input { flex: 1; border: none; background: transparent; padding: .25rem .5rem; font-size: .875rem; outline: none; }
    .wcs-chip-btn   {
        border: none; background: transparent; cursor: pointer;
        color: var(--text-muted); padding: .25rem; border-radius: .25rem;
        display: inline-flex; align-items: center; justify-content: center;
        transition: var(--transition);
    }
    .wcs-chip-btn:hover:not(:disabled) { background: var(--card-bg); color: var(--text-primary); }
    .wcs-chip-btn:disabled { opacity: .3; cursor: not-allowed; }
    .wcs-chip-btn-danger:hover:not(:disabled) { color: var(--red); }

    .wcs-chip-add { display: flex; gap: .5rem; }
    .wcs-chip-add .form-control { flex: 1; }

    /* Domains */
    .wcs-domains { display: flex; flex-direction: column; gap: .375rem; margin-bottom: .75rem; }
    .wcs-domain  {
        display: flex; align-items: center; gap: .5rem;
        background: var(--page-bg); border: 1px solid var(--card-border); border-radius: var(--radius);
        padding: .375rem .625rem;
    }
    .wcs-domain-icon  { color: var(--text-muted); flex-shrink: 0; }
    .wcs-domain-input {
        flex: 1; border: none; background: transparent; font-family: var(--font-mono, ui-monospace, monospace);
        font-size: .8125rem; outline: none; padding: .125rem 0;
    }

    /* Color picker */
    .wcs-color-row { display: flex; gap: .5rem; align-items: center; }
    .wcs-color-swatch {
        width: 44px; height: 38px; border: 1px solid var(--card-border); border-radius: var(--radius);
        cursor: pointer; padding: 0; background: transparent;
    }
    .wcs-color-row .form-control { font-family: var(--font-mono, ui-monospace, monospace); text-transform: lowercase; }

    /* Position radio */
    .wcs-radio-row { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
    .wcs-radio-row-3 { grid-template-columns: repeat(3, 1fr); }
    .wcs-radio {
        display: flex; align-items: center; gap: .5rem;
        border: 1px solid var(--card-border); border-radius: var(--radius);
        padding: .625rem .75rem; cursor: pointer;
        font-size: .875rem; color: var(--text-primary);
        transition: var(--transition);
    }
    .wcs-radio input { position: absolute; opacity: 0; pointer-events: none; }
    .wcs-radio:hover        { background: var(--page-bg); }
    .wcs-radio-active       { border-color: var(--brand); background: var(--brand-xlight); color: var(--brand-dark); }
    .wcs-radio-active i     { color: var(--brand); }

    /* Bubble-shape swatch inside the bubble_style radio */
    .wcs-bubble-preview {
        display: inline-block; width: 26px; height: 16px;
        background: var(--brand-xlight); border: 1px solid var(--brand);
        flex-shrink: 0;
    }
    .wcs-bubble-preview-soft    { border-radius: 6px; }
    .wcs-bubble-preview-rounded { border-radius: 14px; }
    .wcs-bubble-preview-square  { border-radius: 2px; }

    /* Icon-picker tiles */
    .wcs-icon-row  { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem; }
    .wcs-icon-tile {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .25rem; padding: .625rem .375rem;
        border: 1px solid var(--card-border); border-radius: var(--radius);
        cursor: pointer; font-size: .75rem; color: var(--text-primary);
        transition: var(--transition);
    }
    .wcs-icon-tile input   { position: absolute; opacity: 0; pointer-events: none; }
    .wcs-icon-tile i       { font-size: 1.25rem; color: var(--text-muted); }
    .wcs-icon-tile:hover                { background: var(--page-bg); }
    .wcs-icon-tile-active               { border-color: var(--brand); background: var(--brand-xlight); color: var(--brand-dark); }
    .wcs-icon-tile-active i             { color: var(--brand); }
    @media (max-width: 480px) { .wcs-icon-row { grid-template-columns: repeat(2, 1fr); } }

    /* Language multi-select tiles */
    .wcs-lang-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
    .wcs-lang-tile {
        position: relative;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .125rem; padding: .625rem .375rem;
        border: 1px solid var(--card-border); border-radius: var(--radius);
        cursor: pointer; font-size: .75rem; color: var(--text-primary);
        transition: var(--transition); background: var(--card-bg);
    }
    .wcs-lang-tile input { position: absolute; opacity: 0; pointer-events: none; }
    .wcs-lang-code  { font-size: .95rem; font-weight: 700; letter-spacing: .04em; color: var(--text-muted); }
    .wcs-lang-label { font-size: .75rem; color: var(--text-secondary); }
    .wcs-lang-tile:hover              { background: var(--page-bg); }
    .wcs-lang-tile-active             { border-color: var(--brand); background: var(--brand-xlight); }
    .wcs-lang-tile-active .wcs-lang-code  { color: var(--brand); }
    .wcs-lang-tile-active .wcs-lang-label { color: var(--brand-dark); }
    @media (max-width: 480px) { .wcs-lang-grid { grid-template-columns: repeat(2, 1fr); } }

    /* Copy row + snippet */
    .wcs-copy-row { display: flex; gap: .5rem; align-items: center; margin-top: .375rem; }
    .wcs-copy-row .form-control { flex: 1; }
    .wcs-mono { font-family: var(--font-mono, ui-monospace, monospace); font-size: .8125rem; }
    .wcs-snippet { resize: none; white-space: pre; overflow-x: auto; margin-bottom: .375rem; }

    .wcs-actions { display: flex; justify-content: flex-end; padding-top: .5rem; }

    /* Preview */
    .wcs-preview-stage {
        position: relative; height: 320px; border-radius: var(--radius-lg);
        background: linear-gradient(135deg, var(--page-bg), var(--card-bg));
        border: 1px dashed var(--card-border); overflow: hidden;
    }
    .wcs-preview-card {
        position: absolute; top: 20px; width: calc(100% - 32px); left: 16px;
        max-height: 200px; background: var(--card-bg); border: 1px solid var(--card-border);
        border-radius: var(--radius); box-shadow: 0 8px 24px rgba(0,0,0,.08);
        overflow: hidden; display: flex; flex-direction: column;
    }
    .wcs-preview-header       { color: #fff; padding: .625rem .875rem; }
    .wcs-preview-header-inner { display: flex; justify-content: space-between; align-items: center; }
    .wcs-preview-title        { font-size: .875rem; font-weight: 600; }
    .wcs-preview-subtitle     { font-size: .6875rem; opacity: .85; margin-top: 2px; line-height: 1.2;
                                overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }
    .wcs-preview-close        { opacity: .8; font-size: 1rem; }
    .wcs-preview-body         { padding: .75rem .875rem; background: var(--card-bg); }
    .wcs-preview-welcome      { font-size: .8125rem; color: var(--text-primary); margin-bottom: .5rem; line-height: 1.4;
                                display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .wcs-preview-chips        { display: flex; flex-wrap: wrap; gap: .25rem; }
    .wcs-preview-chip         {
        background: transparent; border: 1px solid; border-radius: 999px;
        padding: .1875rem .625rem; font-size: .6875rem; cursor: default;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: 130px;
    }
    .wcs-preview-branding     {
        display: flex; align-items: center; justify-content: center; gap: .25rem;
        padding: .375rem; font-size: .625rem; color: var(--text-muted);
        background: var(--card-bg); border-top: 1px solid var(--card-border);
    }
    .wcs-preview-branding i   { font-size: .75rem; }

    /* Bubble-style variants applied to the preview card */
    .wcs-preview-bubble-soft    .wcs-preview-card { border-radius: 12px; }
    .wcs-preview-bubble-rounded .wcs-preview-card { border-radius: 20px; }
    .wcs-preview-bubble-square  .wcs-preview-card { border-radius: 4px; }
    .wcs-preview-bubble-soft    .wcs-preview-chip { border-radius: 999px; }
    .wcs-preview-bubble-rounded .wcs-preview-chip { border-radius: 999px; }
    .wcs-preview-bubble-square  .wcs-preview-chip { border-radius: 4px; }

    .wcs-preview-launcher {
        position: absolute; bottom: 20px; height: 48px;
        border-radius: 999px; color: #fff; padding: 0 1rem;
        display: inline-flex; align-items: center; gap: .5rem;
        box-shadow: 0 6px 18px rgba(0,0,0,.15); cursor: pointer;
    }
    .wcs-preview-launcher-icon { font-size: 1.375rem; }
    .wcs-preview-launcher-text { font-size: .8125rem; font-weight: 500; }
    .wcs-preview-left  .wcs-preview-launcher { left:  16px; }
    .wcs-preview-right .wcs-preview-launcher { right: 16px; }

    [dir="rtl"] .wcs-preview-left  .wcs-preview-launcher { left: auto; right: 16px; }
    [dir="rtl"] .wcs-preview-right .wcs-preview-launcher { right: auto; left: 16px; }

    .wcs-preview-disabled {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        flex-direction: column; gap: .5rem; color: var(--text-muted); font-size: .875rem;
    }
    .wcs-preview-disabled i { font-size: 2rem; opacity: .4; }

    /* ── Welcome topics editor ──────────────────────────────────────── */
    .wcs-topics { display: flex; flex-direction: column; gap: .75rem; margin-top: .75rem; }
    .wcs-topic-row {
        border: 1px solid var(--card-border, #e5e7eb);
        border-radius: 10px;
        padding: .75rem;
        background: var(--card-bg, #fff);
        display: flex; flex-direction: column; gap: .625rem;
    }
    .wcs-topic-head {
        display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
    }
    .wcs-topic-index {
        width: 24px; height: 24px; border-radius: 999px;
        background: var(--brand-soft, #ecf7f6); color: var(--brand, #0f7e7a);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .8125rem; font-weight: 700;
    }
    .wcs-topic-actions { margin-inline-start: auto; display: flex; gap: .25rem; }

    .wcs-tint-picker { display: inline-flex; gap: .3125rem; align-items: center; }
    .wcs-tint-dot {
        width: 22px; height: 22px; border-radius: 999px;
        border: 2px solid transparent;
        padding: 0; cursor: pointer;
        transition: transform .12s ease, border-color .12s ease;
    }
    .wcs-tint-dot:hover { transform: scale(1.1); }
    .wcs-tint-dot-active { border-color: var(--text-primary, #0f172a); transform: scale(1.1); }
    .wcs-tint-dot-blue   { background: #3b82f6; }
    .wcs-tint-dot-orange { background: #f97316; }
    .wcs-tint-dot-green  { background: #10b981; }
    .wcs-tint-dot-purple { background: #15b6a8; }
    .wcs-tint-dot-red    { background: #ef4444; }
    .wcs-tint-dot-gray   { background: #6b7280; }
    .wcs-tint-dot-teal   { background: #14b8a6; }

    .wcs-topic-action-row { display: flex; gap: .75rem; flex-wrap: wrap; font-size: .8125rem; }
    .wcs-topic-action-opt {
        display: inline-flex; align-items: center; gap: .375rem;
        color: var(--text-primary, #0f172a); cursor: pointer;
    }
    .wcs-topic-action-opt input { accent-color: var(--brand, #0f7e7a); }

    .wcs-topic-labels { display: flex; flex-direction: column; gap: .375rem; }
    .wcs-topic-label-group {
        display: grid; grid-template-columns: 42px 1fr; gap: .5rem; align-items: center;
    }
    .wcs-topic-label-tag {
        font-size: .6875rem; font-weight: 700; letter-spacing: .05em;
        color: var(--text-muted, #64748b); text-align: center;
    }
    .wcs-topic-label-input { font-size: .8125rem; }

    .wcs-topics-add { margin-top: .75rem; }
</style>
@endpush
@endsection
