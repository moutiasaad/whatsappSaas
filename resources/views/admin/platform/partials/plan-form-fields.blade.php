@php
    $plan = $plan ?? null;
    $activeValue = old('is_active', $plan?->is_active ?? true);

    $moduleCatalogue  = config('plan_modules', []);
    $landingCatalogue = config('plan_landing_attributes', []);

    // A brand-new plan starts with everything ticked — an operator adding a
    // plan almost always means "the full product", and un-ticking is easier to
    // notice than a silently missing module.
    $selectedModules = old('modules', $plan?->modules ?? array_keys($moduleCatalogue));
    $selectedModules = is_array($selectedModules) ? $selectedModules : [];

    // NULL landing_features means "never curated". Offer nothing pre-ticked so
    // the choice is explicit, which is the point of the manual picker.
    $selectedLanding = old('landing_features', $plan?->landing_features ?? []);
    $selectedLanding = is_array($selectedLanding) ? $selectedLanding : [];

    $trialEnabledValue = old('trial_enabled', $plan?->trial_enabled ?? false);
    $trialIsOn = (string) $trialEnabledValue === '1' || $trialEnabledValue === 1 || $trialEnabledValue === true;

    // AI messages. The column keeps its three-state convention — NULL is
    // unlimited, 0 is "no AI on this plan", a positive number is the monthly
    // cap — but the operator only picks between unlimited and a number here:
    // whether AI runs at all is the `ai_agent` module tick, and the controller
    // writes 0 on its own when that module is off.
    $storedQuota  = $plan?->ai_message_quota;
    $aiIsLimited  = old('ai_messages_mode', ($storedQuota > 0) ? 'limited' : 'unlimited') === 'limited';
    $aiLimitValue = old('ai_message_limit', $storedQuota > 0 ? $storedQuota : null);

    $checked = fn ($value, array $list) => in_array($value, $list, true);
@endphp

<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="name">{{ __('ui.plan_form_fields.name') }}</label>
        <input id="name" type="text" name="name" value="{{ old('name', $plan?->name) }}" class="form-control @error('name') error @enderror">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="price_monthly">{{ __('ui.plan_form_fields.monthly_price') }}</label>
        <input id="price_monthly" type="number" min="0" step="0.01" name="price_monthly" value="{{ old('price_monthly', $plan?->price_monthly) }}" class="form-control @error('price_monthly') error @enderror">
        @error('price_monthly') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="price_annual">{{ __('ui.plan_form_fields.annual_price') }}</label>
        <input id="price_annual" type="number" min="0" step="0.01" name="price_annual" value="{{ old('price_annual', $plan?->price_annual) }}" class="form-control @error('price_annual') error @enderror">
        @error('price_annual') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    {{-- PayPal NCP button URL — one per plan, created manually in the
         PayPal merchant dashboard. When set, the checkout button links
         straight to this URL (paypal.com/ncp/payment/XXX) instead of
         going through the Orders API. Full-row because the value is
         long. Leave blank to fall back to the dynamic Orders API flow. --}}
    <div class="form-group" style="grid-column:1 / -1;">
        <label class="form-label" for="paypal_ncp_link">{{ __('ui.plan_form_fields.paypal_ncp_link') }}</label>
        <input id="paypal_ncp_link" type="url" name="paypal_ncp_link"
               value="{{ old('paypal_ncp_link', $plan?->paypal_ncp_link) }}"
               placeholder="https://www.paypal.com/ncp/payment/XXXXXXXX"
               class="form-control @error('paypal_ncp_link') error @enderror">
        <small class="form-help">{{ __('ui.plan_form_fields.paypal_ncp_link_hint') }}</small>
        @error('paypal_ncp_link') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_users">{{ __('ui.plan_form_fields.max_users') }}</label>
        <input id="max_users" type="number" min="1" step="1" name="max_users" value="{{ old('max_users', $plan?->max_users) }}" class="form-control @error('max_users') error @enderror">
        @error('max_users') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_instances">{{ __('ui.plan_form_fields.max_instances') }}</label>
        <input id="max_instances" type="number" min="0" step="1" name="max_instances" value="{{ old('max_instances', $plan?->max_instances) }}" class="form-control @error('max_instances') error @enderror">
        <small class="form-help">{{ __('ui.plan_form_fields.max_instances_hint') }}</small>
        @error('max_instances') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_conversations_per_month">{{ __('ui.plan_form_fields.max_conversations_per_month') }}</label>
        <input id="max_conversations_per_month" type="number" min="0" step="1" name="max_conversations_per_month" value="{{ old('max_conversations_per_month', $plan?->max_conversations_per_month) }}" class="form-control @error('max_conversations_per_month') error @enderror">
        @error('max_conversations_per_month') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

