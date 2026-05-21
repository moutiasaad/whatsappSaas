<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="app_name">{{ __('ui.platform_global_settings_page.application_name') }}</label>
        <input id="app_name" type="text" name="app_name" value="{{ old('app_name', $settings['app_name']['value']) }}" class="form-control @error('app_name') error @enderror">
        @error('app_name') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="app_url">{{ __('ui.platform_global_settings_page.application_url') }}</label>
        <input id="app_url" type="url" name="app_url" value="{{ old('app_url', $settings['app_url']['value']) }}" class="form-control @error('app_url') error @enderror">
        @error('app_url') <div class="form-error">{{ $message }}</div> @enderror
    </div>

    <div class="form-group full">
        <label class="form-label" for="support_email">{{ __('ui.platform_global_settings_page.support_email') }}</label>
        <input id="support_email" type="email" name="support_email" value="{{ old('support_email', $settings['support_email']['value']) }}" class="form-control @error('support_email') error @enderror">
        @error('support_email') <div class="form-error">{{ $message }}</div> @enderror
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:14px;margin-top:16px;">
    <div class="form-group">
        <input type="hidden" name="platform_signups_enabled" value="0">
        <label class="toggle-label">
            <input type="checkbox" name="platform_signups_enabled" value="1" @checked(old('platform_signups_enabled', $settings['platform_signups_enabled']['value']))>
            <span class="toggle-text">{{ __('ui.platform_global_settings_page.new_tenant_signups') }}</span>
        </label>
    </div>

    <div class="form-group">
        <input type="hidden" name="billing_features_enabled" value="0">
        <label class="toggle-label">
            <input type="checkbox" name="billing_features_enabled" value="1" @checked(old('billing_features_enabled', $settings['billing_features_enabled']['value']))>
            <span class="toggle-text">{{ __('ui.platform_global_settings_page.billing_features') }}</span>
        </label>
    </div>

    <div class="form-group">
        <input type="hidden" name="maintenance_mode_enabled" value="0">
        <label class="toggle-label">
            <input type="checkbox" name="maintenance_mode_enabled" value="1" @checked(old('maintenance_mode_enabled', $settings['maintenance_mode_enabled']['value']))>
            <span class="toggle-text">{{ __('ui.platform_global_settings_page.maintenance_banner') }}</span>
        </label>
    </div>
</div>
