@php
    $plan = $plan ?? null;
    $activeValue = old('is_active', $plan?->is_active ?? true);
    $aiIncludedValue = old('ai_included', $plan?->ai_included ?? false);
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

    <div class="form-group">
        <label class="form-label" for="max_users">{{ __('ui.plan_form_fields.max_users') }}</label>
        <input id="max_users" type="number" min="1" step="1" name="max_users" value="{{ old('max_users', $plan?->max_users) }}" class="form-control @error('max_users') error @enderror">
        @error('max_users') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_instances">{{ __('ui.plan_form_fields.max_instances') }}</label>
        <input id="max_instances" type="number" min="0" step="1" name="max_instances" value="{{ old('max_instances', $plan?->max_instances) }}" class="form-control @error('max_instances') error @enderror">
        @error('max_instances') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_conversations_per_month">{{ __('ui.plan_form_fields.max_conversations_per_month') }}</label>
        <input id="max_conversations_per_month" type="number" min="0" step="1" name="max_conversations_per_month" value="{{ old('max_conversations_per_month', $plan?->max_conversations_per_month) }}" class="form-control @error('max_conversations_per_month') error @enderror">
        @error('max_conversations_per_month') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="stripe_price_id_monthly">{{ __('ui.plan_form_fields.stripe_monthly_price_id') }}</label>
        <input id="stripe_price_id_monthly" type="text" name="stripe_price_id_monthly" value="{{ old('stripe_price_id_monthly', $plan?->stripe_price_id_monthly) }}" class="form-control @error('stripe_price_id_monthly') error @enderror" placeholder="{{ __('ui.plan_form_fields.stripe_placeholder') }}">
        @error('stripe_price_id_monthly') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="stripe_price_id_annual">{{ __('ui.plan_form_fields.stripe_annual_price_id') }}</label>
        <input id="stripe_price_id_annual" type="text" name="stripe_price_id_annual" value="{{ old('stripe_price_id_annual', $plan?->stripe_price_id_annual) }}" class="form-control @error('stripe_price_id_annual') error @enderror" placeholder="{{ __('ui.plan_form_fields.stripe_placeholder') }}">
        @error('stripe_price_id_annual') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group" style="margin-top:16px;">
    <label class="form-label" for="features">{{ __('ui.plan_form_fields.features') }}</label>
    <textarea id="features" name="features" class="form-control @error('features') error @enderror" rows="6" placeholder='{{ __('ui.plan_form_fields.features_placeholder') }}'>{{ old('features', $plan?->features ? json_encode($plan->features, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    @error('features') <div class="form-error">{{ $message }}</div> @enderror
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
    <div class="form-group">
        <input type="hidden" name="ai_included" value="0">
        <label class="toggle-label">
            <input type="checkbox" name="ai_included" value="1" @checked((string) $aiIncludedValue === '1' || $aiIncludedValue === 1 || $aiIncludedValue === true)>
            <span class="toggle-text">{{ __('ui.plan_form_fields.ai_included') }}</span>
        </label>
    </div>
    <div class="form-group">
        <label class="form-label" for="ai_token_quota">{{ __('ui.plan_form_fields.ai_token_quota') }}</label>
        <input id="ai_token_quota" type="number" min="0" step="1" name="ai_token_quota" value="{{ old('ai_token_quota', $plan?->ai_token_quota ?? 0) }}" class="form-control @error('ai_token_quota') error @enderror">
        @error('ai_token_quota') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group" style="margin-top:16px;">
    <input type="hidden" name="is_active" value="0">
    <label class="toggle-label">
        <input type="checkbox" name="is_active" value="1" @checked((string) $activeValue === '1' || $activeValue === 1 || $activeValue === true)>
        <span class="toggle-text">{{ __('ui.plan_form_fields.is_active') }}</span>
    </label>
</div>