{{-- ══ FREE TRIAL ══════════════════════════════════════════════════════ --}}
<div class="plan-fieldset">
    <div class="plan-fieldset-head">
        <div class="plan-fieldset-title">{{ __('ui.plan_form_fields.trial_title') }}</div>
        <div class="plan-fieldset-sub">{{ __('ui.plan_form_fields.trial_subtitle') }}</div>
    </div>

    <div class="form-group">
        <input type="hidden" name="trial_enabled" value="0">
        <label class="toggle-label">
            <input type="checkbox" name="trial_enabled" value="1" id="trial_enabled" @checked($trialIsOn)>
            <span class="toggle-text">{{ __('ui.plan_form_fields.trial_enabled') }}</span>
        </label>
        <span class="form-hint" style="display:block;margin-top:.25rem;">{{ __('ui.plan_form_fields.trial_enabled_hint') }}</span>
    </div>

    <div class="form-group" style="max-width:260px;margin-top:12px;">
        <label class="form-label" for="trial_days">{{ __('ui.plan_form_fields.trial_days') }}</label>
        <input id="trial_days" type="number" min="1" max="365" step="1" name="trial_days"
               value="{{ old('trial_days', $plan?->trial_days) }}"
               placeholder="{{ config('app.trial_days', 7) }}"
               class="form-control @error('trial_days') error @enderror">
        <small class="form-help">{{ __('ui.plan_form_fields.trial_days_hint', ['default' => config('app.trial_days', 7)]) }}</small>
        @error('trial_days') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

