@extends('layouts.admin')

@section('title', __('ui.settings_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.settings_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $u   = auth()->user();
    $px  = $u->routeNamePrefix();
    $ws  = $settings['workspace'] ?? [];
    $loc = $settings['locale'] ?? [];
    $hrs = $settings['hours'] ?? [];
    $sec = $settings['security'] ?? [];
    $ntf = $settings['notify'] ?? [];

    $dayHours = collect($days)->mapWithKeys(fn ($d) => [$d => [
        'open'   => old("hours.$d.open",   data_get($hrs, "days.$d.open")   ?? '09:00'),
        'close'  => old("hours.$d.close",  data_get($hrs, "days.$d.close")  ?? '18:00'),
        'closed' => (bool) old("hours.$d.closed", data_get($hrs, "days.$d.closed", in_array($d, ['sat', 'sun'], true))),
    ]])->all();

    $initial = [
        'name'            => (string) old('name', $tenant->name),
        'support_email'   => (string) old('support_email', $ws['support_email'] ?? ''),
        'customer_name'   => (string) old('customer_name', $ws['customer_name'] ?? ''),
        'reply_signature' => (string) old('reply_signature', $ws['reply_signature'] ?? ''),
        'locale'          => (string) old('locale', $loc['default'] ?? app()->getLocale()),
        'timezone'        => (string) old('timezone', $tenant->timezone ?: config('app.timezone')),
        'date_format'     => (string) old('date_format', $loc['date_format'] ?? 'd/m/Y'),
        'time_format'     => (string) old('time_format', $loc['time_format'] ?? 'H:i'),
        'auto_close'      => (int)    old('auto_close_minutes', $tenant->autoCloseMinutes()),
        'hours_enabled'   => (bool)   old('hours_enabled', data_get($hrs, 'enabled', false)),
        'hours'           => $dayHours,
        'notify'          => collect($notifyKeys)->mapWithKeys(fn ($k) => [
                                 $k => (bool) old("notify.$k", $ntf[$k] ?? in_array($k, ['unclaimed', 'ai_escalated'], true)),
                             ])->all(),
        'allowed_domains' => (string) old('allowed_domains', implode(', ', (array) ($sec['allowed_domains'] ?? []))),
    ];

    // A section is display:none unless active, so an error inside a collapsed
    // one would be invisible. Open whichever section failed validation.
    $fieldSection = [
        'name' => 'workspace', 'support_email' => 'workspace',
        'customer_name' => 'branding', 'reply_signature' => 'branding',
        'locale' => 'locale', 'timezone' => 'locale', 'date_format' => 'locale', 'time_format' => 'locale',
        'hours' => 'hours', 'notify' => 'notifications',
        'auto_close_minutes' => 'conversations',
        'allowed_domains' => 'security',
    ];
    $openSection = 'workspace';
    foreach (array_keys($errors->getMessages()) as $field) {
        $root = explode('.', $field)[0];
        if (isset($fieldSection[$root])) { $openSection = $fieldSection[$root]; break; }
    }
@endphp

{{-- The console fills the viewport and manages its own scroll regions, so the
     shared .page-content padding/height is neutralised for this route. Without
     this the shell renders inside the padded page block and reads as a card on
     a page rather than a console. --}}
<script>document.body.classList.add('wc-host');</script>

<div class="wv-console" x-data="workspaceSettings()" x-cloak>
<form method="POST" action="{{ route($px . '.settings.update') }}" class="wc-shell"
      x-ref="form" @submit="onSubmit()" data-no-unsaved-guard>
    @csrf
    @method('PUT')

    {{-- ══ PAGE HEAD ═══════════════════════════════════════════════ --}}
    <div class="wc-phead">
        <div class="m">
            <h1>
                {{ __('ui.settings_page.page_title') }}
                <span class="wc-pill on"><i></i><span>{{ $tenant->plan?->name ?? __('ui.tenant_billing.no_plan') }}</span></span>
            </h1>
            <p>{{ __('ui.settings_page.subtitle') }}</p>
        </div>
        <div class="acts">
            <span class="wc-dirty" :class="dirty ? 'on' : ''">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 8v4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
                {{ __('ui.settings_page.unsaved') }}
            </span>
            <button type="button" class="wc-btn g" @click="discard()" :disabled="!dirty">{{ __('ui.settings_page.discard') }}</button>
            <button type="submit" class="wc-btn p" :disabled="saving">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                {{ __('ui.save') }}
            </button>
        </div>
    </div>

    {{-- no-prev: this console has no preview pane, so the shell's third grid
         column is collapsed. Left at its default the form would stop 348px
         short of the right edge and leave a dead strip. --}}
    <div class="wc-body no-prev">

        {{-- ══ SECTION NAV ══════════════════════════════════════════ --}}
        <nav class="wc-snav">
            <div class="lbl">{{ __('ui.settings_page.nav_label') }}</div>
            @php
                $nav = array_values(array_filter([
                    ['k' => 'workspace',     'i' => 'ri-building-2-line'],
                    ['k' => 'branding',      'i' => 'ri-palette-line'],
                    ['k' => 'locale',        'i' => 'ri-global-line'],
                    ['k' => 'hours',         'i' => 'ri-time-line'],
                    ['k' => 'notifications', 'i' => 'ri-notification-3-line'],
                    ['k' => 'conversations', 'i' => 'ri-message-3-line'],
                    ['k' => 'security',      'i' => 'ri-shield-keyhole-line'],
                    ['k' => 'plan',          'i' => 'ri-vip-crown-2-line'],
                ]));
            @endphp
            @foreach($nav as $n)
            <button type="button" class="wc-sn" :class="section === '{{ $n['k'] }}' ? 'on' : ''" @click="go('{{ $n['k'] }}')">
                <i class="{{ $n['i'] }}" style="font-size:16px"></i>
                <span>{{ __('ui.settings_page.nav_' . $n['k']) }}</span>
                @if($n['k'] === 'notifications')
                    <span class="n" x-text="Object.values(form.notify).filter(Boolean).length"></span>
                @endif
            </button>
            @endforeach
        </nav>

        {{-- ══ FORM ═════════════════════════════════════════════════ --}}
        <div class="wc-form" x-ref="formScroll"><div class="wc-fwrap">

            {{-- ── WORKSPACE ───────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'workspace'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.workspace_title') }}</h2>
                    <p>{{ __('ui.settings_page.workspace_sub') }}</p>
                </div>

                <div class="wc-fld">
                    <label class="wc-flabel" for="name">{{ __('ui.settings_page.name') }}</label>
                    <input id="name" name="name" type="text" maxlength="150" required class="wc-inp" x-model="form.name">
                    @error('name') <div class="wc-err">{{ $message }}</div> @enderror
                </div>

                <div class="wc-fld">
                    <label class="wc-flabel" for="slug">{{ __('ui.settings_page.slug') }}</label>
                    {{-- Read-only: the slug is already baked into live widget
                         embeds and webhook URLs. --}}
                    <input id="slug" type="text" value="{{ $tenant->slug }}" class="wc-inp" disabled>
                    <div class="wc-hint">{{ __('ui.settings_page.slug_help') }}</div>
                </div>

                <div class="wc-fld">
                    <label class="wc-flabel" for="support_email">{{ __('ui.settings_page.support_email') }}</label>
                    <input id="support_email" name="support_email" type="email" maxlength="190"
                           placeholder="support@example.com" class="wc-inp" x-model="form.support_email">
                    <div class="wc-hint">{{ __('ui.settings_page.support_email_help') }}</div>
                    @error('support_email') <div class="wc-err">{{ $message }}</div> @enderror
                </div>
            </section>

            {{-- ── BRANDING ────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'branding'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.branding_title') }}</h2>
                    <p>{{ __('ui.settings_page.branding_sub') }}</p>
                </div>

                <div class="wc-fld">
                    <label class="wc-flabel" for="customer_name">{{ __('ui.settings_page.customer_name') }}</label>
                    <input id="customer_name" name="customer_name" type="text" maxlength="150"
                           placeholder="{{ $tenant->name }}" class="wc-inp" x-model="form.customer_name">
                    <div class="wc-hint">{{ __('ui.settings_page.customer_name_help') }}</div>
                </div>

                <div class="wc-fld">
                    <label class="wc-flabel" for="reply_signature">{{ __('ui.settings_page.signature') }}</label>
                    <textarea id="reply_signature" name="reply_signature" rows="3" maxlength="300"
                              class="wc-inp" x-model="form.reply_signature"></textarea>
                    <div class="wc-hint">{{ __('ui.settings_page.signature_help') }}</div>
                </div>

                {{-- Live preview of what the customer actually receives. --}}
                <div class="wc-fld">
                    <div class="wc-flabel">{{ __('ui.settings_page.signature_preview') }}</div>
                    <div class="wc-bub out">
                        <div>{{ __('ui.settings_page.signature_sample') }}</div>
                        <template x-if="form.reply_signature.trim()">
                            <div style="margin-top:8px;opacity:.85;white-space:pre-line" x-text="form.reply_signature"></div>
                        </template>
                    </div>
                </div>
            </section>

            {{-- ── LANGUAGE & FORMAT ───────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'locale'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.locale_title') }}</h2>
                    <p>{{ __('ui.settings_page.locale_sub') }}</p>
                </div>

                <div class="wc-grid2">
                    <div class="wc-fld">
                        <label class="wc-flabel" for="locale">{{ __('ui.settings_page.language') }}</label>
                        <select id="locale" name="locale" class="wc-sel" x-model="form.locale">
                            @foreach($locales as $code => $meta)
                            <option value="{{ $code }}">{{ $meta['native'] ?? $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wc-fld">
                        <label class="wc-flabel" for="timezone">{{ __('ui.settings_page.timezone') }}</label>
                        <select id="timezone" name="timezone" class="wc-sel" x-model="form.timezone">
                            @foreach($timezones as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                        <div class="wc-hint">{{ __('ui.settings_page.timezone_help') }}</div>
                    </div>
                </div>

                <div class="wc-grid2">
                    <div class="wc-fld">
                        <label class="wc-flabel" for="date_format">{{ __('ui.settings_page.date_format') }}</label>
                        <select id="date_format" name="date_format" class="wc-sel" x-model="form.date_format">
                            @foreach($dateFormats as $f)
                            <option value="{{ $f }}">{{ now()->format($f) }} — {{ $f }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wc-fld">
                        <label class="wc-flabel" for="time_format">{{ __('ui.settings_page.time_format') }}</label>
                        <select id="time_format" name="time_format" class="wc-sel" x-model="form.time_format">
                            @foreach($timeFormats as $f)
                            <option value="{{ $f }}">{{ now()->format($f) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            {{-- ── BUSINESS HOURS ──────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'hours'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.hours_title') }}</h2>
                    <p>{{ __('ui.settings_page.hours_sub') }}</p>
                </div>

                <div class="wc-fld">
                    <div class="wc-trow" :class="form.hours_enabled ? 'hi' : ''">
                        <span class="ico" style="background:#d97706"><i class="ri-time-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.settings_page.hours_enable') }}</div>
                            <div class="s">{{ __('ui.settings_page.hours_enable_sub') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.hours_enabled ? 'on' : ''"
                                role="switch" :aria-checked="form.hours_enabled ? 'true' : 'false'"
                                @click="form.hours_enabled = !form.hours_enabled"></button>
                        <input type="hidden" name="hours_enabled" :value="form.hours_enabled ? 1 : 0">
                    </div>
                </div>

                <template x-if="form.hours_enabled">
                    <div class="wc-fld">
                        @foreach($days as $d)
                        <div class="st-day" :class="form.hours.{{ $d }}.closed ? 'off' : ''">
                            <span class="d">{{ __('ui.settings_page.day_' . $d) }}</span>
                            <button type="button" class="wc-tg sm" :class="!form.hours.{{ $d }}.closed ? 'on' : ''"
                                    role="switch" :aria-checked="!form.hours.{{ $d }}.closed ? 'true' : 'false'"
                                    @click="form.hours.{{ $d }}.closed = !form.hours.{{ $d }}.closed"
                                    aria-label="{{ __('ui.settings_page.day_' . $d) }}"></button>
                            <input type="hidden" name="hours[{{ $d }}][closed]" :value="form.hours.{{ $d }}.closed ? 1 : 0">
                            <template x-if="!form.hours.{{ $d }}.closed">
                                <span class="t">
                                    <input type="time" class="wc-inp wc-inp-sm" name="hours[{{ $d }}][open]"  x-model="form.hours.{{ $d }}.open">
                                    <span class="sep">–</span>
                                    <input type="time" class="wc-inp wc-inp-sm" name="hours[{{ $d }}][close]" x-model="form.hours.{{ $d }}.close">
                                </span>
                            </template>
                            <template x-if="form.hours.{{ $d }}.closed">
                                <span class="cl">{{ __('ui.settings_page.day_closed') }}</span>
                            </template>
                        </div>
                        @endforeach
                        <div class="wc-hint" style="margin-top:10px">{{ __('ui.settings_page.hours_tz', ['tz' => $tenant->timezone ?: config('app.timezone')]) }}</div>
                    </div>
                </template>

                <div class="wc-fld">
                    <div class="wc-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 11v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="7.8" r="1.1" fill="currentColor"/></svg>
                        <div>{{ __('ui.settings_page.hours_note') }}</div>
                    </div>
                </div>
            </section>

            {{-- ── NOTIFICATIONS ───────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'notifications'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.notif_title') }}</h2>
                    <p>{{ __('ui.settings_page.notif_sub') }}</p>
                </div>

                @foreach($notifyKeys as $k)
                <div class="wc-fld">
                    <div class="wc-trow" :class="form.notify.{{ $k }} ? 'hi' : ''">
                        <div class="m">
                            <div class="n">
                                {{ __('ui.settings_page.notif_' . $k) }}
                                @if(in_array($k, ['unclaimed', 'ai_escalated'], true))
                                <span class="wc-badge">{{ __('ui.settings_page.recommended') }}</span>
                                @endif
                            </div>
                            <div class="s">{{ __('ui.settings_page.notif_' . $k . '_sub') }}</div>
                        </div>
                        <button type="button" class="wc-tg" :class="form.notify.{{ $k }} ? 'on' : ''"
                                role="switch" :aria-checked="form.notify.{{ $k }} ? 'true' : 'false'"
                                @click="form.notify.{{ $k }} = !form.notify.{{ $k }}"></button>
                        <input type="hidden" name="notify[{{ $k }}]" :value="form.notify.{{ $k }} ? 1 : 0">
                    </div>
                </div>
                @endforeach
            </section>

            {{-- ── CONVERSATIONS ───────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'conversations'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.conv_title') }}</h2>
                    <p>{{ __('ui.settings_page.conv_sub') }}</p>
                </div>

                <div class="wc-fld">
                    <label class="wc-flabel" for="auto_close_minutes">{{ __('ui.settings_page.auto_close') }}</label>
                    <select id="auto_close_minutes" name="auto_close_minutes" class="wc-sel" x-model.number="form.auto_close">
                        @foreach($autoClose as $m)
                        <option value="{{ $m }}">
                            {{ $m === 0
                                ? __('ui.settings_page.auto_close_never')
                                : __('ui.settings_page.auto_close_after', ['human' => now()->diffForHumans(now()->addMinutes($m), \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
                        </option>
                        @endforeach
                    </select>
                    <div class="wc-hint">{{ __('ui.settings_page.auto_close_help') }}</div>
                </div>

                <div class="wc-fld">
                    <div class="wc-trow">
                        <span class="ico" style="background:#0f7e7a"><i class="ri-lock-2-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.settings_page.claim_lock') }}</div>
                            <div class="s">{{ __('ui.settings_page.claim_lock_note') }}</div>
                        </div>
                        <span class="wc-badge">{{ __('ui.settings_page.enforced') }}</span>
                    </div>
                </div>
            </section>

            {{-- ── SECURITY ────────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'security'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.security_title') }}</h2>
                    <p>{{ __('ui.settings_page.security_sub') }}</p>
                </div>

                <div class="wc-fld">
                    <label class="wc-flabel" for="allowed_domains">{{ __('ui.settings_page.allowed_domains') }}</label>
                    <input id="allowed_domains" name="allowed_domains" type="text" maxlength="500"
                           class="wc-inp" x-model="form.allowed_domains" placeholder="example.com, partner.com">
                    <div class="wc-hint">{{ __('ui.settings_page.allowed_domains_help') }}</div>
                    @error('allowed_domains') <div class="wc-err">{{ $message }}</div> @enderror
                </div>

                <div class="wc-fld" x-show="form.allowed_domains.trim()">
                    <div class="wc-chiprow">
                        <template x-for="d in domainList()" :key="d">
                            <span class="wc-tag" x-text="'@' + d"></span>
                        </template>
                    </div>
                </div>

                <div class="wc-fld">
                    <div class="wc-linkcard">
                        <span class="ico"><i class="ri-file-list-3-line"></i></span>
                        <div class="m">
                            <div class="n">{{ __('ui.settings_page.audit_title') }}</div>
                            <div class="s">{{ __('ui.settings_page.audit_sub') }}</div>
                        </div>
                        <a href="{{ route($px . '.users.index') }}" class="wc-btn g sm">{{ __('ui.manage') }}</a>
                    </div>
                </div>
            </section>

            {{-- ── PLAN & USAGE ────────────────────────────────────── --}}
            <section class="wc-sec" x-show="section === 'plan'">
                <div class="wc-sechead">
                    <h2>{{ __('ui.settings_page.plan_title') }}</h2>
                    <p>{{ __('ui.settings_page.plan_sub') }}</p>
                </div>

                @php
                    $seatPct = $usage['seats_limit'] > 0
                        ? min(100, (int) round($usage['seats_used'] / $usage['seats_limit'] * 100)) : 0;
                    $aiPct = ($usage['ai_quota'] ?? null)
                        ? min(100, (int) round($usage['ai_used'] / max(1, (int) $usage['ai_quota']) * 100)) : 0;
                @endphp

                <div class="wc-fld">
                    <div class="wc-linkcard">
                        <span class="ico"><i class="ri-vip-crown-2-line"></i></span>
                        <div class="m">
                            <div class="n">{{ $tenant->plan?->name ?? __('ui.tenant_billing.no_plan') }}</div>
                            <div class="s">
                                @if($tenant->plan)
                                    ${{ rtrim(rtrim(number_format((float) $tenant->plan->price_monthly, 2), '0'), '.') }}{{ __('landing.plan_per_month') }}
                                    · {{ __('ui.tenant_billing.status_' . ($tenant->isActive() ? 'active' : 'inactive')) }}
                                @else
                                    {{ __('ui.tenant_billing.lapsed_sub') }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route($px . '.billing.index') }}" class="wc-btn p sm">{{ __('ui.settings_page.manage_billing') }}</a>
                    </div>
                </div>

                <div class="wc-fld">
                    <div class="wc-meter">
                        <div class="hd">
                            <span class="ico" style="background:#ecf7f6;color:#0f7e7a"><i class="ri-team-line"></i></span>
                            <div class="m">
                                <div class="n">{{ __('ui.settings_page.stat_seats') }}</div>
                                <div class="s">
                                    @if($usage['extra_seats'] > 0)
                                        {{ trans_choice('ui.tenant_billing.seats_purchased_foot', $usage['extra_seats'], ['count' => $usage['extra_seats']]) }}
                                    @else
                                        {{ __('ui.settings_page.stat_seats_sub') }}
                                    @endif
                                </div>
                            </div>
                            <div class="val">{{ $usage['seats_used'] }} / {{ $usage['seats_limit'] ?: '∞' }}</div>
                        </div>
                        @if($usage['seats_limit'] > 0)
                        <div class="bar"><i style="width:{{ $seatPct }}%;background:{{ $seatPct >= 100 ? '#dc2626' : ($seatPct >= 75 ? '#f59e0b' : '#0f7e7a') }}"></i></div>
                        @endif
                    </div>
                </div>

                <div class="wc-fld">
                    <div class="wc-meter">
                        <div class="hd">
                            <span class="ico" style="background:rgba(21,182,168,.13);color:#15b6a8"><i class="ri-sparkling-2-line"></i></span>
                            <div class="m">
                                <div class="n">{{ __('ui.settings_page.stat_ai') }}</div>
                                <div class="s">
                                    @if($usage['ai_credits'] > 0)
                                        {{ __('ui.tenant_billing.ai_credits_foot', ['n' => number_format($usage['ai_credits'])]) }}
                                    @else
                                        {{ __('ui.settings_page.stat_ai_sub') }}
                                    @endif
                                </div>
                            </div>
                            <div class="val">
                                {{ number_format($usage['ai_used']) }} / {{ $usage['ai_quota'] === null ? '∞' : number_format((int) $usage['ai_quota']) }}
                            </div>
                        </div>
                        @if($usage['ai_quota'])
                        <div class="bar"><i style="width:{{ $aiPct }}%;background:{{ $aiPct >= 90 ? '#dc2626' : ($aiPct >= 70 ? '#f59e0b' : '#15b6a8') }}"></i></div>
                        @endif
                    </div>
                </div>

                <div class="wc-grid2">
                    <div class="wc-fld">
                        <div class="wc-meter"><div class="hd">
                            <span class="ico" style="background:#f0fdf4;color:#25a35a"><i class="ri-whatsapp-line"></i></span>
                            <div class="m"><div class="n">{{ __('ui.settings_page.stat_instances') }}</div></div>
                            <div class="val">{{ $usage['instances'] }}</div>
                        </div></div>
                    </div>
                    <div class="wc-fld">
                        <div class="wc-meter"><div class="hd">
                            <span class="ico" style="background:#eff6ff;color:#2563eb"><i class="ri-bank-card-line"></i></span>
                            <div class="m">
                                <div class="n">{{ __('ui.settings_page.stat_last_payment') }}</div>
                                @if($lastPayment)
                                <div class="s">{{ $lastPayment->currency }} {{ number_format((float) $lastPayment->amount, 2) }}</div>
                                @endif
                            </div>
                            <div class="val" style="font-size:15px">{{ $lastPayment?->paid_at?->translatedFormat('j M Y') ?? '—' }}</div>
                        </div></div>
                    </div>
                </div>
            </section>

        </div></div>
    </div>
</form>

    {{-- ══ UNSAVED-CHANGES GUARD ═══════════════════════════════════
         Shown when a dirty form is about to be abandoned by an in-app
         navigation. A real unload (reload, tab close) cannot host a custom
         dialog, so beforeunload falls back to the browser's own prompt. --}}
    <div class="wc-ovl" :class="leaving ? 'on' : ''" @click.self="stay()">
        <div class="wc-modal sm" role="alertdialog" aria-modal="true" aria-labelledby="wcLeaveTitle">
            <div class="mb" style="text-align:center">
                <div style="width:46px;height:46px;border-radius:50%;background:var(--wc-amber-50,#fff7ed);color:var(--wc-amber,#d97706);display:grid;place-items:center;margin:0 auto 12px">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none"><path d="M12 3l9.5 17H2.5L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>
                </div>
                <div id="wcLeaveTitle" style="font-size:15px;font-weight:700;letter-spacing:-.02em">{{ __('ui.settings_page.leave_title') }}</div>
                <div style="font-size:13px;color:var(--wc-muted);margin-top:5px;line-height:1.6">{{ __('ui.settings_page.leave_body') }}</div>
            </div>
            <div class="mf" style="justify-content:center;flex-wrap:wrap">
                <button type="button" class="wc-btn g" @click="stay()">{{ __('ui.settings_page.leave_stay') }}</button>
                <button type="button" class="wc-btn g" @click="leaveAnyway()">{{ __('ui.settings_page.leave_discard') }}</button>
                <button type="button" class="wc-btn p" @click="saveAndLeave()" :disabled="saving">{{ __('ui.settings_page.leave_save') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
function workspaceSettings() {
    return {
        form:     @json($initial),
        // field name -> section, so a failed control can be un-collapsed
        sections: @js($fieldSection),
        baseline: '',
        section:  @js($openSection),
        saving:   false,
        dirty:    false,

        // Where the guarded click was headed, held while the modal is open.
        leaving:  false,
        pending:  null,

        init() {
            this.baseline = JSON.stringify(this.form);
            this.$watch('form', () => { this.dirty = JSON.stringify(this.form) !== this.baseline; }, { deep: true });

            // Reload, tab close and cross-document back: the browser owns that
            // dialog, all we can do is ask for it.
            window.addEventListener('beforeunload', e => {
                if (!this.dirty || this.saving) return;
                e.preventDefault();
                e.returnValue = '';
            });

            // In-app navigation, where a real modal is possible. Capture phase,
            // so a link that carries its own handler is still intercepted.
            document.addEventListener('click', e => this.onNavigate(e), true);

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && this.leaving) this.stay();
            });
        },

        /** Hold a navigation away from an edited form until the user chooses. */
        onNavigate(e) {
            if (!this.dirty || this.saving || this.leaving) return;
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            const link = e.target.closest('a[href]');
            if (!link || link.hasAttribute('download')) return;
            if (link.target && link.target !== '_self') return;

            const href = link.getAttribute('href') || '';
            if (!href || href.startsWith('#') || /^(javascript|mailto|tel):/i.test(href)) return;
            if (link.href === window.location.href) return;

            e.preventDefault();
            e.stopPropagation();
            this.pending = link.href;
            this.leaving = true;
        },

        stay() {
            this.leaving = false;
            this.pending = null;
        },

        leaveAnyway() {
            const to = this.pending;
            // Cleared first: beforeunload must not fire on the way out.
            this.dirty   = false;
            this.leaving = false;
            this.pending = null;
            if (to) window.location.href = to;
        },

        /** Save, then let the redirect land; the pending link is dropped. */
        saveAndLeave() {
            this.leaving = false;
            this.pending = null;
            this.revealInvalid();
            this.$nextTick(() => {
                this.$refs.form.requestSubmit
                    ? this.$refs.form.requestSubmit()
                    : (this.onSubmit(), this.$refs.form.submit());
            });
        },

        /**
         * A collapsed section is display:none, and the browser refuses to
         * report a validation error on a control it cannot focus. Open the
         * section holding the first invalid field before submitting.
         */
        revealInvalid() {
            const bad = this.$refs.form.querySelector(':invalid');
            const sec = bad && this.sections[(bad.name || '').split('[')[0]];
            if (sec) this.go(sec);
        },

        go(s) {
            this.section = s;
            if (this.$refs.formScroll) this.$refs.formScroll.scrollTop = 0;
        },

        domainList() {
            return this.form.allowed_domains
                .split(/[\s,]+/)
                .map(d => d.trim().replace(/^@/, '').toLowerCase())
                .filter(d => d && d.includes('.'));
        },

        discard() {
            this.form = JSON.parse(this.baseline);
            this.dirty = false;
        },

        onSubmit() {
            this.saving = true;
            this.dirty  = false;
        },
    };
}
</script>
@endsection

@push('styles')
    {{-- Shared Wavadesk console shell — see /ai-settings and /webchat/settings. --}}
    <link rel="stylesheet" href="{{ asset('css/wavadesk-console.css') }}?v={{ filemtime(public_path('css/wavadesk-console.css')) }}">
    <style>
        /* Collapse the preview column for consoles that have no preview pane. */
        .wv-console .wc-body.no-prev { grid-template-columns: var(--wc-nav) minmax(0, 1fr); }

        /* Business-hours rows: the one shape the shared console has no class for. */
        .wv-console .st-day { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--wc-border, #e6ebf0); border-radius: 11px; }
        .wv-console .st-day + .st-day { margin-top: 8px; }
        .wv-console .st-day.off { background: var(--wc-soft, #f7f9fa); }
        .wv-console .st-day .d { font-size: 13.5px; font-weight: 600; width: 92px; flex-shrink: 0; }
        .wv-console .st-day .t { display: flex; align-items: center; gap: 8px; margin-inline-start: auto; }
        .wv-console .st-day .t .sep { color: var(--wc-muted, #64748b); }
        .wv-console .st-day .cl { margin-inline-start: auto; font-size: 12.5px; color: var(--wc-muted, #64748b); font-weight: 500; }
        .wv-console .st-day input[type="time"] { width: 108px; }
        @media (max-width: 560px) {
            .wv-console .st-day { flex-wrap: wrap; }
            .wv-console .st-day .t, .wv-console .st-day .cl { margin-inline-start: 0; flex-basis: 100%; }
        }
    </style>
@endpush
