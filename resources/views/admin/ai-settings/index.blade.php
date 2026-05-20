@extends('layouts.admin')

@section('title', 'AI Settings')

@section('breadcrumb')
    <span>AI Settings</span>
@endsection

@section('content')
<div>

    {{-- Header --}}
    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">AI Auto-Reply</h1>
        <p style="font-size:.875rem;color:var(--text-muted);margin-top:.125rem">Configure Claude AI to assist or autonomously handle customer conversations</p>
    </div>

    {{-- Mode Selector --}}
    <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header">
            <div class="card-title">AI Mode</div>
        </div>
        <form action="{{ route('admin.ai-settings.update') }}" method="POST" id="mode-form">
            @csrf @method('PUT')
            <div style="padding:0 1.5rem 1.5rem;display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem">
                @php
                    $modes = [
                        'off'        => ['label' => 'Off',        'desc' => 'AI disabled',                        'icon' => '<path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>', 'color' => '#6b7280'],
                        'suggestion' => ['label' => 'Suggestion', 'desc' => 'AI drafts, agent sends',             'icon' => '<path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>', 'color' => '#f59e0b'],
                        'autonomous' => ['label' => 'Autonomous', 'desc' => 'AI replies automatically',           'icon' => '<path d="M13 10V3L4 14h7v7l9-11h-7z"/>', 'color' => '#10b981'],
                        'hybrid'     => ['label' => 'Hybrid',     'desc' => 'Auto until escalation keyword',     'icon' => '<path d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', 'color' => '#8b5cf6'],
                    ];
                @endphp
                @foreach($modes as $value => $mode)
                <label style="cursor:pointer">
                    <input type="radio" name="mode" value="{{ $value }}" class="sr-only"
                           {{ $settings->mode === $value ? 'checked' : '' }}
                           onchange="document.getElementById('mode-form').submit()">
                    <div style="padding:1rem;border:2px solid {{ $settings->mode === $value ? $mode['color'] : 'var(--card-border)' }};border-radius:.875rem;text-align:center;transition:all .2s;background:{{ $settings->mode === $value ? 'rgba('.($value==='off'?'107,114,128':($value==='suggestion'?'245,158,11':($value==='autonomous'?'16,185,129':'139,92,246'))).',.06)' : 'transparent' }}"
                         onmouseenter="if(this.parentElement.querySelector('input').value !== '{{ $settings->mode }}') this.style.borderColor='{{ $mode['color'] }}40'"
                         onmouseleave="if(this.parentElement.querySelector('input').value !== '{{ $settings->mode }}') this.style.borderColor='var(--card-border)'">
                        <div style="width:2.5rem;height:2.5rem;border-radius:50%;background:{{ $settings->mode === $value ? $mode['color'] : 'var(--page-bg)' }};display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;transition:all .2s">
                            <svg width="18" height="18" fill="none" stroke="{{ $settings->mode === $value ? '#fff' : 'var(--text-muted)' }}" stroke-width="2" viewBox="0 0 24 24">{!! $mode['icon'] !!}</svg>
                        </div>
                        <div style="font-weight:600;font-size:.875rem;color:{{ $settings->mode === $value ? $mode['color'] : 'var(--text-primary)' }}">{{ $mode['label'] }}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.25rem">{{ $mode['desc'] }}</div>
                    </div>
                </label>
                @endforeach
            </div>
        </form>
    </div>

    {{-- Settings Form --}}
    <form action="{{ route('admin.ai-settings.update') }}" method="POST" data-loading>
        @csrf @method('PUT')
        <input type="hidden" name="mode" value="{{ $settings->mode }}">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

            {{-- Left --}}
            <div style="display:flex;flex-direction:column;gap:1.5rem">

                {{-- System Prompt --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">System Prompt</div>
                            <div class="card-subtitle">Define AI's persona and behavior</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem">
                        <textarea name="system_prompt" rows="8"
                                  class="form-control @error('system_prompt') error @enderror"
                                  placeholder="You are a helpful customer support agent for {{ auth()->user()->tenant->name }}. Be concise, friendly, and professional.&#10;&#10;Always:&#10;- Greet customers warmly&#10;- Provide accurate information&#10;- Escalate complex issues to human agents">{{ old('system_prompt', $settings->system_prompt) }}</textarea>
                        @error('system_prompt') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">The AI will follow these instructions when generating responses</div>
                    </div>
                </div>

                {{-- Escalation Keywords --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Escalation Keywords</div>
                            <div class="card-subtitle">AI will stop and alert agents when these are detected</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem" x-data="keywordManager(@json($settings->escalation_keywords ?? []))">
                        <div style="display:flex;flex-wrap:wrap;gap:.375rem;margin-bottom:.75rem;min-height:2rem">
                            <template x-for="(kw, i) in keywords" :key="i">
                                <span style="display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .625rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:999px;font-size:.8125rem;color:#ef4444">
                                    <span x-text="kw"></span>
                                    <button type="button" @click="remove(i)" style="background:none;border:none;cursor:pointer;color:#ef4444;padding:0;line-height:1;font-size:.875rem">&times;</button>
                                </span>
                            </template>
                        </div>
                        <div style="display:flex;gap:.5rem">
                            <input type="text" x-model="newKw" @keydown.enter.prevent="add()"
                                   placeholder="e.g. human, agent, escalate…"
                                   class="form-control" style="flex:1">
                            <button type="button" @click="add()" class="btn btn-outline btn-sm">Add</button>
                        </div>
                        <input type="hidden" name="escalation_keywords" :value="JSON.stringify(keywords)">
                    </div>
                </div>
            </div>

            {{-- Right --}}
            <div style="display:flex;flex-direction:column;gap:1.5rem">

                {{-- Quota --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Token Quota</div>
                            <div class="card-subtitle">Monthly usage limit</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem">
                        <div class="form-group">
                            <label class="form-label">Monthly Token Limit</label>
                            <input type="number" name="monthly_token_quota" min="0"
                                   value="{{ old('monthly_token_quota', $settings->monthly_token_quota) }}"
                                   class="form-control @error('monthly_token_quota') error @enderror">
                            @error('monthly_token_quota') <div class="form-error">{{ $message }}</div> @enderror
                            <div class="form-hint">Set 0 for unlimited</div>
                        </div>

                        {{-- Usage Progress --}}
                        @if($settings->monthly_token_quota > 0)
                        <div style="padding:.875rem;background:var(--page-bg);border-radius:.625rem">
                            <div style="display:flex;justify-content:space-between;font-size:.8125rem;margin-bottom:.5rem">
                                <span style="color:var(--text-secondary)">This period</span>
                                <span style="font-weight:600">{{ $settings->quotaPercentage() }}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill {{ $settings->quotaPercentage() > 90 ? 'danger' : ($settings->quotaPercentage() > 70 ? 'warning' : '') }}"
                                     style="width:{{ $settings->quotaPercentage() }}%"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-top:.5rem;font-size:.75rem;color:var(--text-muted)">
                                <span>{{ number_format($settings->tokens_used_this_period) }} used</span>
                                <span>Resets {{ $settings->quota_reset_at?->format('M j') }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Test Sandbox --}}
                <div class="card" x-data="aiTest()">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Test Sandbox</div>
                            <div class="card-subtitle">Try the AI with your current settings</div>
                        </div>
                    </div>
                    <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.75rem">
                        <textarea x-model="testMessage" rows="3"
                                  class="form-control"
                                  placeholder="Type a customer message to test…"></textarea>
                        <button type="button" @click="runTest()" :disabled="testing || !testMessage.trim()"
                                class="btn btn-outline btn-sm">
                            <span x-show="!testing">Run Test</span>
                            <span x-show="testing" style="display:flex;align-items:center;gap:.375rem">
                                <div class="spinner" style="width:.875rem;height:.875rem;border-width:2px"></div>
                                Testing…
                            </span>
                        </button>
                        <div x-show="response" style="padding:.75rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:.625rem">
                            <div style="font-size:.6875rem;font-weight:600;color:var(--brand);margin-bottom:.375rem;text-transform:uppercase;letter-spacing:.05em">AI Response</div>
                            <div style="font-size:.8125rem;color:var(--text-secondary);white-space:pre-wrap" x-text="response"></div>
                            <div x-show="tokensUsed" style="font-size:.6875rem;color:var(--text-muted);margin-top:.5rem" x-text="`${tokensUsed} tokens used`"></div>
                        </div>
                        <div x-show="error" style="padding:.75rem;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:.625rem;font-size:.8125rem;color:#ef4444" x-text="error"></div>
                    </div>
                </div>

                {{-- Knowledge Base Link --}}
                <div style="padding:1rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:.75rem;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-size:.875rem;font-weight:600;color:var(--text-primary)">Knowledge Base</div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">
                            {{ $knowledgeCount }} entries · Used to ground AI responses
                        </div>
                    </div>
                    <a href="{{ route('admin.knowledge.index') }}" class="btn btn-outline btn-sm">Manage</a>
                </div>
            </div>
        </div>

        <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;gap:.5rem">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<script>
function keywordManager(initial) {
    return {
        keywords: initial || [],
        newKw: '',
        add() {
            const k = this.newKw.trim().toLowerCase();
            if (k && !this.keywords.includes(k)) {
                this.keywords.push(k);
            }
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
                    this.error = data.message || 'Test failed';
                }
            } catch {
                this.error = 'Network error';
            } finally {
                this.testing = false;
            }
        }
    }
}
</script>
@endsection
