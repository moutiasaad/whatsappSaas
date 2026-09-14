@extends('layouts.admin')

@section('title', __('ui.ai_settings_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.ai_settings_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();

    $languages = [
        'auto' => ['label' => __('ui.ai_settings_page.lang_auto'), 'flag' => '🌐', 'code' => 'Auto'],
        'fr'   => ['label' => __('ui.ai_settings_page.lang_fr'),   'flag' => '🇫🇷', 'code' => 'FR'],
        'en'   => ['label' => __('ui.ai_settings_page.lang_en'),   'flag' => '🇬🇧', 'code' => 'EN'],
        'ar'   => ['label' => __('ui.ai_settings_page.lang_ar'),   'flag' => '🇸🇦', 'code' => 'AR'],
        'es'   => ['label' => __('ui.ai_settings_page.lang_es'),   'flag' => '🇪🇸', 'code' => 'ES'],
        'pt'   => ['label' => __('ui.ai_settings_page.lang_pt'),   'flag' => '🇵🇹', 'code' => 'PT'],
        'de'   => ['label' => __('ui.ai_settings_page.lang_de'),   'flag' => '🇩🇪', 'code' => 'DE'],
        'it'   => ['label' => __('ui.ai_settings_page.lang_it'),   'flag' => '🇮🇹', 'code' => 'IT'],
    ];

    $modes = [
        'off'        => ['color' => '#6b7280', 'icon' => 'ri-close-circle-line'],
        'suggestion' => ['color' => '#f59e0b', 'icon' => 'ri-lightbulb-flash-line'],
        'autonomous' => ['color' => '#10b981', 'icon' => 'ri-flashlight-line'],
        'hybrid'     => ['color' => '#15b6a8', 'icon' => 'ri-git-branch-line'],
    ];

    $initial = [
        'mode'                => (string) old('mode', $settings->mode),
        'whatsapp_enabled'    => (bool)   old('whatsapp_enabled',   $settings->whatsapp_enabled ?? true),
        'webchat_enabled'     => (bool)   old('webchat_enabled',    $settings->webchat_enabled ?? true),
        'reply_when_claimed'  => (bool)   old('reply_when_claimed', $settings->reply_when_claimed ?? false),
        'reply_language'      => (string) old('reply_language',     $settings->reply_language ?? 'auto'),
        'suggestion_count'    => (int)    old('suggestion_count',   $settings->suggestion_count ?? 3),
        'system_prompt'       => (string) old('system_prompt',      $settings->system_prompt ?? ''),
        // The field posts as a JSON string (the controller json_decodes it), so
        // a failed validation round-trip has to be decoded back into an array.
        'escalation_keywords' => array_values(
            is_string(old('escalation_keywords'))
                ? (array) (json_decode(old('escalation_keywords'), true) ?: [])
                : (array) ($settings->escalation_keywords ?? [])
        ),
    ];

    // A section is display:none unless active, so a validation error inside a
    // collapsed one would be invisible. Open the section that failed.
    $sectionOfField = [
        'mode' => 'mode',
        'whatsapp_enabled' => 'channels', 'webchat_enabled' => 'channels', 'reply_when_claimed' => 'channels',
        'reply_language' => 'replies', 'suggestion_count' => 'replies',
        'system_prompt' => 'prompt',
        'escalation_keywords' => 'escalation',
    ];
    $openSection = 'mode';
    foreach ($errors->keys() as $key) {
        $root = explode('.', $key)[0];
        if (isset($sectionOfField[$root])) { $openSection = $sectionOfField[$root]; break; }
    }

    // Read-only allowance, in AI replies per month. Set by the plan:
    // null = unlimited, 0 = AI off on this plan, N = hard cap.
    $quota      = $settings->monthly_message_quota;
    $used       = (int) $settings->ai_messages_used_this_period;
    $quotaPct   = $settings->quotaPercentage();
    $quotaReset = $settings->quota_reset_at;

    $i18n = [
        'modeLabels' => [
            'off'        => __('ui.ai_settings_page.modes.off.label'),
            'suggestion' => __('ui.ai_settings_page.modes.suggestion.label'),
            'autonomous' => __('ui.ai_settings_page.modes.autonomous.label'),
            'hybrid'     => __('ui.ai_settings_page.modes.hybrid.label'),
        ],
        'langLabels' => collect($languages)->map(fn ($l) => $l['code'])->all(),
        'chNone'     => __('ui.ai_settings_page.channels_none'),
        'chBoth'     => __('ui.ai_settings_page.channels_both'),
        'chWhatsapp' => __('ui.ai_settings_page.channels_whatsapp'),
        'chWebchat'  => __('ui.ai_settings_page.channels_webchat'),
    ];
@endphp

{{-- The console fills the viewport and manages its own scroll regions, so the
     shared .page-content padding/height is neutralised for this route. --}}
<script>document.body.classList.add('wc-host');</script>

<div class="wv-console" x-data="aiSettingsConsole()" x-cloak>
<form method="POST" action="{{ route($panelPrefix . '.ai-settings.update') }}" class="wc-shell" @submit="onSubmit()">
    @csrf
    @method('PUT')

    {{-- ══ PAGE HEAD ═══════════════════════════════════════════════ --}}
    <div class="wc-phead">
        <div class="m">
            <h1>
                {{ __('ui.ai_settings_page.page_title') }}
                <span class="wc-pill" :class="form.mode === 'off' ? 'off' : 'on'">
                    <i></i><span x-text="i18n.modeLabels[form.mode]"></span>
                </span>
            </h1>
            <p>{{ __('ui.ai_settings_page.subtitle') }}</p>
        </div>
        <div class="acts">
            <span class="wc-dirty" :class="dirty ? 'on' : ''">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 8v4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
                {{ __('ui.ai_settings_page.unsaved') }}
            </span>
            <button type="button" class="wc-btn g" @click="discard()" :disabled="!dirty">{{ __('ui.ai_settings_page.discard') }}</button>
            <button type="submit" class="wc-btn p" :disabled="saving">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                {{ __('ui.ai_settings_page.save_settings') }}
            </button>
        </div>
    </div>

    <div class="wc-body">

        {{-- ══ SECTION NAV ══════════════════════════════════════════ --}}
        <nav class="wc-snav">
            <div class="lbl">{{ __('ui.ai_settings_page.nav_settings') }}</div>

            <button type="button" class="wc-sn" :class="section === 'mode' ? 'on' : ''" @click="go('mode')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                <span>{{ __('ui.ai_settings_page.nav_mode') }}</span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'channels' ? 'on' : ''" @click="go('channels')" x-show="form.mode !== 'off'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                <span>{{ __('ui.ai_settings_page.nav_channels') }}</span>
                <svg class="warn" width="14" height="14" viewBox="0 0 24 24" fill="none" x-show="!form.whatsapp_enabled && !form.webchat_enabled"><path d="M12 3l9.5 17H2.5L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
            </button>

            <button type="button" class="wc-sn" :class="section === 'replies' ? 'on' : ''" @click="go('replies')" x-show="form.mode !== 'off'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c2.5 2.4 2.5 15.6 0 18M12 3c-2.5 2.4-2.5 15.6 0 18" stroke="currentColor" stroke-width="2"/></svg>
                <span>{{ __('ui.ai_settings_page.nav_replies') }}</span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'prompt' ? 'on' : ''" @click="go('prompt')" x-show="form.mode !== 'off'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor" stroke-width="2"/><path d="M7 8h10M7 12h10M7 16h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span>{{ __('ui.ai_settings_page.nav_prompt') }}</span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'escalation' ? 'on' : ''" @click="go('escalation')" x-show="form.mode !== 'off'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 3l9.5 17H2.5L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
                <span>{{ __('ui.ai_settings_page.nav_escalation') }}</span>
                <span class="n" x-text="form.escalation_keywords.length"></span>
            </button>

            <button type="button" class="wc-sn" :class="section === 'usage' ? 'on' : ''" @click="go('usage')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span>{{ __('ui.ai_settings_page.nav_usage') }}</span>
            </button>
        </nav>

        {{-- ══ FORM ═════════════════════════════════════════════════ --}}
        <div class="wc-form" x-ref="formScroll"><div class="wc-fwrap">

            {{-- ── MODE ────────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'mode'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.ai_settings_page.ai_mode') }}</h2>
                    <p>{{ __('ui.ai_settings_page.ai_mode_hint') }}</p>
                </div>

                <div class="wc-fld">
                    <div class="wc-modes">
                        @foreach ($modes as $key => $mode)
                            <button type="button" class="wc-mode" style="--acc:{{ $mode['color'] }}"
                                    :class="form.mode === '{{ $key }}' ? 'on' : ''"
                                    @click="form.mode = '{{ $key }}'">
                                <span class="top">
                                    <span class="ico"><i class="{{ $mode['icon'] }}"></i></span>
                                    <span class="lbl">{{ __('ui.ai_settings_page.modes.' . $key . '.label') }}</span>
                                    <span class="tick"><i class="ri-check-line" x-show="form.mode === '{{ $key }}'"></i></span>
                                </span>
                                <span class="desc">{{ __('ui.ai_settings_page.modes.' . $key . '.desc') }}</span>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="mode" :value="form.mode">
                    @error('mode') <div class="wc-err">{{ $message }}</div> @enderror
                </div>

                <div class="wc-fld" x-show="form.mode === 'autonomous'">
                    <div class="wc-note" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" style="color:#10b981"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                        <div>{{ __('ui.ai_settings_page.autonomous_info') }}</div>
                    </div>
                </div>

                <div class="wc-fld" x-show="form.mode === 'hybrid'">
                    <div class="wc-note" style="background:var(--wc-teal-50);border-color:var(--wc-teal-100);color:var(--wc-teal-d)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" style="color:var(--wc-teal-l)"><path d="M6 3v12a3 3 0 003 3h9M18 3v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="6" cy="3" r="1.6" fill="currentColor"/><circle cx="18" cy="3" r="1.6" fill="currentColor"/></svg>
                        <div>{{ __('ui.ai_settings_page.hybrid_info') }}</div>
                    </div>
                </div>

                <div class="wc-fld" x-show="form.mode === 'off'">
                    <div class="wc-blank">
                        <div class="ico"><i class="ri-robot-2-line" style="font-size:26px"></i></div>
                        <div class="t">{{ __('ui.ai_settings_page.ai_disabled') }}</div>
                        <div class="s">{{ __('ui.ai_settings_page.ai_disabled_desc') }}</div>
                    </div>
                </div>
            </section>

            {{-- ── CHANNELS ────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'channels'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.ai_settings_page.channels') }}</h2>
                    <p>{{ __('ui.ai_settings_page.channels_hint') }}</p>
                </div>

                <div class="wc-fld">
                    <div class="wc-trow" :class="form.whatsapp_enabled ? 'hi' : ''">
                        <span class="ico" style="background:#25a35a"><i class="ri-whatsapp-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.ai_settings_page.channel_whatsapp') }}</div>
                            <div class="s">{{ __('ui.ai_settings_page.channel_whatsapp_hint') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.whatsapp_enabled ? 'on' : ''"
                                role="switch" :aria-checked="form.whatsapp_enabled ? 'true' : 'false'"
                                @click="form.whatsapp_enabled = !form.whatsapp_enabled"></button>
                        <input type="hidden" name="whatsapp_enabled" :value="form.whatsapp_enabled ? 1 : 0">
                    </div>
                </div>

                <div class="wc-fld">
                    <div class="wc-trow" :class="form.webchat_enabled ? 'hi' : ''">
                        <span class="ico" style="background:#4f6bed"><i class="ri-chat-smile-2-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.ai_settings_page.channel_live_chat') }}</div>
                            <div class="s">{{ __('ui.ai_settings_page.channel_live_chat_hint') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.webchat_enabled ? 'on' : ''"
                                role="switch" :aria-checked="form.webchat_enabled ? 'true' : 'false'"
                                @click="form.webchat_enabled = !form.webchat_enabled"></button>
                        <input type="hidden" name="webchat_enabled" :value="form.webchat_enabled ? 1 : 0">
                    </div>
                </div>

                <div class="wc-fld" x-show="!form.whatsapp_enabled && !form.webchat_enabled">
                    <div class="wc-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 3l9.5 17H2.5L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
                        <div>{{ __('ui.ai_settings_page.no_channel_warning') }}</div>
                    </div>
                </div>

                <div class="wc-fld">
                    <div class="wc-trow">
                        <div class="m">
                            <div class="n">{{ __('ui.ai_settings_page.reply_when_claimed') }}</div>
                            <div class="s">{{ __('ui.ai_settings_page.reply_when_claimed_hint') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.reply_when_claimed ? 'on' : ''"
                                role="switch" :aria-checked="form.reply_when_claimed ? 'true' : 'false'"
                                @click="form.reply_when_claimed = !form.reply_when_claimed"></button>
                        <input type="hidden" name="reply_when_claimed" :value="form.reply_when_claimed ? 1 : 0">
                    </div>
                </div>
            </section>

            {{-- ── REPLIES ─────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'replies'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.ai_settings_page.reply_settings') }}</h2>
                    <p>{{ __('ui.ai_settings_page.reply_settings_hint') }}</p>
                </div>

                <div class="wc-fld">
                    <span class="wc-flabel">{{ __('ui.ai_settings_page.reply_language') }}</span>
                    <div class="wc-langs four">
                        @foreach ($languages as $code => $lang)
                            <button type="button" class="wc-lgc" :class="form.reply_language === '{{ $code }}' ? 'on' : ''"
                                    @click="form.reply_language = '{{ $code }}'">
                                <svg class="tick" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <div class="flag">{{ $lang['flag'] }}</div>
                                <div class="c" style="font-size:12px">{{ $lang['code'] }}</div>
                                <div class="n">{{ $lang['label'] }}</div>
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="reply_language" :value="form.reply_language">
                    @error('reply_language') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.ai_settings_page.reply_language_hint') }}</div>
                </div>

                <div class="wc-fld" x-show="form.mode === 'suggestion' || form.mode === 'hybrid'">
                    <span class="wc-flabel">{{ __('ui.ai_settings_page.suggestion_count') }}</span>
                    <div class="wc-nums">
                        @foreach ([1, 2, 3, 4, 5] as $n)
                            <button type="button" class="wc-numbtn" :class="form.suggestion_count === {{ $n }} ? 'on' : ''"
                                    @click="form.suggestion_count = {{ $n }}">{{ $n }}</button>
                        @endforeach
                        <span class="suffix">{{ __('ui.ai_settings_page.suggestions_per_message') }}</span>
                    </div>
                    <input type="hidden" name="suggestion_count" :value="form.suggestion_count">
                    @error('suggestion_count') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.ai_settings_page.suggestion_count_hint') }}</div>
                </div>
            </section>

            {{-- ── PROMPT ──────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'prompt'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.ai_settings_page.system_prompt') }}</h2>
                    <p>{{ __('ui.ai_settings_page.sec_prompt_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <label for="aiPrompt">{{ __('ui.ai_settings_page.system_prompt_hint') }}</label>
                    <textarea id="aiPrompt" name="system_prompt" maxlength="4000" x-model="form.system_prompt"
                              class="wc-inp @error('system_prompt') err @enderror" style="min-height:220px"
                              placeholder="{{ __('ui.ai_settings_page.system_prompt_placeholder', ['tenant' => auth()->user()->tenant->name ?? '']) }}"></textarea>
                    <div class="wc-cnt"><span x-text="form.system_prompt.length"></span>/4000</div>
                    @error('system_prompt') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.ai_settings_page.system_prompt_footer') }}</div>
                </div>
            </section>

            {{-- ── ESCALATION ──────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'escalation'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.ai_settings_page.escalation_keywords') }}</h2>
                    <p>{{ __('ui.ai_settings_page.escalation_keywords_hint') }}</p>
                </div>

                <div class="wc-fld">
                    <div class="wc-tagbox">
                        <template x-if="form.escalation_keywords.length === 0">
                            <span class="placeholder">{{ __('ui.ai_settings_page.no_keywords') }}</span>
                        </template>
                        <template x-for="(kw, i) in form.escalation_keywords" :key="'kw' + i">
                            <span class="wc-tag">
                                <span x-text="kw"></span>
                                <button type="button" @click="form.escalation_keywords.splice(i, 1)" :aria-label="kw">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                </button>
                            </span>
                        </template>
                    </div>

                    <div class="wc-addrow">
                        <input class="wc-inp" type="text" maxlength="40" x-model="newKeyword"
                               @keydown.enter.prevent="addKeyword()" @keydown.comma.prevent="addKeyword()"
                               placeholder="{{ __('ui.ai_settings_page.keyword_placeholder') }}">
                        <button type="button" class="wc-btn g" @click="addKeyword()" :disabled="!newKeyword.trim()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                            {{ __('ui.ai_settings_page.add') }}
                        </button>
                    </div>

                    {{-- The controller json_decodes this field, so it stays a single JSON string. --}}
                    <input type="hidden" name="escalation_keywords" :value="JSON.stringify(form.escalation_keywords)">
                    @error('escalation_keywords') <div class="wc-err">{{ $message }}</div> @enderror
                    <div class="wc-hint">{{ __('ui.ai_settings_page.keyword_hint') }}</div>
                </div>
            </section>

            {{-- ── USAGE (read-only) ───────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'usage'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.ai_settings_page.usage_limits') }}</h2>
                    <p>{{ __('ui.ai_settings_page.sec_usage_desc') }}</p>
                </div>

                <div class="wc-fld">
                    <div class="wc-meter">
                        <div class="hd">
                            <span class="ico" style="background:#eff6ff;color:#2563eb"><i class="ri-coins-line"></i></span>
                            <div class="m">
                                <div class="n">{{ __('ui.ai_settings_page.usage_card_title') }}</div>
                                <div class="s">{{ __('ui.ai_settings_page.usage_card_hint') }}</div>
                            </div>
                            <div class="val">
                                @if (is_null($quota))
                                    {{ __('ui.ai_settings_page.usage_unlimited') }}
                                @elseif ($quota === 0)
                                    <span style="color:var(--wc-red)">{{ __('ui.ai_settings_page.usage_ai_off') }}</span>
                                @else
                                    {{ number_format($used) }} / {{ number_format($quota) }}
                                @endif
                            </div>
                        </div>

                        @if (!is_null($quota) && $quota > 0)
                            @php
                                $barColor = $quotaPct >= 90 ? '#dc2626' : ($quotaPct >= 70 ? '#f59e0b' : '#2563eb');
                            @endphp
                            <div class="bar"><i style="width:{{ min(100, max(0, $quotaPct)) }}%;background:{{ $barColor }}"></i></div>
                        @endif

                        @if ($quotaReset && !is_null($quota) && $quota !== 0)
                            <div class="foot">{{ __('ui.ai_settings_page.usage_resets_on', ['date' => $quotaReset->format('Y-m-d')]) }}</div>
                        @endif
                    </div>
                </div>

                <div class="wc-fld">
                    <div class="wc-linkcard">
                        <span class="ico"><i class="ri-book-2-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.ai_settings_page.knowledge_base') }}</div>
                            <div class="s">{{ $knowledgeCount }} {{ __('ui.ai_settings_page.entries') }} · {{ __('ui.ai_settings_page.knowledge_base_hint') }}</div>
                        </div>
                        <a href="{{ route($panelPrefix . '.knowledge.index') }}" class="wc-btn g sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('ui.manage') }}
                        </a>
                    </div>
                </div>
            </section>

        </div></div>

        {{-- ══ PREVIEW ══════════════════════════════════════════════ --}}
        <aside class="wc-prev" :class="previewOpen ? 'open' : ''">
            <div class="wc-pvh">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="eye"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                <span class="t">{{ __('ui.ai_settings_page.preview') }}</span>
                <span class="cl" x-text="form.reply_language.toUpperCase()"></span>
            </div>

            <div class="wc-pvbody">
                <div class="wc-blank" style="width:288px" x-show="form.mode === 'off'">
                    <div class="ico"><i class="ri-robot-2-line" style="font-size:26px"></i></div>
                    <div class="t">{{ __('ui.ai_settings_page.pv_off_title') }}</div>
                    <div class="s">{{ __('ui.ai_settings_page.pv_off_hint') }}</div>
                </div>

                <div class="wc-convo" x-show="form.mode !== 'off'">
                    <div class="ch">
                        <span class="av" style="background:var(--wc-teal)">C</span>
                        <div class="m">
                            <div class="n">{{ __('ui.ai_settings_page.pv_customer') }}</div>
                            <div class="s" x-text="channelSummary()"></div>
                        </div>
                    </div>

                    <div class="cb">
                        <div class="wc-bub in">{{ __('ui.ai_settings_page.pv_sample_msg') }}</div>

                        {{-- Autonomous + hybrid: the AI answers on its own. --}}
                        <template x-if="form.mode === 'autonomous' || form.mode === 'hybrid'">
                            <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
                                <span class="wc-aitag"><i class="ri-flashlight-line"></i> AI</span>
                                <div class="wc-bub out">{{ __('ui.ai_settings_page.pv_ai_reply') }}</div>
                            </div>
                        </template>

                        {{-- Suggestion + hybrid: drafts the agent can send. --}}
                        <template x-if="form.mode === 'suggestion' || form.mode === 'hybrid'">
                            <div class="wc-suggwrap">
                                <div class="cap">{{ __('ui.ai_settings_page.pv_drafts') }}</div>
                                <div class="wc-sugg">
                                    <template x-for="(draft, i) in visibleDrafts()" :key="'d' + i">
                                        <span x-text="draft"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Hybrid stops at an escalation keyword and hands over. --}}
                        <template x-if="form.mode === 'hybrid' && form.escalation_keywords.length > 0">
                            <span class="wc-aitag left" style="background:var(--wc-amber-50);color:var(--wc-amber)">
                                <i class="ri-user-shared-line"></i> {{ __('ui.ai_settings_page.pv_escalated') }}
                            </span>
                        </template>
                    </div>

                    <div class="cf">
                        <span class="fi">{{ __('ui.ai_settings_page.pv_composer') }}</span>
                        <span class="sb"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M3 12L21 4l-8 17-2-7-8-2z" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/></svg></span>
                    </div>
                </div>

                <div class="wc-pvmeta">
                    <div class="kv"><span class="k">{{ __('ui.ai_settings_page.meta_mode') }}</span><span class="v" x-text="i18n.modeLabels[form.mode]"></span></div>
                    <div class="kv"><span class="k">{{ __('ui.ai_settings_page.meta_channels') }}</span><span class="v" x-text="channelSummary()"></span></div>
                    <div class="kv"><span class="k">{{ __('ui.ai_settings_page.meta_language') }}</span><span class="v" x-text="i18n.langLabels[form.reply_language] || form.reply_language.toUpperCase()"></span></div>
                    <div class="kv" x-show="form.mode === 'suggestion' || form.mode === 'hybrid'"><span class="k">{{ __('ui.ai_settings_page.meta_drafts') }}</span><span class="v" x-text="form.suggestion_count"></span></div>
                    <div class="kv"><span class="k">{{ __('ui.ai_settings_page.meta_keywords') }}</span><span class="v" x-text="form.escalation_keywords.length"></span></div>
                </div>
            </div>
        </aside>

    </div>
</form>

    <button type="button" class="wc-pvfab" @click="previewOpen = true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
        {{ __('ui.ai_settings_page.preview') }}
    </button>
    <div class="wc-scrim" :class="previewOpen ? 'on' : ''" @click="previewOpen = false"></div>
</div>

<script>
function aiSettingsConsole() {
    return {
        form:        @json($initial),
        i18n:        @json($i18n),
        drafts:      @js([__('ui.ai_settings_page.pv_draft_1'), __('ui.ai_settings_page.pv_draft_2'), __('ui.ai_settings_page.pv_draft_3')]),
        baseline:    '',
        section:     @js($openSection),
        previewOpen: false,
        newKeyword:  '',
        saving:      false,
        dirty:       false,

        init() {
            this.baseline = JSON.stringify(this.form);
            this.$watch('form', () => { this.dirty = JSON.stringify(this.form) !== this.baseline; }, { deep: true });
            // Sections after Mode only exist while the AI is on; bounce back to
            // Mode if the tenant switches it off while standing in one of them.
            this.$watch('form.mode', v => { if (v === 'off' && this.section !== 'usage') this.go('mode'); });
        },

        go(s) {
            this.section = s;
            if (this.$refs.formScroll) this.$refs.formScroll.scrollTop = 0;
        },

        addKeyword() {
            const k = this.newKeyword.trim().toLowerCase().replace(/,+$/, '');
            if (k && !this.form.escalation_keywords.includes(k)) this.form.escalation_keywords.push(k);
            this.newKeyword = '';
        },

        channelSummary() {
            const w = this.form.whatsapp_enabled, c = this.form.webchat_enabled;
            if (w && c) return this.i18n.chBoth;
            if (w) return this.i18n.chWhatsapp;
            if (c) return this.i18n.chWebchat;
            return this.i18n.chNone;
        },

        // The preview shows as many drafts as the tenant asked for, cycling the
        // three samples so a count of 5 still renders five distinct rows.
        visibleDrafts() {
            const n = Math.max(1, Math.min(5, this.form.suggestion_count || 1));
            return Array.from({ length: n }, (_, i) => this.drafts[i % this.drafts.length]);
        },

        discard() {
            this.form = JSON.parse(this.baseline);
            this.dirty = false;
        },

        onSubmit() { this.saving = true; },
    };
}
</script>

@push('styles')
    {{-- Shared Wavadesk console shell — see /webchat/settings and /saved-replies. --}}
    <link rel="stylesheet" href="{{ asset('css/wavadesk-console.css') }}?v={{ filemtime(public_path('css/wavadesk-console.css')) }}">
@endpush
@endsection
