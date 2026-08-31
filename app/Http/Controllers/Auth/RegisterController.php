<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerification;
use App\Models\Plan;
use App\Models\RegisterOtp;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    private const OTP_TTL_MINUTES   = 10;
    private const RESEND_COOLDOWN_S = 60;

    // ── Step 1: show registration form ──────────────────────────────────────

    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route(auth()->user()->homeRouteName());
        }

        $plans        = Plan::where('is_active', true)->orderBy('id')->get();
        $selectedPlan = $plans->firstWhere('id', (int) $request->get('plan')) ?? $plans->first();

        return view('auth.register', compact('plans', 'selectedPlan'));
    }

    // ── Step 2: validate form → send OTP ────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'admin_name'   => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8|confirmed',
            'plan_id'      => 'required|exists:plans,id',
        ]);

        $otp        = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt  = now()->addMinutes(self::OTP_TTL_MINUTES);
        $pendingData = $request->except(['_token', 'password', 'password_confirmation']);
        $passwordHash = Hash::make($request->password);

        // DEV ONLY: log plaintext OTP when APP_DEBUG=true.
        if (config('app.debug')) {
            Log::info('[OTP DEBUG] register code generated', [
                'email' => $request->email,
                'otp'   => $otp,
            ]);
        }

        // Persist BEFORE attempting to send the email — if delivery fails, the code
        // still exists in `register_otp_codes` and can be recovered/retried without
        // losing the pending registration data.
        $record = RegisterOtp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp'           => $otp,
                'data'          => $pendingData,
                'password_hash' => $passwordHash,
                'expires_at'    => $expiresAt,
                'sent_at'       => null,
                'verified_at'   => null,
                'resend_count'  => 0,
            ]
        );

        session([
            '_reg_pending' => [
                'otp'        => $otp,
                'expires_at' => $expiresAt->timestamp,
                'sent_at'    => now()->timestamp,
                'data'       => $pendingData,
                'password'   => $passwordHash,
            ],
        ]);

        $mailSent = false;
        try {
            Mail::to($request->email)->send(new OtpVerification($otp));
            $mailSent = true;
            $record->update(['sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('OTP email failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
        }

        $redirect = redirect()->route('register.otp');
        return $mailSent
            ? $redirect
            : $redirect->with('warning', __('auth.register.otp_email_saved_but_not_sent'));
    }

    // ── Step 3: show OTP entry page ─────────────────────────────────────────

    public function showOtp()
    {
        $pending = session('_reg_pending');

        if (!$pending) {
            return redirect()->route('register');
        }

        $maskedEmail = $this->maskEmail($pending['data']['email']);

        return view('auth.verify-otp', compact('maskedEmail'));
    }

    private function rehydratePendingFromDb(string $email): ?array
    {
        $record = RegisterOtp::where('email', $email)->first();
        if (!$record || $record->isExpired() || $record->isVerified()) {
            return null;
        }
        return [
            'otp'        => $record->otp,
            'expires_at' => $record->expires_at->timestamp,
            'sent_at'    => $record->sent_at?->timestamp ?? now()->timestamp,
            'data'       => $record->data ?? [],
            'password'   => $record->password_hash,
        ];
    }

    // ── Step 4: verify OTP → create account ─────────────────────────────────

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $pending = session('_reg_pending');

        if (!$pending) {
            return redirect()->route('register')->withErrors(['general' => __('auth.register.session_expired')]);
        }

        if (now()->timestamp > $pending['expires_at']) {
            session()->forget('_reg_pending');
            RegisterOtp::where('email', $pending['data']['email'] ?? '')->delete();
            return redirect()->route('register')->withErrors(['general' => __('auth.register.otp_expired')]);
        }

        if (!hash_equals($pending['otp'], $request->otp)) {
            return back()->withErrors(['otp' => __('auth.register.otp_invalid')]);
        }

        // Mark DB record as verified before we consume the pending payload.
        RegisterOtp::where('email', $pending['data']['email'] ?? '')
            ->update(['verified_at' => now()]);

        // OTP verified — create the account
        $data = $pending['data'];
        $plan = Plan::findOrFail($data['plan_id']);
        $isFree = !$plan->price_monthly || (float) $plan->price_monthly === 0.0;
        $slug   = $this->generateSlug($data['company_name']);

        DB::beginTransaction();
        try {
            $tenant = Tenant::create([
                'name'                => $data['company_name'],
                'slug'                => $slug,
                'plan_id'             => $plan->id,
                'subscription_status' => $isFree ? 'trial' : 'suspended',
                'trial_ends_at'       => $isFree ? now()->addDays(14) : null,
                'is_active'           => $isFree,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['admin_name'],
                'email'     => $data['email'],
                'password'  => $pending['password'],
                'role'      => 'admin',
                'is_active' => true,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Registration failed after OTP', ['error' => $e->getMessage()]);
            return back()->withErrors(['general' => __('auth.register.server_error')]);
        }

        session()->forget('_reg_pending');
        RegisterOtp::where('email', $data['email'])->delete();

        if ($isFree) {
            Auth::login($user);
            return redirect()->route('tenant_admin.dashboard')
                ->with('success', __('auth.register.welcome_trial'));
        }

        session(['_pending_register_user' => $user->id]);

        return redirect()->route('payment.checkout', ['tenant' => $tenant->id]);
    }

    // ── Resend OTP ───────────────────────────────────────────────────────────

    public function resendOtp(Request $request)
    {
        $pending = session('_reg_pending');

        if (!$pending) {
            return response()->json(['error' => __('auth.register.session_expired')], 400);
        }

        $secondsSinceSent = now()->timestamp - ($pending['sent_at'] ?? 0);
        if ($secondsSinceSent < self::RESEND_COOLDOWN_S) {
            $wait = self::RESEND_COOLDOWN_S - $secondsSinceSent;
            return response()->json(['error' => "Please wait {$wait}s before resending."], 429);
        }

        $otp       = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $email     = $pending['data']['email'];
        $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES);

        if (config('app.debug')) {
            Log::info('[OTP DEBUG] register code resent', [
                'email' => $email,
                'otp'   => $otp,
            ]);
        }

        // Persist BEFORE attempting mail so the new code survives delivery failures.
        $record = RegisterOtp::where('email', $email)->first();
        if ($record) {
            $record->update([
                'otp'          => $otp,
                'expires_at'   => $expiresAt,
                'sent_at'      => null,
                'resend_count' => $record->resend_count + 1,
            ]);
        }

        $mailSent = false;
        try {
            Mail::to($email)->send(new OtpVerification($otp));
            $mailSent = true;
            $record?->update(['sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('OTP resend failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        $pending['otp']        = $otp;
        $pending['expires_at'] = $expiresAt->timestamp;
        $pending['sent_at']    = now()->timestamp;
        session(['_reg_pending' => $pending]);

        if (!$mailSent) {
            return response()->json([
                'ok'      => true,
                'warning' => __('auth.register.otp_email_saved_but_not_sent'),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i    = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = min(3, strlen($local));
        return substr($local, 0, $visible) . str_repeat('*', max(0, strlen($local) - $visible)) . '@' . $domain;
    }
}
