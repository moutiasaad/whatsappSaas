@php
    $tenant = $tenant ?? null;
    $tenantAdmin = $tenantAdmin ?? null;
    $activeValue = old('is_active', $tenant?->is_active ?? true);
    $adminNameValue = old('admin_name', $tenantAdmin?->name);
    $adminEmailValue = old('admin_email', $tenantAdmin?->email);
@endphp

<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="name">{{ __('ui.tenant_form_fields.name') }}</label>
        <input id="name" type="text" name="name" value="{{ old('name', $tenant?->name) }}" class="form-control @error('name') error @enderror" placeholder="{{ __('ui.tenant_form_fields.name_placeholder') }}">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="slug">{{ __('ui.tenant_form_fields.slug') }}</label>
        <input id="slug" type="text" name="slug" value="{{ old('slug', $tenant?->slug) }}" class="form-control @error('slug') error @enderror" placeholder="{{ __('ui.tenant_form_fields.slug_placeholder') }}">
        @error('slug') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="subscription_status">{{ __('ui.tenant_form_fields.subscription_status') }}</label>
        <select id="subscription_status" name="subscription_status" class="form-control @error('subscription_status') error @enderror">
            @foreach(['active','suspended','cancelled'] as $status)
                <option value="{{ $status }}" @selected(old('subscription_status', $tenant?->subscription_status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('subscription_status') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="plan_id">{{ __('ui.tenant_form_fields.plan') }}</label>
        <select id="plan_id" name="plan_id" class="form-control @error('plan_id') error @enderror">
            <option value="">{{ __('ui.tenant_form_fields.no_plan') }}</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected((string) old('plan_id', $tenant?->plan_id) === (string) $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
        @error('plan_id') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="subscription_starts_at">{{ __('ui.tenant_form_fields.subscription_starts_at') }}</label>
        <input id="subscription_starts_at" type="date" name="subscription_starts_at"
               value="{{ old('subscription_starts_at', $tenant?->subscription_starts_at?->format('Y-m-d')) }}"
               class="form-control @error('subscription_starts_at') error @enderror">
        @error('subscription_starts_at') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="subscription_ends_at">{{ __('ui.tenant_form_fields.subscription_ends_at') }}</label>
        <input id="subscription_ends_at" type="date" name="subscription_ends_at"
               value="{{ old('subscription_ends_at', $tenant?->subscription_ends_at?->format('Y-m-d')) }}"
               class="form-control @error('subscription_ends_at') error @enderror">
        @error('subscription_ends_at') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="stripe_id">{{ __('ui.tenant_form_fields.stripe_customer_id') }}</label>
        <input id="stripe_id" type="text" name="stripe_id" value="{{ old('stripe_id', $tenant?->stripe_id) }}" class="form-control @error('stripe_id') error @enderror" placeholder="{{ __('ui.tenant_form_fields.stripe_placeholder') }}">
        @error('stripe_id') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group" style="margin-top:16px;">
    <label class="form-label" for="settings">{{ __('ui.tenant_form_fields.settings') }}</label>
    <textarea id="settings" name="settings" class="form-control @error('settings') error @enderror" rows="6" placeholder='{{ __('ui.tenant_form_fields.settings_placeholder') }}'>{{ old('settings', $tenant?->settings ? json_encode($tenant->settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    @error('settings') <div class="form-error">{{ $message }}</div> @enderror
</div>

<div class="form-group" style="margin-top:16px;">
    <input type="hidden" name="is_active" value="0">
    <label class="toggle-label">
        <input type="checkbox" name="is_active" value="1" @checked((string) $activeValue === '1' || $activeValue === 1 || $activeValue === true)>
        <span class="toggle-text">{{ __('ui.tenant_form_fields.active_tenant') }}</span>
    </label>
</div>

<div class="card" style="margin-top:20px; overflow:visible;">
    <div class="card-header">
        <div>
            <div class="card-title">{{ __('ui.tenant_form_fields.initial_tenant_admin') }}</div>
            <div class="card-subtitle">{{ $tenant ? __('ui.tenant_form_fields.update_admin_credentials') : __('ui.tenant_form_fields.create_first_admin') }}</div>
        </div>
    </div>

    <div style="padding:20px; display:grid; gap:16px;">
        <div class="form-group">
            <label class="form-label" for="admin_name">{{ __('ui.tenant_form_fields.admin_name') }}</label>
            <input id="admin_name" type="text" name="admin_name" value="{{ $adminNameValue }}" class="form-control @error('admin_name') error @enderror" placeholder="{{ __('ui.tenant_form_fields.admin_name_placeholder') }}">
            @error('admin_name') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="admin_email">{{ __('ui.tenant_form_fields.admin_email') }}</label>
            <input id="admin_email" type="email" name="admin_email" value="{{ $adminEmailValue }}" class="form-control @error('admin_email') error @enderror" placeholder="{{ __('ui.tenant_form_fields.admin_email_placeholder') }}">
            @error('admin_email') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="admin_password">{{ __('ui.tenant_form_fields.admin_password') }}</label>
            <input id="admin_password" type="password" name="admin_password" class="form-control @error('admin_password') error @enderror" placeholder="{{ __('ui.tenant_form_fields.admin_password_placeholder') }}">
            @error('admin_password') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="admin_password_confirmation">{{ __('ui.tenant_form_fields.confirm_password') }}</label>
            <input id="admin_password_confirmation" type="password" name="admin_password_confirmation" class="form-control" placeholder="{{ __('ui.tenant_form_fields.confirm_password_placeholder') }}">
        </div>
    </div>
</div>
