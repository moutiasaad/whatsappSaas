@php
    $plan = $plan ?? null;
    $activeValue = old('is_active', $plan?->is_active ?? true);
    $aiIncludedValue = old('ai_included', $plan?->ai_included ?? false);
@endphp

<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="name">Plan Name <span class="req">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name', $plan?->name) }}" class="form-control @error('name') error @enderror" required>
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="price_monthly">Monthly Price <span class="req">*</span></label>
        <input id="price_monthly" type="number" min="0" step="0.01" name="price_monthly" value="{{ old('price_monthly', $plan?->price_monthly) }}" class="form-control @error('price_monthly') error @enderror" required>
        @error('price_monthly') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="price_annual">Annual Price <span class="req">*</span></label>
        <input id="price_annual" type="number" min="0" step="0.01" name="price_annual" value="{{ old('price_annual', $plan?->price_annual) }}" class="form-control @error('price_annual') error @enderror" required>
        @error('price_annual') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_users">Max Users <span class="req">*</span></label>
        <input id="max_users" type="number" min="1" step="1" name="max_users" value="{{ old('max_users', $plan?->max_users) }}" class="form-control @error('max_users') error @enderror" required>
        @error('max_users') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_instances">Max Instances <span class="req">*</span></label>
        <input id="max_instances" type="number" min="0" step="1" name="max_instances" value="{{ old('max_instances', $plan?->max_instances) }}" class="form-control @error('max_instances') error @enderror" required>
        @error('max_instances') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="max_conversations_per_month">Max Conversations / Month <span class="req">*</span></label>
        <input id="max_conversations_per_month" type="number" min="0" step="1" name="max_conversations_per_month" value="{{ old('max_conversations_per_month', $plan?->max_conversations_per_month) }}" class="form-control @error('max_conversations_per_month') error @enderror" required>
        @error('max_conversations_per_month') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="stripe_price_id_monthly">Stripe Monthly Price ID</label>
        <input id="stripe_price_id_monthly" type="text" name="stripe_price_id_monthly" value="{{ old('stripe_price_id_monthly', $plan?->stripe_price_id_monthly) }}" class="form-control @error('stripe_price_id_monthly') error @enderror" placeholder="price_...">
        @error('stripe_price_id_monthly') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="stripe_price_id_annual">Stripe Annual Price ID</label>
        <input id="stripe_price_id_annual" type="text" name="stripe_price_id_annual" value="{{ old('stripe_price_id_annual', $plan?->stripe_price_id_annual) }}" class="form-control @error('stripe_price_id_annual') error @enderror" placeholder="price_...">
        @error('stripe_price_id_annual') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group" style="margin-top:16px;">
    <label class="form-label" for="features">Features (JSON array/object)</label>
    <textarea id="features" name="features" class="form-control @error('features') error @enderror" rows="6" placeholder='["Priority support","Unlimited teams"]'>{{ old('features', $plan?->features ? json_encode($plan->features, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    @error('features') <div class="form-error">{{ $message }}</div> @enderror
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
    <div class="form-group">
        <input type="hidden" name="ai_included" value="0">
        <label class="toggle-label">
            <input type="checkbox" name="ai_included" value="1" @checked((string) $aiIncludedValue === '1' || $aiIncludedValue === 1 || $aiIncludedValue === true)>
            <span class="toggle-text">AI included in plan</span>
        </label>
    </div>
    <div class="form-group">
        <label class="form-label" for="ai_token_quota">AI Token Quota</label>
        <input id="ai_token_quota" type="number" min="0" step="1" name="ai_token_quota" value="{{ old('ai_token_quota', $plan?->ai_token_quota ?? 0) }}" class="form-control @error('ai_token_quota') error @enderror">
        @error('ai_token_quota') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group" style="margin-top:16px;">
    <input type="hidden" name="is_active" value="0">
    <label class="toggle-label">
        <input type="checkbox" name="is_active" value="1" @checked((string) $activeValue === '1' || $activeValue === 1 || $activeValue === true)>
        <span class="toggle-text">Plan is active</span>
    </label>
</div>
