@extends('layouts.admin')

@section('title', __('ui.profile_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.profile_page.title') }}</span>
@endsection

@section('content')
<div x-data="profilePage()" x-cloak>

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.profile_page.title') }}</div>
            <div class="page-subtitle">{{ __('ui.profile_page.subtitle') }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;max-width:900px;">

        {{-- ── Change Password ─────────────────────────────────────────── --}}
        <div class="card" id="password">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.profile_page.change_password') }}</div>
                    <div class="card-subtitle">{{ __('ui.profile_page.change_password_hint') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0;">
                    <i class="ri-lock-password-line"></i>
                </div>
            </div>

            @if(session('password_success'))
            <div style="margin:0 1.5rem;padding:.75rem 1rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:.625rem;font-size:.8125rem;color:#059669;display:flex;align-items:center;gap:.5rem;">
                <i class="ri-checkbox-circle-line"></i> {{ session('password_success') }}
            </div>
            @endif

            <form action="{{ route(auth()->user()->routeNamePrefix() . '.profile.password') }}" method="POST" data-loading style="padding:0 1.5rem 1.5rem;">
                @csrf @method('PUT')
                <div style="display:flex;flex-direction:column;gap:1rem;margin-top:1rem;">

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.profile_page.current_password') }}</label>
                        <input type="password" name="current_password" autocomplete="current-password"
                               class="form-control @error('current_password') error @enderror" required>
                        @error('current_password') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.profile_page.new_password') }}</label>
                        <input type="password" name="password" autocomplete="new-password"
                               class="form-control @error('password') error @enderror" required>
                        @error('password') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('ui.profile_page.confirm_password') }}</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                               class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="align-self:flex-start;">
                        <i class="ri-save-3-line"></i> {{ __('ui.profile_page.save_password') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Change Email ─────────────────────────────────────────────── --}}
        <div class="card" id="email">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.profile_page.change_email') }}</div>
                    <div class="card-subtitle">{{ __('ui.profile_page.change_email_hint') }}</div>
                </div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;color:#6366f1;flex-shrink:0;">
                    <i class="ri-mail-settings-line"></i>
                </div>
            </div>

            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1rem;margin-top:1rem;">

                <div class="form-group">
                    <label class="form-label">{{ __('ui.profile_page.current_email') }}</label>
                    <input type="text" value="{{ Auth::user()->email }}" class="form-control" readonly
                           style="background:var(--page-bg);color:var(--text-muted);">
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('ui.profile_page.new_email') }}</label>
                    <input type="email" x-model="newEmail"
                           placeholder="{{ __('ui.profile_page.new_email_placeholder') }}"
                           class="form-control" :class="emailError ? 'error' : ''">
                    <div x-show="emailError" x-text="emailError"
                         style="color:#ef4444;font-size:.8125rem;margin-top:.25rem;"></div>
                </div>

                <button type="button" @click="requestOtp()"
                        :disabled="sending || !newEmail.trim()"
                        class="btn btn-primary" style="align-self:flex-start;">
                    <template x-if="!sending">
                        <span><i class="ri-mail-send-line"></i> {{ __('ui.profile_page.send_otp') }}</span>
                    </template>
                    <template x-if="sending">
                        <span style="display:flex;align-items:center;gap:.375rem;">
                            <div class="spinner" style="width:.8rem;height:.8rem;border-width:2px;"></div>
                            {{ __('ui.processing') }}
                        </span>
                    </template>
                </button>
            </div>
        </div>

    </div>

    {{-- ── OTP Modal ────────────────────────────────────────────────────── --}}
    <div class="modal-overlay" x-show="showOtp" x-transition.opacity
         style="display:none;" @click.self="showOtp = false">
        <div class="modal-box" style="max-width:400px;" @click.stop>
            <div class="modal-icon" style="background:rgba(99,102,241,.1);color:#6366f1;">
                <i class="ri-mail-check-line" style="font-size:1.25rem;"></i>
            </div>
            <h3>{{ __('auth.register.otp_heading') }}</h3>
            <p style="font-size:.875rem;color:var(--text-muted);">
                {{ __('auth.register.otp_subheading') }} <strong x-text="newEmail"></strong>
            </p>

            <div style="margin:1.25rem 0;">
                <input type="text" x-model="otpCode" maxlength="6" inputmode="numeric"
                       @keydown.enter="verifyOtp()"
                       placeholder="• • • • • •"
                       style="width:100%;text-align:center;letter-spacing:.5rem;font-size:1.5rem;font-weight:700;padding:.75rem;border:2px solid var(--card-border);border-radius:.75rem;background:var(--page-bg);"
                       :style="otpError ? 'border-color:#ef4444;' : (otpCode.length===6 ? 'border-color:var(--brand);' : '')">
                <div x-show="otpError" x-text="otpError"
                     style="color:#ef4444;font-size:.8125rem;margin-top:.5rem;text-align:center;"></div>
            </div>

            <div style="display:flex;flex-direction:column;gap:.625rem;">
                <button type="button" @click="verifyOtp()"
                        :disabled="verifying || otpCode.length < 6"
                        class="btn btn-primary" style="width:100%;">
                    <template x-if="!verifying">
                        <span>{{ __('auth.register.otp_verify_btn') }}</span>
                    </template>
                    <template x-if="verifying">
                        <span style="display:flex;align-items:center;justify-content:center;gap:.5rem;">
                            <div class="spinner" style="width:.8rem;height:.8rem;border-width:2px;"></div>
                            {{ __('ui.processing') }}
                        </span>
                    </template>
                </button>
                <button type="button" @click="resendOtp()"
                        :disabled="resendCooldown > 0"
                        class="btn btn-ghost btn-sm">
                    <span x-show="resendCooldown <= 0">{{ __('auth.register.otp_resend') }}</span>
                    <span x-show="resendCooldown > 0" x-text="'{{ __('auth.register.otp_resend_wait', [':s' => '']) }}' + resendCooldown + 's'"></span>
                </button>
                <button type="button" @click="showOtp = false" class="btn btn-ghost btn-sm" style="color:var(--text-muted);">
                    {{ __('auth.register.otp_back') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ── Success toast ────────────────────────────────────────────────── --}}
    <div x-show="emailSuccess" x-transition.opacity
         style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;padding:.875rem 1.25rem;background:#059669;color:#fff;border-radius:.875rem;font-size:.875rem;font-weight:600;display:flex;align-items:center;gap:.625rem;box-shadow:0 4px 20px rgba(5,150,105,.35);">
        <i class="ri-checkbox-circle-line"></i>
        {{ __('ui.profile_page.email_updated') }}
    </div>

