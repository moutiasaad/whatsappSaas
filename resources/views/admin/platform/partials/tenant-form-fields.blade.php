@php
    $tenant = $tenant ?? null;
    $activeValue = old('is_active', $tenant?->is_active ?? true);
@endphp

<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="name">Name <span class="req">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name', $tenant?->name) }}" class="form-control @error('name') error @enderror" required placeholder="e.g. Demo Company">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="slug">Slug</label>
        <input id="slug" type="text" name="slug" value="{{ old('slug', $tenant?->slug) }}" class="form-control @error('slug') error @enderror" placeholder="Auto-generated from name if empty">
        @error('slug') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="subscription_status">Subscription Status <span class="req">*</span></label>
        <select id="subscription_status" name="subscription_status" class="form-control @error('subscription_status') error @enderror" required>
            @foreach(['trial','active','suspended','cancelled'] as $status)
                <option value="{{ $status }}" @selected(old('subscription_status', $tenant?->subscription_status ?? 'trial') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('subscription_status') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="plan_id">Plan</label>
        <select id="plan_id" name="plan_id" class="form-control @error('plan_id') error @enderror">
            <option value="">No plan</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected((string) old('plan_id', $tenant?->plan_id) === (string) $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
        @error('plan_id') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="trial_ends_at">Trial Ends At</label>
        <input id="trial_ends_at" type="date" name="trial_ends_at" value="{{ old('trial_ends_at', $tenant?->trial_ends_at?->format('Y-m-d')) }}" class="form-control @error('trial_ends_at') error @enderror">
        @error('trial_ends_at') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="stripe_id">Stripe Customer ID</label>
        <input id="stripe_id" type="text" name="stripe_id" value="{{ old('stripe_id', $tenant?->stripe_id) }}" class="form-control @error('stripe_id') error @enderror" placeholder="cus_xxx">
        @error('stripe_id') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group" style="margin-top:16px;">
    <label class="form-label" for="settings">Settings (JSON)</label>
    <textarea id="settings" name="settings" class="form-control @error('settings') error @enderror" rows="6" placeholder='{"timezone":"UTC","locale":"en"}'>{{ old('settings', $tenant?->settings ? json_encode($tenant->settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    @error('settings') <div class="form-error">{{ $message }}</div> @enderror
</div>

<div class="form-group" style="margin-top:16px;">
    <input type="hidden" name="is_active" value="0">
    <label class="toggle-label">
        <input type="checkbox" name="is_active" value="1" @checked((string) $activeValue === '1' || $activeValue === 1 || $activeValue === true)>
        <span class="toggle-text">Active tenant</span>
    </label>
</div>