{{-- ══ MODULES ═════════════════════════════════════════════════════════ --}}
<div class="plan-fieldset">
    <div class="plan-fieldset-head">
        <div class="plan-fieldset-title">{{ __('ui.plan_form_fields.modules_title') }}</div>
        <div class="plan-fieldset-sub">{{ __('ui.plan_form_fields.modules_subtitle') }}</div>
    </div>

    <div class="plan-checkgrid">
        @foreach ($moduleCatalogue as $key => $meta)
            @php $always = (bool) ($meta['always'] ?? false); @endphp
            <label class="plan-check {{ $always ? 'is-locked' : '' }}">
                {{-- An `always` module is rendered ticked and disabled; a disabled
                     input posts nothing, so a hidden field carries its value. --}}
                @if ($always)
                    <input type="hidden" name="modules[]" value="{{ $key }}">
                    <input type="checkbox" checked disabled>
                @else
                    <input type="checkbox" name="modules[]" value="{{ $key }}" @checked($checked($key, $selectedModules))>
                @endif
                <span class="plan-check-body">
                    <span class="plan-check-name"><i class="{{ $meta['icon'] ?? 'ri-checkbox-circle-line' }}"></i>{{ __('ui.plan_modules.' . $key) }}</span>
                    <span class="plan-check-desc">{{ __('ui.plan_modules_desc.' . $key) }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('modules') <div class="form-error">{{ $message }}</div> @enderror
    @error('modules.*') <div class="form-error">{{ $message }}</div> @enderror
</div>

{{-- ══ AI MESSAGES ═════════════════════════════════════════════════════ --}}
<div class="plan-fieldset" id="ai-messages-fieldset">
    <div class="plan-fieldset-head">
        <div class="plan-fieldset-title">{{ __('ui.plan_form_fields.ai_messages_title') }}</div>
        <div class="plan-fieldset-sub">{{ __('ui.plan_form_fields.ai_messages_subtitle') }}</div>
    </div>

    <div class="plan-radiorow">
        <label class="plan-check">
            <input type="radio" name="ai_messages_mode" value="unlimited" id="ai_messages_mode_unlimited" @checked(!$aiIsLimited)>
            <span class="plan-check-body">
                <span class="plan-check-name"><i class="ri-infinity-line"></i>{{ __('ui.plan_form_fields.ai_messages_unlimited') }}</span>
                <span class="plan-check-desc">{{ __('ui.plan_form_fields.ai_messages_unlimited_hint') }}</span>
            </span>
        </label>

        <label class="plan-check">
            <input type="radio" name="ai_messages_mode" value="limited" id="ai_messages_mode_limited" @checked($aiIsLimited)>
            <span class="plan-check-body">
                <span class="plan-check-name"><i class="ri-speed-up-line"></i>{{ __('ui.plan_form_fields.ai_messages_limited') }}</span>
                <span class="plan-check-desc">{{ __('ui.plan_form_fields.ai_messages_limited_hint') }}</span>
            </span>
        </label>
    </div>

    <div class="form-group" id="ai-message-limit-group" style="max-width:280px;margin-top:12px;" @if(!$aiIsLimited) hidden @endif>
        <label class="form-label" for="ai_message_limit">{{ __('ui.plan_form_fields.ai_message_limit') }}</label>
        <input id="ai_message_limit" type="number" min="1" step="1" name="ai_message_limit"
               value="{{ $aiLimitValue }}"
               placeholder="{{ __('ui.plan_form_fields.ai_message_limit_placeholder') }}"
               class="form-control @error('ai_message_limit') error @enderror">
        <small class="form-help">{{ __('ui.plan_form_fields.ai_message_limit_hint') }}</small>
    </div>

    @error('ai_messages_mode') <div class="form-error">{{ $message }}</div> @enderror
    @error('ai_message_limit') <div class="form-error">{{ $message }}</div> @enderror

    <div class="plan-note" id="ai-messages-off-note" hidden>
        <i class="ri-information-line"></i>{{ __('ui.plan_form_fields.ai_messages_module_off') }}
    </div>
</div>

{{-- ══ LANDING PAGE ATTRIBUTES ═════════════════════════════════════════ --}}
<div class="plan-fieldset">
    <div class="plan-fieldset-head">
        <div class="plan-fieldset-title">{{ __('ui.plan_form_fields.landing_title') }}</div>
        <div class="plan-fieldset-sub">{{ __('ui.plan_form_fields.landing_subtitle') }}</div>
    </div>

    <div class="plan-checkgrid">
        @foreach ($landingCatalogue as $key => $meta)
            <label class="plan-check">
                <input type="checkbox" name="landing_features[]" value="{{ $key }}" @checked($checked($key, $selectedLanding))>
                <span class="plan-check-body">
                    <span class="plan-check-name"><i class="{{ $meta['icon'] ?? 'ri-checkbox-circle-line' }}"></i>{{ __('ui.plan_landing_attributes.' . $key) }}</span>
                </span>
            </label>
        @endforeach

        {{-- Module lines. Ticking one here only advertises it; the plan must
             also grant the module or the line is dropped when rendering. --}}
        @foreach ($moduleCatalogue as $key => $meta)
            <label class="plan-check">
                <input type="checkbox" name="landing_features[]" value="module:{{ $key }}" @checked($checked('module:' . $key, $selectedLanding))>
                <span class="plan-check-body">
                    <span class="plan-check-name"><i class="{{ $meta['icon'] ?? 'ri-checkbox-circle-line' }}"></i>{{ __('ui.plan_modules.' . $key) }}</span>
                </span>
            </label>
        @endforeach
    </div>
    <span class="form-hint" style="display:block;margin-top:.5rem;">{{ __('ui.plan_form_fields.landing_hint') }}</span>
    @error('landing_features') <div class="form-error">{{ $message }}</div> @enderror
    @error('landing_features.*') <div class="form-error">{{ $message }}</div> @enderror
</div>

<div class="form-group" style="margin-top:16px;">
    <input type="hidden" name="is_active" value="0">
    <label class="toggle-label">
        <input type="checkbox" name="is_active" value="1" @checked((string) $activeValue === '1' || $activeValue === 1 || $activeValue === true)>
        <span class="toggle-text">{{ __('ui.plan_form_fields.is_active') }}</span>
    </label>
</div>

@push('styles')
<style>
    .plan-fieldset {
        margin-top: 20px; padding: 16px;
        border: 1px solid var(--card-border); border-radius: var(--radius-lg);
        background: var(--page-bg);
    }
    .plan-fieldset-head  { margin-bottom: 12px; }
    .plan-fieldset-title { font-size: .9375rem; font-weight: 600; color: var(--text-primary); }
    .plan-fieldset-sub   { font-size: .8125rem; color: var(--text-muted); margin-top: .125rem; line-height: 1.45; }

    .plan-checkgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    @media (max-width: 1100px) { .plan-checkgrid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px)  { .plan-checkgrid { grid-template-columns: 1fr; } }

    .plan-check {
        display: flex; align-items: flex-start; gap: .625rem;
        padding: .625rem .75rem; cursor: pointer;
        border: 1px solid var(--card-border); border-radius: var(--radius);
        background: var(--card-bg); transition: var(--transition);
    }
    .plan-check:hover { border-color: var(--brand-light, var(--brand)); }
    .plan-check input[type=checkbox] { accent-color: var(--brand); width: 16px; height: 16px; margin-top: .125rem; flex-shrink: 0; }
    .plan-check.is-locked { opacity: .72; cursor: default; }
    .plan-check-body { display: flex; flex-direction: column; gap: .125rem; min-width: 0; }
    .plan-check-name {
        display: flex; align-items: center; gap: .375rem;
        font-size: .8125rem; font-weight: 600; color: var(--text-primary);
    }
    .plan-check-name i { color: var(--brand); font-size: .9375rem; }
    .plan-check-desc { font-size: .75rem; color: var(--text-muted); line-height: 1.4; }

    .plan-check input[type=radio] { accent-color: var(--brand); width: 16px; height: 16px; margin-top: .125rem; flex-shrink: 0; }
    .plan-radiorow { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
    @media (max-width: 640px) { .plan-radiorow { grid-template-columns: 1fr; } }

    /* Whole block greys out while the AI agent module is unticked — the plan
       grants no AI at all then, so a message allowance means nothing. */
    #ai-messages-fieldset.is-off .plan-radiorow,
    #ai-messages-fieldset.is-off #ai-message-limit-group { opacity: .5; pointer-events: none; }

    .plan-note {
        display: flex; align-items: flex-start; gap: .4rem;
        margin-top: 12px; padding: .5rem .625rem;
        font-size: .75rem; line-height: 1.45; color: var(--text-muted);
        border: 1px solid var(--card-border); border-radius: var(--radius);
        background: var(--card-bg);
    }
    .plan-note i { color: var(--brand); font-size: .875rem; flex-shrink: 0; }

    /* Both blocks below are display:flex, which outranks the UA rule the
       `hidden` attribute relies on — without this they stay visible. */
    #ai-message-limit-group[hidden], #ai-messages-off-note[hidden] { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var fieldset = document.getElementById('ai-messages-fieldset');
    var limited  = document.getElementById('ai_messages_mode_limited');
    var group    = document.getElementById('ai-message-limit-group');
    var note     = document.getElementById('ai-messages-off-note');
    var aiModule = document.querySelector('input[name="modules[]"][value="ai_agent"]');
    if (!fieldset) return;

    function syncLimitBox() { if (group && limited) group.hidden = !limited.checked; }

    function syncModule() {
        var on = !aiModule || aiModule.checked;
        fieldset.classList.toggle('is-off', !on);
        if (note) note.hidden = on;
    }

    document.querySelectorAll('input[name="ai_messages_mode"]')
        .forEach(function (r) { r.addEventListener('change', syncLimitBox); });
    if (aiModule) aiModule.addEventListener('change', syncModule);

    syncLimitBox();
    syncModule();
})();
</script>
@endpush
