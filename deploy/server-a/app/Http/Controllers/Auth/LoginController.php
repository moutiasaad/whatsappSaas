<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SsoHandoffCode;
use App\Services\WavadeskApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * LoginController (Server A — wavadesk.com)
 *
 * Same proxy pattern as RegisterController: the form posts email + password,
 * Server A forwards to /api/v1/auth/login on Server B, and on success the
 * user gets bounced through the SSO handoff onto app.wavadesk.com already
 * authenticated. Multi-portal routing (admin / supervisor / agent) is
 * decided by Server B based on the account's role, not on the URL that
 * received the credentials — Server A doesn't need per-role login pages.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly WavadeskApi $api,
        private readonly SsoHandoffCode $codes,
    ) {}

    public function show(Request $request)
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->api->login($credentials);

        if (! $result['ok']) {
            $this->rethrowServerBValidation($result);

            Log::info('Server A -> B login failed', [
                'status' => $result['status'],
                'email'  => $credentials['email'],
            ]);

            return back()->onlyInput('email')->withErrors([
                'email' => $result['body']['message'] ?? __('auth.errors.credentials_mismatch'),
            ]);
        }

        $body   = $result['body'];
        $userId = (int) ($body['user']['id'] ?? 0);
        $token  = (string) ($body['token'] ?? '');

        if ($userId === 0 || $token === '') {
            return back()->onlyInput('email')->withErrors([
                'email' => __('auth.errors.credentials_mismatch'),
            ]);
        }

        $request->session()->put('wavadesk.api_token', $token);
        $request->session()->put('wavadesk.user',       $body['user'] ?? []);
        $request->session()->put('wavadesk.tenant',     $body['tenant'] ?? []);

        $code = $this->codes->mint($userId);

        return redirect()->away(sprintf(
            '%s/auth/sso?code=%s',
            rtrim((string) config('services.wavadesk.app_url'), '/'),
            urlencode($code),
        ));
    }

    /**
     * Marketing-side logout: revoke the token on Server B (best-effort) and
     * clear the local session. Redirect to the landing page.
     */
    public function logout(Request $request)
    {
        $token = (string) $request->session()->pull('wavadesk.api_token', '');
        $request->session()->forget(['wavadesk.user', 'wavadesk.tenant']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($token !== '') {
            rescue(fn () => $this->api->me($token)); // no-op if backend already gone
        }

        return redirect()->route('landing');
    }

    private function rethrowServerBValidation(array $result): void
    {
        if ($result['status'] !== 422) {
            return;
        }

        $errors = $result['body']['errors'] ?? null;
        if (! is_array($errors) || $errors === []) {
            return;
        }

        throw ValidationException::withMessages($errors);
    }
}
