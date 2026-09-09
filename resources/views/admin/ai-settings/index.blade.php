@extends('layouts.admin')

@section('title', __('ui.ai_settings_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.ai_settings_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
$panelPrefix = auth()->user()->routeNamePrefix();
$languages = [
    'auto' => ['label' => __('ui.ai_settings_page.lang_auto'),  'flag' => '🌐', 'code' => 'Auto'],
    'fr'   => ['label' => __('ui.ai_settings_page.lang_fr'),    'flag' => '🇫🇷', 'code' => 'FR'],
    'en'   => ['label' => __('ui.ai_settings_page.lang_en'),    'flag' => '🇬🇧', 'code' => 'EN'],
    'ar'   => ['label' => __('ui.ai_settings_page.lang_ar'),    'flag' => '🇸🇦', 'code' => 'AR'],
    'es'   => ['label' => __('ui.ai_settings_page.lang_es'),    'flag' => '🇪🇸', 'code' => 'ES'],
    'pt'   => ['label' => __('ui.ai_settings_page.lang_pt'),    'flag' => '🇵🇹', 'code' => 'PT'],
    'de'   => ['label' => __('ui.ai_settings_page.lang_de'),    'flag' => '🇩🇪', 'code' => 'DE'],
    'it'   => ['label' => __('ui.ai_settings_page.lang_it'),    'flag' => '🇮🇹', 'code' => 'IT'],
];
$modes = [
    'off' => [
        'label' => __('ui.ai_settings_page.modes.off.label'),
        'desc'  => __('ui.ai_settings_page.modes.off.desc'),
        'color' => '#6b7280',
        'shadow'=> 'rgba(107,114,128,.18)',
        'bg'    => 'rgba(107,114,128,.07)',
        'icon'  => 'ri-close-circle-line',
    ],
    'suggestion' => [
        'label' => __('ui.ai_settings_page.modes.suggestion.label'),
        'desc'  => __('ui.ai_settings_page.modes.suggestion.desc'),
        'color' => '#f59e0b',
        'shadow'=> 'rgba(245,158,11,.22)',
        'bg'    => 'rgba(245,158,11,.07)',
        'icon'  => 'ri-lightbulb-flash-line',
    ],
    'autonomous' => [
        'label' => __('ui.ai_settings_page.modes.autonomous.label'),
        'desc'  => __('ui.ai_settings_page.modes.autonomous.desc'),
        'color' => '#10b981',
        'shadow'=> 'rgba(16,185,129,.22)',
        'bg'    => 'rgba(16,185,129,.07)',
        'icon'  => 'ri-flashlight-line',
    ],
    'hybrid' => [
        'label' => __('ui.ai_settings_page.modes.hybrid.label'),
        'desc'  => __('ui.ai_settings_page.modes.hybrid.desc'),
        'color' => '#15b6a8',
        'shadow'=> 'rgba(21,182,168,.22)',
        'bg'    => 'rgba(21,182,168,.07)',
        'icon'  => 'ri-git-branch-line',
    ],
];
@endphp

<style>
.ai-mode-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .75rem;
}
@media (max-width: 1100px) { .ai-mode-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px)  { .ai-mode-grid { grid-template-columns: 1fr; } }

.ai-mode-card {
    border: 1.5px solid var(--card-border);
    border-radius: .875rem;
    padding: 1rem;
    text-align: start;
    cursor: pointer;
    transition: border-color .16s, box-shadow .16s, background .16s;
    width: 100%;
    background: var(--card-bg);
    position: relative;
    display: flex;
    flex-direction: column;
    gap: .5rem;
    min-height: 100%;
}
.ai-mode-card:hover { border-color: var(--border-2, #cbd5e1); }
.ai-mode-card .mode-top { display: flex; align-items: center; gap: .625rem; }
.ai-mode-card .mode-icon {
    width: 2rem; height: 2rem; border-radius: .5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
    transition: background .16s, color .16s;
    background: var(--page-bg); color: var(--text-muted);
}
.ai-mode-card .mode-label {
    font-weight: 700; font-size: .875rem; letter-spacing: -.01em;
    color: var(--text-primary); transition: color .16s;
}
.ai-mode-card .mode-check {
    margin-inline-start: auto; width: 1.05rem; height: 1.05rem; border-radius: 50%;
    border: 1.5px solid var(--card-border); display: grid; place-items: center;
    color: #fff; font-size: .6rem; flex-shrink: 0; transition: .16s;
}
.ai-mode-card .mode-desc {
    font-size: .75rem; color: var(--text-muted); line-height: 1.45;
}

/* channel switches */
.ai-channel {
    display: flex; align-items: flex-start; gap: .875rem;
    padding: 1rem; border: 1.5px solid var(--card-border);
    border-radius: .875rem; transition: border-color .16s, background .16s;
}
.ai-channel.on { border-color: var(--brand); background: var(--brand-xlight); }
.ai-channel .ch-icon {
    width: 2.25rem; height: 2.25rem; border-radius: .625rem; flex-shrink: 0;
    display: grid; place-items: center; font-size: 1.05rem; color: #fff;
}
.ai-channel .ch-m { flex: 1; min-width: 0; }
.ai-channel .ch-name { font-size: .875rem; font-weight: 600; color: var(--text-primary); }
.ai-channel .ch-desc { font-size: .75rem; color: var(--text-muted); margin-top: .15rem; line-height: 1.45; }
.ai-switch {
    width: 2.5rem; height: 1.4rem; border-radius: 999px; background: var(--card-border);
    position: relative; flex-shrink: 0; transition: background .16s; border: none; cursor: pointer;
    margin-top: .15rem;
}
.ai-switch::after {
    content: ''; position: absolute; top: .175rem; inset-inline-start: .175rem;
    width: 1.05rem; height: 1.05rem; border-radius: 50%; background: #fff;
    transition: inset-inline-start .16s; box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.ai-switch.on { background: var(--brand); }
.ai-switch.on::after { inset-inline-start: 1.275rem; }
.ai-switch:disabled { opacity: .45; cursor: not-allowed; }

.lang-btn {
    border: 1.5px solid var(--card-border); border-radius: .625rem;
    padding: .5rem .375rem; cursor: pointer; font-size: .8rem;
    text-align: center; transition: all .15s; background: transparent;
    color: var(--text-secondary);
    display: flex; flex-direction: column; align-items: center; gap: .2rem;
}
.lang-btn:hover { border-color: var(--brand); color: var(--brand); }
.lang-btn .lang-flag { font-size: 1.125rem; line-height: 1; }
.lang-btn .lang-code { font-size: .7rem; font-weight: 700; letter-spacing: .04em; }
.count-btn {
    width: 2.5rem; height: 2.5rem; border: 1.5px solid var(--card-border); border-radius: .625rem;
    cursor: pointer; font-size: .875rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s; flex-shrink: 0;
    background: transparent; color: var(--text-secondary);
}
.count-btn:hover { border-color: var(--brand); color: var(--brand); }
.info-badge {
    padding: .75rem 1rem; border-radius: .625rem;
    display: flex; gap: .625rem; align-items: flex-start;
    font-size: .8125rem; color: var(--text-secondary);
}
</style>

<div x-data="aiSettingsPage()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.ai_settings_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.ai_settings_page.subtitle') }}</div>
        </div>
    </div>

    <form action="{{ route($panelPrefix . '.ai-settings.update') }}" method="POST" data-loading>
        @csrf @method('PUT')
        <input type="hidden" name="mode" :value="selectedMode">

        {{-- ── Mode selector ──────────────────────────────────────────────── --}}
        <div class="card" style="margin-bottom:1.25rem">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.ai_settings_page.ai_mode') }}</div>
                    <div class="card-subtitle">{{ __('ui.ai_settings_page.ai_mode_hint') }}</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem">
                <div class="ai-mode-grid">
                    @foreach($modes as $value => $mode)
                    <button type="button"
                            class="ai-mode-card"
                            @click="selectedMode = '{{ $value }}'"
                            :style="selectedMode === '{{ $value }}'
                                ? 'border-color:{{ $mode['color'] }};background:{{ $mode['bg'] }};box-shadow:0 3px 14px {{ $mode['shadow'] }};'
                                : ''">
                        <span class="mode-top">
                            <span class="mode-icon"
                                  :style="selectedMode === '{{ $value }}' ? 'background:{{ $mode['color'] }};color:#fff;' : ''">
                                <i class="{{ $mode['icon'] }}"></i>
                            </span>
                            <span class="mode-label"
                                  :style="selectedMode === '{{ $value }}' ? 'color:{{ $mode['color'] }}' : ''">
                                {{ $mode['label'] }}
                            </span>
                            <span class="mode-check"
                                  :style="selectedMode === '{{ $value }}'
                                      ? 'background:{{ $mode['color'] }};border-color:{{ $mode['color'] }}'
                                      : ''">
                                <i class="ri-check-line" x-show="selectedMode === '{{ $value }}'"></i>
                            </span>
                        </span>
                        <span class="mode-desc">{{ $mode['desc'] }}</span>
                    </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Channels: where the AI is allowed to answer ─────────────────── --}}
        <div class="card" style="margin-bottom:1.25rem" x-show="selectedMode !== 'off'">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.ai_settings_page.channels') }}</div>
                    <div class="card-subtitle">{{ __('ui.ai_settings_page.channels_hint') }}</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem" class="ai-channels">
                <div class="ai-channel" :class="{ 'on': whatsappEnabled }">
                    <div class="ch-icon" style="background:#25a35a">
                        <i class="ri-whatsapp-line"></i>
                    </div>
                    <div class="ch-m">
                        <div class="ch-name">{{ __('ui.ai_settings_page.channel_whatsapp') }}</div>
                        <div class="ch-desc">{{ __('ui.ai_settings_page.channel_whatsapp_hint') }}</div>
                    </div>
                    <button type="button" class="ai-switch" :class="{ 'on': whatsappEnabled }"
                            @click="whatsappEnabled = !whatsappEnabled"
                            :aria-pressed="whatsappEnabled ? 'true' : 'false'"
                            aria-label="{{ __('ui.ai_settings_page.channel_whatsapp') }}"></button>
                    <input type="hidden" name="whatsapp_enabled" :value="whatsappEnabled ? 1 : 0">
                </div>

                <div class="ai-channel" :class="{ 'on': webchatEnabled }">
                    <div class="ch-icon" style="background:#4f6bed">
                        <i class="ri-chat-smile-2-line"></i>
                    </div>
                    <div class="ch-m">
                        <div class="ch-name">{{ __('ui.ai_settings_page.channel_live_chat') }}</div>
                        <div class="ch-desc">{{ __('ui.ai_settings_page.channel_live_chat_hint') }}</div>
                    </div>
                    <button type="button" class="ai-switch" :class="{ 'on': webchatEnabled }"
                            @click="webchatEnabled = !webchatEnabled"
                            :aria-pressed="webchatEnabled ? 'true' : 'false'"
                            aria-label="{{ __('ui.ai_settings_page.channel_live_chat') }}"></button>
                    <input type="hidden" name="webchat_enabled" :value="webchatEnabled ? 1 : 0">
                </div>
            </div>

            <div style="padding:0 1.5rem 1.5rem">
                <div class="ai-channel" :class="{ 'on': replyWhenClaimed }" style="padding:.875rem 1rem">
                    <div class="ch-m">
                        <div class="ch-name">{{ __('ui.ai_settings_page.reply_when_claimed') }}</div>
                        <div class="ch-desc">{{ __('ui.ai_settings_page.reply_when_claimed_hint') }}</div>
                    </div>
                    <button type="button" class="ai-switch" :class="{ 'on': replyWhenClaimed }"
                            @click="replyWhenClaimed = !replyWhenClaimed"
                            :aria-pressed="replyWhenClaimed ? 'true' : 'false'"
                            aria-label="{{ __('ui.ai_settings_page.reply_when_claimed') }}"></button>
                    <input type="hidden" name="reply_when_claimed" :value="replyWhenClaimed ? 1 : 0">
                </div>
            </div>

            <div style="padding:0 1.5rem 1.5rem" x-show="!whatsappEnabled && !webchatEnabled">
                <div class="info-badge" style="background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.25)">
                    <i class="ri-alert-line" style="color:#d97706;font-size:1rem;margin-top:.1rem;flex-shrink:0"></i>
                    <span>{{ __('ui.ai_settings_page.no_channel_warning') }}</span>
                </div>
            </div>
        </div>

        {{-- ── Two-column layout ───────────────────────────────────────────── --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">

            {{-- LEFT COLUMN --}}
            <div style="display:flex;flex-direction:column;gap:1.5rem;">

                {{-- System Prompt --}}
                <div class="card" x-show="selectedMode !== 'off'">
                    <div class="card-header">
                        <div>
                            <div class="card-title">{{ __('ui.ai_settings_page.system_prompt') }}</div>
                            <div class="card-subtitle">{{ __('ui.ai_settings_page.system_prompt_hint') }}</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem;">
                        <textarea name="system_prompt" rows="8"
                                  class="form-control @error('system_prompt') error @enderror"
                                  placeholder="{{ __('ui.ai_settings_page.system_prompt_placeholder', ['tenant' => auth()->user()->tenant->name]) }}">{{ old('system_prompt', $settings->system_prompt) }}</textarea>
                        @error('system_prompt') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">{{ __('ui.ai_settings_page.system_prompt_footer') }}</div>
                    </div>
                </div>

                {{-- Off state placeholder --}}
                <div class="card" x-show="selectedMode === 'off'" style="padding:2.5rem;text-align:center;">
                    <div style="width:3.5rem;height:3.5rem;border-radius:50%;background:rgba(107,114,128,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.5rem;color:#6b7280;">
                        <i class="ri-robot-off-line"></i>
                    </div>
                    <div style="font-weight:700;font-size:.9375rem;color:var(--text-primary);margin-bottom:.375rem;">
                        {{ __('ui.ai_settings_page.ai_disabled') }}
                    </div>
                    <div style="font-size:.8125rem;color:var(--text-muted);max-width:22rem;margin:0 auto;">
                        {{ __('ui.ai_settings_page.ai_disabled_desc') }}
                    </div>
                </div>

                {{-- Escalation Keywords --}}
                <div class="card" x-show="selectedMode !== 'off'"
                     x-data="keywordManager(@json($settings->escalation_keywords ?? []))">
                    <div class="card-header">
                        <div>
                            <div class="card-title">{{ __('ui.ai_settings_page.escalation_keywords') }}</div>
                            <div class="card-subtitle">{{ __('ui.ai_settings_page.escalation_keywords_hint') }}</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem;">
                        <div style="display:flex;flex-wrap:wrap;gap:.375rem;margin-bottom:.75rem;min-height:2.5rem;padding:.625rem .75rem;background:var(--page-bg);border-radius:.625rem;border:1px solid var(--card-border);">
                            <template x-if="keywords.length === 0">
                                <span style="font-size:.75rem;color:var(--text-muted);align-self:center;">
                                    {{ __('ui.ai_settings_page.no_keywords') }}
                                </span>
                            </template>
                            <template x-for="(kw, i) in keywords" :key="i">
                                <span style="display:inline-flex;align-items:center;gap:.375rem;padding:.25rem .625rem;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:999px;font-size:.8125rem;color:#ef4444;">
                                    <span x-text="kw"></span>
                                    <button type="button" @click="remove(i)"
                                            style="background:none;border:none;cursor:pointer;color:#ef4444;padding:0;line-height:1;font-size:.875rem;display:flex;align-items:center;opacity:.7;transition:opacity .1s;"
                                            onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.7">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </span>
                            </template>
                        </div>
                        <div style="display:flex;gap:.5rem;">
                            <input type="text" x-model="newKw"
                                   @keydown.enter.prevent="add()"
                                   @keydown.comma.prevent="add()"
                                   placeholder="{{ __('ui.ai_settings_page.keyword_placeholder') }}"
                                   class="form-control" style="flex:1;">
                            <button type="button" @click="add()" class="btn btn-outline btn-sm">
                                <i class="ri-add-line"></i> {{ __('ui.add') }}
                            </button>
                        </div>
                        <div class="form-hint" style="margin-top:.5rem;">{{ __('ui.ai_settings_page.keyword_hint') }}</div>
                        <input type="hidden" name="escalation_keywords" :value="JSON.stringify(keywords)">
                    </div>
                </div>

            </div>

            {{-- RIGHT COLUMN --}}
            <div style="display:flex;flex-direction:column;gap:1.5rem;">

                {{-- Language + Suggestion Settings --}}
                <div class="card" x-show="selectedMode !== 'off'">
                    <div class="card-header">
                        <div>
                            <div class="card-title">{{ __('ui.ai_settings_page.reply_settings') }}</div>
                            <div class="card-subtitle">{{ __('ui.ai_settings_page.reply_settings_hint') }}</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem;">

                        {{-- Reply Language --}}
                        <div class="form-group"
                             x-data="{ lang: '{{ old('reply_language', $settings->reply_language ?? 'auto') }}' }">
                            <label class="form-label">{{ __('ui.ai_settings_page.reply_language') }}</label>
                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin-top:.375rem;">
                                @foreach($languages as $code => $lang)
                                <button type="button"
                                        class="lang-btn"
                                        @click="lang = '{{ $code }}'"
                                        :style="lang === '{{ $code }}'
                                            ? 'border-color:var(--brand);background:rgba(16,185,129,.08);color:var(--brand);font-weight:600;'
                                            : ''">
                                    <span class="lang-flag">{{ $lang['flag'] }}</span>
                                    <span class="lang-code">{{ $lang['code'] }}</span>
                                    <span style="font-size:.7rem;">{{ $lang['label'] }}</span>
                                </button>
                                @endforeach
                            </div>
                            <div class="form-hint" style="margin-top:.375rem;">{{ __('ui.ai_settings_page.reply_language_hint') }}</div>
                            <input type="hidden" name="reply_language" :value="lang">
                        </div>

                        {{-- Suggestion count --}}
                        <div x-show="selectedMode === 'suggestion' || selectedMode === 'hybrid'">
                            <label class="form-label">{{ __('ui.ai_settings_page.suggestion_count') }}</label>
                            <div style="display:flex;gap:.5rem;align-items:center;margin-top:.375rem;"
                                 x-data="{ count: {{ old('suggestion_count', $settings->suggestion_count ?? 3) }} }">
                                @foreach([1,2,3,4,5] as $n)
                                <button type="button"
                                        class="count-btn"
                                        @click="count = {{ $n }}"
                                        :style="count === {{ $n }}
                                            ? 'background:var(--brand);color:#fff;border-color:var(--brand);box-shadow:0 2px 8px rgba(16,185,129,.3);'
                                            : ''">
                                    {{ $n }}
                                </button>
                                @endforeach
                                <span style="font-size:.8125rem;color:var(--text-muted);margin-left:.25rem;">
                                    {{ __('ui.ai_settings_page.suggestions_per_message') }}
                                </span>
                                <input type="hidden" name="suggestion_count" :value="count">
                            </div>
                            <div class="form-hint" style="margin-top:.375rem;">
                                {{ __('ui.ai_settings_page.suggestion_count_hint') }}
                            </div>
                        </div>

                        {{-- Autonomous info --}}
                        <div x-show="selectedMode === 'autonomous'"
                             class="info-badge" style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);">
                            <i class="ri-flashlight-line" style="color:#10b981;font-size:1rem;margin-top:.1rem;flex-shrink:0;"></i>
                            <span>{{ __('ui.ai_settings_page.autonomous_info') }}</span>
                        </div>

                        {{-- Hybrid info --}}
                        <div x-show="selectedMode === 'hybrid'"
                             class="info-badge" style="background:rgba(21,182,168,.06);border:1px solid rgba(21,182,168,.2);">
                            <i class="ri-git-branch-line" style="color:#15b6a8;font-size:1rem;margin-top:.1rem;flex-shrink:0;"></i>
                            <span>{{ __('ui.ai_settings_page.hybrid_info') }}</span>
                        </div>

                    </div>
                </div>

                {{-- Token usage — plan-driven, read-only. Tenants see what
                     their current plan gives them and how much is left this
                     period. null = unlimited, 0 = AI off, positive = cap. --}}
                @php
                    $q     = $settings->monthly_token_quota;
                    $used  = (int) $settings->tokens_used_this_period;
                    $pct   = $settings->quotaPercentage();
                    $reset = $settings->quota_reset_at;
                @endphp
                <div style="padding:1rem 1.25rem;background:rgba(37,99,235,.04);border:1px solid rgba(37,99,235,.14);border-radius:.875rem;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.5rem;">
                        <div style="display:flex;gap:.75rem;align-items:center;">
                            <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(37,99,235,.14);display:flex;align-items:center;justify-content:center;color:#2563eb;flex-shrink:0;">
                                <i class="ri-coins-line"></i>
                            </div>
                            <div>
                                <div style="font-size:.875rem;font-weight:600;color:var(--text-primary);">{{ __('ui.ai_settings_page.usage_card_title') }}</div>
                                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem;">{{ __('ui.ai_settings_page.usage_card_hint') }}</div>
                            </div>
                        </div>
                        <div style="text-align:end;font-size:.875rem;font-weight:600;color:var(--text-primary);white-space:nowrap;">
                            @if(is_null($q))
                                {{ __('ui.ai_settings_page.usage_unlimited') }}
                            @elseif($q === 0)
                                <span style="color:#dc2626">{{ __('ui.ai_settings_page.usage_ai_off') }}</span>
                            @else
                                {{ number_format($used) }} / {{ number_format($q) }}
                            @endif
                        </div>
                    </div>
                    @if(!is_null($q) && $q > 0)
                        <div style="height:6px;border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;">
                            <div style="height:100%;background:@if($pct >= 90) #dc2626 @elseif($pct >= 70) #f59e0b @else #2563eb @endif;width:{{ min(100, max(0, $pct)) }}%;transition:width .3s"></div>
                        </div>
                    @endif
                    @if($reset && !is_null($q) && $q !== 0)
                        <div style="font-size:.7rem;color:var(--text-muted);margin-top:.5rem;text-align:end;">
                            {{ __('ui.ai_settings_page.usage_resets_on', ['date' => $reset->format('Y-m-d')]) }}
                        </div>
                    @endif
                </div>

                {{-- Knowledge Base link --}}
                <div style="padding:1rem 1.25rem;background:rgba(16,185,129,.05);border:1px solid rgba(16,185,129,.15);border-radius:.875rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                    <div style="display:flex;gap:.75rem;align-items:center;">
                        <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.15);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0;">
                            <i class="ri-book-2-line"></i>
                        </div>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:var(--text-primary);">
                                {{ __('ui.ai_settings_page.knowledge_base') }}
                            </div>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem;">
                                {{ $knowledgeCount }} {{ __('ui.ai_settings_page.entries') }} · {{ __('ui.ai_settings_page.knowledge_base_hint') }}
                            </div>
                        </div>
                    </div>
                    <a href="{{ route($panelPrefix . '.knowledge.index') }}" class="btn btn-outline btn-sm" style="flex-shrink:0;">
                        <i class="ri-external-link-line"></i> {{ __('ui.manage') }}
                    </a>
                </div>

            </div>
        </div>

        {{-- Save / Cancel --}}
        <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;gap:.5rem;padding-top:1rem;border-top:1px solid var(--card-border);">
            <a href="{{ route($panelPrefix . '.dashboard') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                <i class="ri-save-3-line"></i> {{ __('ui.ai_settings_page.save_settings') }}
            </button>
        </div>
    </form>

</div>

@push('scripts')
<script>
function aiSettingsPage() {
    return {
        selectedMode:     '{{ old('mode', $settings->mode) }}',
        whatsappEnabled:  {{ old('whatsapp_enabled', $settings->whatsapp_enabled ?? true) ? 'true' : 'false' }},
        webchatEnabled:   {{ old('webchat_enabled', $settings->webchat_enabled ?? true) ? 'true' : 'false' }},
        replyWhenClaimed: {{ old('reply_when_claimed', $settings->reply_when_claimed ?? false) ? 'true' : 'false' }},
    }
}

function keywordManager(initial) {
    return {
        keywords: initial || [],
        newKw: '',
        add() {
            const k = this.newKw.trim().toLowerCase();
            if (k && !this.keywords.includes(k)) this.keywords.push(k);
            this.newKw = '';
        },
        remove(i) { this.keywords.splice(i, 1); }
    }
}

</script>
@endpush
@endsection
