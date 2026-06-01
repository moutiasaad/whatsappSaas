@extends('layouts.admin')

@section('title', __('ui.ai_settings_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.ai_settings_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
$panelPrefix = auth()->user()->routeNamePrefix();
$languages = [
    'auto' => ['label' => __('ui.ai_settings_page.lang_auto'),       'flag' => '🌐'],
    'fr'   => ['label' => __('ui.ai_settings_page.lang_fr'),          'flag' => '🇫🇷'],
    'en'   => ['label' => __('ui.ai_settings_page.lang_en'),          'flag' => '🇬🇧'],
    'ar'   => ['label' => __('ui.ai_settings_page.lang_ar'),          'flag' => '🇸🇦'],
    'es'   => ['label' => __('ui.ai_settings_page.lang_es'),          'flag' => '🇪🇸'],
    'pt'   => ['label' => __('ui.ai_settings_page.lang_pt'),          'flag' => '🇵🇹'],
    'de'   => ['label' => __('ui.ai_settings_page.lang_de'),          'flag' => '🇩🇪'],
    'it'   => ['label' => __('ui.ai_settings_page.lang_it'),          'flag' => '🇮🇹'],
];
$modes = [
    'off' => [
        'label' => __('ui.ai_settings_page.modes.off.label'),
        'desc'  => __('ui.ai_settings_page.modes.off.desc'),
        'color' => '#6b7280',
        'bg'    => 'rgba(107,114,128,.08)',
        'icon'  => 'ri-close-circle-line',
    ],
    'suggestion' => [
        'label' => __('ui.ai_settings_page.modes.suggestion.label'),
        'desc'  => __('ui.ai_settings_page.modes.suggestion.desc'),
        'color' => '#f59e0b',
        'bg'    => 'rgba(245,158,11,.08)',
        'icon'  => 'ri-lightbulb-line',
    ],
    'autonomous' => [
        'label' => __('ui.ai_settings_page.modes.autonomous.label'),
        'desc'  => __('ui.ai_settings_page.modes.autonomous.desc'),
        'color' => '#10b981',
        'bg'    => 'rgba(16,185,129,.08)',
        'icon'  => 'ri-flashlight-line',
    ],
    'hybrid' => [
        'label' => __('ui.ai_settings_page.modes.hybrid.label'),
        'desc'  => __('ui.ai_settings_page.modes.hybrid.desc'),
        'color' => '#8b5cf6',
        'bg'    => 'rgba(139,92,246,.08)',
        'icon'  => 'ri-git-branch-line',
    ],
];
@endphp

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
        <div class="card" style="margin-bottom:1.5rem;padding:1.5rem;">
            <div style="font-weight:700;font-size:.9375rem;color:var(--text-primary);margin-bottom:.25rem;">
                {{ __('ui.ai_settings_page.ai_mode') }}
            </div>
            <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:1.25rem;">
                {{ __('ui.ai_settings_page.ai_mode_hint') }}
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.875rem;">
                @foreach($modes as $value => $mode)
                <button type="button"
                        @click="selectedMode = '{{ $value }}'"
                        :style="selectedMode === '{{ $value }}'
                            ? 'border-color:{{ $mode['color'] }};background:{{ $mode['bg'] }};'
                            : 'border-color:var(--card-border);background:transparent;'"
                        style="border:2px solid;border-radius:1rem;padding:1.25rem 1rem;text-align:center;cursor:pointer;transition:all .18s;width:100%;">
                    <div :style="selectedMode === '{{ $value }}'
                                ? 'background:{{ $mode['color'] }};color:#fff;'
                                : 'background:var(--page-bg);color:var(--text-muted);'"
                         style="width:2.75rem;height:2.75rem;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto .875rem;transition:all .18s;font-size:1.25rem;">
                        <i class="{{ $mode['icon'] }}"></i>
                    </div>
                    <div :style="selectedMode === '{{ $value }}' ? 'color:{{ $mode['color'] }}' : 'color:var(--text-primary)'"
                         style="font-weight:700;font-size:.875rem;margin-bottom:.375rem;transition:color .18s;">
                        {{ $mode['label'] }}
                    </div>
                    <div style="font-size:.75rem;color:var(--text-muted);line-height:1.4;">
                        {{ $mode['desc'] }}
                    </div>
                    <div x-show="selectedMode === '{{ $value }}'"
                         style="margin-top:.75rem;display:flex;justify-content:center;">
                        <span style="width:.5rem;height:.5rem;border-radius:50%;background:{{ $mode['color'] }};display:inline-block;"></span>
                    </div>
                </button>
                @endforeach
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
                <div class="card" x-show="selectedMode === 'off'" style="padding:2rem;text-align:center;">
                    <div style="font-size:2rem;margin-bottom:.75rem;">🤖</div>
                    <div style="font-weight:600;font-size:.9375rem;color:var(--text-primary);margin-bottom:.375rem;">
                        {{ __('ui.ai_settings_page.ai_disabled') }}
                    </div>
                    <div style="font-size:.8125rem;color:var(--text-muted);">
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
                        <div style="display:flex;flex-wrap:wrap;gap:.375rem;margin-bottom:.75rem;min-height:2.25rem;padding:.5rem;background:var(--page-bg);border-radius:.625rem;border:1px solid var(--card-border);">
                            <template x-if="keywords.length === 0">
                                <span style="font-size:.75rem;color:var(--text-muted);align-self:center;">
                                    {{ __('ui.ai_settings_page.no_keywords') }}
                                </span>
                            </template>
                            <template x-for="(kw, i) in keywords" :key="i">
                                <span style="display:inline-flex;align-items:center;gap:.375rem;padding:.25rem .625rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:999px;font-size:.8125rem;color:#ef4444;">
                                    <span x-text="kw"></span>
                                    <button type="button" @click="remove(i)"
                                            style="background:none;border:none;cursor:pointer;color:#ef4444;padding:0;line-height:1;font-size:1rem;display:flex;align-items:center;">
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
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.ai_settings_page.reply_language') }}</label>
                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;"
                                 x-data="{ lang: '{{ old('reply_language', $settings->reply_language ?? 'auto') }}' }">
                                @foreach($languages as $code => $lang)
                                <button type="button"
                                        @click="lang = '{{ $code }}'"
                                        :style="lang === '{{ $code }}'
                                            ? 'border-color:var(--brand);background:rgba(16,185,129,.08);color:var(--brand);font-weight:600;'
                                            : 'border-color:var(--card-border);background:transparent;color:var(--text-secondary);'"
                                        style="border:1.5px solid;border-radius:.625rem;padding:.5rem .25rem;cursor:pointer;font-size:.8rem;text-align:center;transition:all .15s;display:flex;flex-direction:column;align-items:center;gap:.2rem;">
                                    <span style="font-size:1.1rem;">{{ $lang['flag'] }}</span>
                                    <span>{{ $lang['label'] }}</span>
                                </button>
                                @endforeach
                                <input type="hidden" name="reply_language" :value="lang">
                            </div>
                            <div class="form-hint">{{ __('ui.ai_settings_page.reply_language_hint') }}</div>
                        </div>

                        {{-- Suggestion count (only for suggestion / hybrid) --}}
                        <div x-show="selectedMode === 'suggestion' || selectedMode === 'hybrid'">
                            <label class="form-label">{{ __('ui.ai_settings_page.suggestion_count') }}</label>
                            <div style="display:flex;gap:.5rem;align-items:center;margin-top:.375rem;"
                                 x-data="{ count: {{ old('suggestion_count', $settings->suggestion_count ?? 3) }} }">
                                @foreach([1,2,3,4,5] as $n)
                                <button type="button"
                                        @click="count = {{ $n }}"
                                        :style="count === {{ $n }}
                                            ? 'background:var(--brand);color:#fff;border-color:var(--brand);'
                                            : 'background:transparent;color:var(--text-secondary);border-color:var(--card-border);'"
                                        style="width:2.5rem;height:2.5rem;border:1.5px solid;border-radius:.625rem;cursor:pointer;font-size:.875rem;font-weight:600;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;">
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

                        {{-- Autonomous info badge --}}
                        <div x-show="selectedMode === 'autonomous'"
                             style="padding:.75rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:.625rem;display:flex;gap:.625rem;align-items:flex-start;">
                            <i class="ri-flashlight-line" style="color:#10b981;font-size:1rem;margin-top:.1rem;flex-shrink:0;"></i>
                            <div style="font-size:.8125rem;color:var(--text-secondary);">
                                {{ __('ui.ai_settings_page.autonomous_info') }}
                            </div>
                        </div>

                        {{-- Hybrid info badge --}}
                        <div x-show="selectedMode === 'hybrid'"
                             style="padding:.75rem;background:rgba(139,92,246,.06);border:1px solid rgba(139,92,246,.2);border-radius:.625rem;display:flex;gap:.625rem;align-items:flex-start;">
                            <i class="ri-git-branch-line" style="color:#8b5cf6;font-size:1rem;margin-top:.1rem;flex-shrink:0;"></i>
                            <div style="font-size:.8125rem;color:var(--text-secondary);">
                                {{ __('ui.ai_settings_page.hybrid_info') }}
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Token Quota --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">{{ __('ui.ai_settings_page.token_quota') }}</div>
                            <div class="card-subtitle">{{ __('ui.ai_settings_page.monthly_usage_limit') }}</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.ai_settings_page.monthly_token_limit') }}</label>
                            <input type="number" name="monthly_token_quota" min="0"
                                   value="{{ old('monthly_token_quota', $settings->monthly_token_quota) }}"
                                   class="form-control @error('monthly_token_quota') error @enderror">
                            @error('monthly_token_quota') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-hint">{{ __('ui.ai_settings_page.unlimited_hint') }}</div>
                        </div>

                        @if($settings->monthly_token_quota > 0)
                        <div style="padding:.875rem;background:var(--page-bg);border-radius:.625rem;">
                            <div style="display:flex;justify-content:space-between;font-size:.8125rem;margin-bottom:.5rem;">
                                <span style="color:var(--text-secondary);">{{ __('ui.ai_settings_page.this_period') }}</span>
                                <span style="font-weight:600;">{{ $settings->quotaPercentage() }}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill {{ $settings->quotaPercentage() > 90 ? 'danger' : ($settings->quotaPercentage() > 70 ? 'warning' : '') }}"
                                     style="width:{{ $settings->quotaPercentage() }}%"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-top:.5rem;font-size:.75rem;color:var(--text-muted);">
                                <span>{{ number_format($settings->tokens_used_this_period) }} {{ __('ui.ai_settings_page.used') }}</span>
                                <span>{{ __('ui.ai_settings_page.resets_on', ['date' => $settings->quota_reset_at?->format('M j')]) }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Test Sandbox --}}
                <div class="card" x-show="selectedMode !== 'off'" x-data="aiTest()">
                    <div class="card-header">
                        <div>
                            <div class="card-title">{{ __('ui.ai_settings_page.test_sandbox') }}</div>
                            <div class="card-subtitle">{{ __('ui.ai_settings_page.test_sandbox_hint') }}</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.75rem;">
                        <textarea x-model="testMessage" rows="3" class="form-control"
                                  placeholder="{{ __('ui.ai_settings_page.test_placeholder') }}"></textarea>
                        <button type="button" @click="runTest()" :disabled="testing || !testMessage.trim()"
                                class="btn btn-outline btn-sm">
                            <span x-show="!testing"><i class="ri-send-plane-line"></i> {{ __('ui.ai_settings_page.run_test') }}</span>
                            <span x-show="testing" style="display:flex;align-items:center;gap:.375rem;">
                                <div class="spinner" style="width:.875rem;height:.875rem;border-width:2px;"></div>
                                {{ __('ui.ai_settings_page.testing') }}
                            </span>
                        </button>
                        <div x-show="response"
                             style="padding:.875rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:.625rem;">
                            <div style="font-size:.6875rem;font-weight:700;color:var(--brand);margin-bottom:.375rem;text-transform:uppercase;letter-spacing:.06em;display:flex;align-items:center;gap:.375rem;">
                                <i class="ri-sparkling-2-line"></i> {{ __('ui.ai_settings_page.ai_response') }}
                            </div>
                            <div style="font-size:.8125rem;color:var(--text-secondary);white-space:pre-wrap;" x-text="response"></div>
                            <div x-show="tokensUsed" style="font-size:.6875rem;color:var(--text-muted);margin-top:.5rem;"
                                 x-text="`${tokensUsed} {{ __('ui.ai_settings_page.tokens_used') }}`"></div>
                        </div>
                        <div x-show="error"
                             style="padding:.75rem;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:.625rem;font-size:.8125rem;color:#ef4444;"
                             x-text="error"></div>
                    </div>
                </div>

                {{-- Knowledge Base link --}}
                <div style="padding:1rem 1.25rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:.875rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                    <div style="display:flex;gap:.75rem;align-items:center;">
                        <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.15);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0;">
                            <i class="ri-book-2-line"></i>
                        </div>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:var(--text-primary);">{{ __('ui.ai_settings_page.knowledge_base') }}</div>
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
        <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;gap:.5rem;">
            <a href="{{ route($panelPrefix . '.dashboard') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                <i class="ri-save-line"></i> {{ __('ui.ai_settings_page.save_settings') }}
            </button>
        </div>
    </form>

</div>

@push('scripts')
<script>
function aiSettingsPage() {
    return {
        selectedMode: '{{ old('mode', $settings->mode) }}',
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

function aiTest() {
    return {
        testMessage: '',
        testing: false,
        response: null,
        tokensUsed: null,
        error: null,

        async runTest() {
            this.testing = true;
            this.response = null;
            this.error = null;
            try {
                const res = await fetch('/api/ai/test', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ message: this.testMessage })
                });
                const data = await res.json();
                if (res.ok) {
                    this.response = data.response;
                    this.tokensUsed = data.tokens_used;
                } else {
                    this.error = data.message || '{{ __('ui.ai_settings_page.test_failed') }}';
                }
            } catch {
                this.error = '{{ __('ui.ai_settings_page.network_error') }}';
            } finally {
                this.testing = false;
            }
        }
    }
}
</script>
@endpush
@endsection
