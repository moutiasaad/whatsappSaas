<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    private const OTP_TTL_MINUTES   = 10;
    private const RESEND_COOLDOWN_S = 60;

    public function show()
    {
        return view('admin.profile.index');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => __('ui.profile_page.current_password_wrong')])->withFragment('password');
        }

        $user->update(['password' => $request->password]);

        return back()->with('password_success', __('ui.profile_page.password_updated'))->withFragment('password');
    }

    public function requestEmailChange(Request $request)
    {
        $request->validate([
            'new_email' => 'required|email|unique:users,email,' . Auth::id(),
        ]);

        $cooldown = session('_email_change_pending.sent_at');
        if ($cooldown && now()->timestamp - $cooldown < self::RESEND_COOLDOWN_S) {
            return response()->json([
                'wait' => self::RESEND_COOLDOWN_S - (now()->timestamp - $cooldown),
            ], 429);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        session([
            '_email_change_pending' => [
                'otp'        => $otp,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
                'sent_at'    => now()->timestamp,
                'new_email'  => $request->new_email,
            ],
        ]);

        try {
            Mail::to($request->new_email)->send(new OtpVerification($otp));
        } catch (\Throwable $e) {
            Log::error('Profile email change OTP failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('auth.register.otp_send_failed')], 500);
        }

        return response()->json(['sent' => true]);
    }

    public function verifyEmailChange(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $pending = session('_email_change_pending');

        if (!$pending) {
            return response()->json(['message' => __('auth.register.session_expired')], 422);
        }

        if (now()->timestamp > $pending['expires_at']) {
            session()->forget('_email_change_pending');
            return response()->json(['message' => __('auth.register.otp_expired')], 422);
        }

        if ($request->otp !== $pending['otp']) {
            return response()->json(['message' => __('auth.register.otp_invalid')], 422);
        }

        $newEmail = $pending['new_email'];

        if (\App\Models\User::where('email', $newEmail)->where('id', '!=', Auth::id())->exists()) {
            session()->forget('_email_change_pending');
            return response()->json(['message' => __('ui.profile_page.email_taken')], 422);
        }

        Auth::user()->update(['email' => $newEmail]);
        session()->forget('_email_change_pending');

        return response()->json(['success' => true, 'email' => $newEmail]);
    }
}