</div>

@push('scripts')
<script>
function profilePage() {
    return {
        newEmail:     '',
        otpCode:      '',
        sending:      false,
        verifying:    false,
        showOtp:      false,
        emailError:   null,
        otpError:     null,
        emailSuccess: false,
        resendCooldown: 0,
        _timer: null,

        async requestOtp() {
            this.emailError = null;
            this.sending = true;
            try {
                const res = await fetch('{{ route(auth()->user()->routeNamePrefix() . ".profile.email-change") }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ new_email: this.newEmail }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.otpCode  = '';
                    this.otpError = null;
                    this.showOtp  = true;
                    this.startCooldown(60);
                } else if (res.status === 429 && data.wait) {
                    this.emailError = '{{ __("auth.register.otp_resend_wait", [":s" => ""]) }}' + data.wait + 's';
                } else {
                    const errors = data.errors?.new_email;
                    this.emailError = errors ? errors[0] : (data.message || '{{ __("auth.register.server_error") }}');
                }
            } catch { this.emailError = '{{ __("auth.register.server_error") }}'; }
            finally { this.sending = false; }
        },

        async verifyOtp() {
            this.otpError = null;
            this.verifying = true;
            try {
                const res = await fetch('{{ route(auth()->user()->routeNamePrefix() . ".profile.email-verify") }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ otp: this.otpCode }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.showOtp = false;
                    this.emailSuccess = true;
                    // Update displayed current email
                    document.querySelectorAll('input[readonly]').forEach(el => {
                        if (el.value.includes('@')) el.value = data.email;
                    });
                    this.newEmail = '';
                    setTimeout(() => { this.emailSuccess = false; }, 4000);
                } else {
                    this.otpError = data.message || '{{ __("auth.register.otp_invalid") }}';
                }
            } catch { this.otpError = '{{ __("auth.register.server_error") }}'; }
            finally { this.verifying = false; }
        },

        async resendOtp() {
            if (this.resendCooldown > 0) return;
            await this.requestOtp();
            if (this.showOtp) this.startCooldown(60);
        },

        startCooldown(seconds) {
            clearInterval(this._timer);
            this.resendCooldown = seconds;
            this._timer = setInterval(() => {
                if (this.resendCooldown > 0) this.resendCooldown--;
                else clearInterval(this._timer);
            }, 1000);
        },
    }
}
</script>
@endpush
@endsection
