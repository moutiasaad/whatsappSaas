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
        'color' => '#8b5cf6',
        'shadow'=> 'rgba(139,92,246,.22)',
        'bg'    => 'rgba(139,92,246,.07)',
        'icon'  => 'ri-git-branch-line',
    ],
];
$sampleTests = [
    __('ui.ai_settings_page.sample_1'),
    __('ui.ai_settings_page.sample_2'),
    __('ui.ai_settings_page.sample_3'),
];
@endphp

<style>
.ai-mode-card {
    border: 2px solid var(--card-border);
    border-radius: 1rem;
    padding: 1.375rem 1rem 1.125rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .18s, box-shadow .18s, background .18s, transform .12s;
    width: 100%;
    background: transparent;
    position: relative;
    overflow: hidden;
}
.ai-mode-card:hover { transform: translateY(-1px); }
.ai-mode-card .mode-icon {
    width: 2.75rem; height: 2.75rem; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto .875rem; font-size: 1.25rem;
    transition: background .18s, color .18s;
    background: var(--page-bg); color: var(--text-muted);
}
.ai-mode-card .mode-label {
    font-weight: 700; font-size: .875rem; margin-bottom: .3rem;
    color: var(--text-primary); transition: color .18s;
}
.ai-mode-card .mode-desc {
    font-size: .75rem; color: var(--text-muted); line-height: 1.4;
}
.ai-mode-card::after {
    content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
    width: 0; height: 3px; border-radius: 3px 3px 0 0;
    transition: width .2s;
}
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
.sample-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .3rem .75rem; border-radius: 999px; font-size: .75rem;
    border: 1px solid var(--card-border); background: var(--page-bg);
    cursor: pointer; transition: all .15s; color: var(--text-secondary);
    white-space: nowrap;
}
.sample-chip:hover { border-color: var(--brand); color: var(--brand); background: rgba(16,185,129,.05); }
.ai-response-box {
    padding: 1rem; border-radius: .75rem;
    background: rgba(16,185,129,.05); border: 1px solid rgba(16,185,129,.18);
}
.ai-response-label {
    font-size: .6875rem; font-weight: 700; color: var(--brand);
    text-transform: uppercase; letter-spacing: .07em;
    display: flex; align-items: center; gap: .375rem; margin-bottom: .5rem;
}
.ai-response-text {
    font-size: .8125rem; color: var(--text-secondary);
    white-space: pre-wrap; line-height: 1.6;
}
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
                        class="ai-mode-card"
                        @click="selectedMode = '{{ $value }}'"
                        :style="selectedMode === '{{ $value }}'
                            ? 'border-color:{{ $mode['color'] }};background:{{ $mode['bg'] }};box-shadow:0 4px 18px {{ $mode['shadow'] }};'
                            : ''">
                    <div class="mode-icon"
                         :style="selectedMode === '{{ $value }}'
                             ? 'background:{{ $mode['color'] }};color:#fff;'
                             : ''">
                        <i class="{{ $mode['icon'] }}"></i>
                    </div>
                    <div class="mode-label"
                         :style="selectedMode === '{{ $value }}' ? 'color:{{ $mode['color'] }}' : ''">
                        {{ $mode['label'] }}
                    </div>
                    <div class="mode-desc">{{ $mode['desc'] }}</div>
                    <div x-show="selectedMode === '{{ $value }}'"
                         style="position:absolute;bottom:0;left:12%;right:12%;height:3px;border-radius:3px 3px 0 0;background:{{ $mode['color'] }};"></div>
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
                             class="info-badge" style="background:rgba(139,92,246,.06);border:1px solid rgba(139,92,246,.2);">
                            <i class="ri-git-branch-line" style="color:#8b5cf6;font-size:1rem;margin-top:.1rem;flex-shrink:0;"></i>
                            <span>{{ __('ui.ai_settings_page.hybrid_info') }}</span>
                        </div>

                    </div>
                </div>

                {{-- ── Test Sandbox ────────────────────────────────────────── --}}
                <div class="card" x-show="selectedMode !== 'off'" x-data="aiTest()">
                    <div class="card-header">
                        <div style="flex:1">
                            <div class="card-title">{{ __('ui.ai_settings_page.test_sandbox') }}</div>
                            <div class="card-subtitle">{{ __('ui.ai_settings_page.test_sandbox_hint') }}</div>
                        </div>
                        <button type="button" x-show="response || error" @click="response=null;error=null;tokensUsed=null"
                                class="btn btn-ghost btn-sm" style="flex-shrink:0;">
                            <i class="ri-refresh-line"></i> {{ __('ui.ai_settings_page.clear_response') }}
                        </button>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.875rem;">

                        {{-- Quick sample chips --}}
                        <div>
                            <div style="font-size:.7rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;">
                                <i class="ri-magic-line" style="font-size:.8rem;"></i> {{ __('ui.ai_settings_page.quick_tests') }}
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:.375rem;">
                                @foreach($sampleTests as $sample)
                                <button type="button" class="sample-chip"
                                        @click="testMessage = '{{ $sample }}'; runTest()">
                                    <i class="ri-sparkling-2-line" style="font-size:.8rem;opacity:.7;"></i>
                                    {{ $sample }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Textarea --}}
                        <div style="position:relative;">
                            <textarea x-model="testMessage" rows="3" class="form-control"
                                      placeholder="{{ __('ui.ai_settings_page.test_placeholder') }}"
                                      style="resize:vertical;padding-bottom:2.5rem;"></textarea>
                            <button type="button"
                                    @click="runTest()"
                                    :disabled="testing || !testMessage.trim()"
                                    style="position:absolute;bottom:.5rem;right:.5rem;padding:.375rem .875rem;border-radius:.5rem;border:none;cursor:pointer;font-size:.8125rem;font-weight:600;display:flex;align-items:center;gap:.375rem;transition:all .15s;"
                                    :style="(testing || !testMessage.trim())
                                        ? 'background:var(--page-bg);color:var(--text-muted);cursor:not-allowed;'
                                        : 'background:var(--brand);color:#fff;box-shadow:0 2px 8px rgba(16,185,129,.3);'">
                                <template x-if="!testing">
                                    <span style="display:flex;align-items:center;gap:.3rem;">
                                        <i class="ri-sparkling-2-fill"></i> {{ __('ui.ai_settings_page.run_test') }}
                                    </span>
                                </template>
                                <template x-if="testing">
                                    <span style="display:flex;align-items:center;gap:.375rem;">
                                        <div class="spinner" style="width:.8rem;height:.8rem;border-width:2px;"></div>
                                        {{ __('ui.ai_settings_page.testing') }}
                                    </span>
                                </template>
                            </button>
                        </div>

                        {{-- AI Response --}}
                        <div x-show="response" x-transition class="ai-response-box">
                            <div class="ai-response-label">
                                <i class="ri-robot-2-line"></i> {{ __('ui.ai_settings_page.ai_response') }}
                                <span x-show="tokensUsed"
                                      style="margin-left:auto;font-weight:400;color:var(--text-muted);letter-spacing:0;"
                                      x-text="tokensUsed + ' {{ __('ui.ai_settings_page.tokens_used') }}'"></span>
                            </div>
                            <div class="ai-response-text" x-text="response"></div>
                        </div>

                        {{-- Error --}}
                        <div x-show="error" x-transition
                             style="padding:.75rem 1rem;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:.625rem;font-size:.8125rem;color:#ef4444;display:flex;gap:.5rem;align-items:flex-start;">
                            <i class="ri-error-warning-line" style="flex-shrink:0;margin-top:.05rem;"></i>
                            <span x-text="error"></span>
                        </div>

                    </div>
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
        testing:     false,
        response:    null,
        tokensUsed:  null,
        error:       null,

        async runTest() {
            if (!this.testMessage.trim()) return;
            this.testing  = true;
            this.response = null;
            this.error    = null;
            try {
                const res = await fetch('/api/ai/test', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ question: this.testMessage }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.response   = data.answer;
                    this.tokensUsed = data.tokens_used || null;
                } else {
                    this.error = data.message || data.errors?.question?.[0] || '{{ __('ui.ai_settings_page.test_failed') }}';
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
