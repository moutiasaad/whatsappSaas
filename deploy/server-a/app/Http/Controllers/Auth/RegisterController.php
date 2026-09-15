<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SsoHandoffCode;
use App\Services\WavadeskApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RegisterController (Server A — wavadesk.com)
 *
 * The marketing-side register form is a thin proxy: it posts the same fields
 * the old monolith accepted, but forwards them to Server B's v1 register API.
 * On success the controller:
 *
 *   1. Stashes the Sanctum token in the session (encrypted at rest — Laravel
 *      session driver responsibility, never a raw cookie).
 *   2. Mints a short-lived signed handoff code with the shared HMAC secret.
 *   3. Redirects the browser to https://app.wavadesk.com/auth/sso?code=…
 *
 * Server B's SsoHandoffController redeems the code, opens a first-party web
 * session on app.wavadesk.com, and routes to /register/plan (or the panel).
 *
 * If the API call fails, the user sees the same field-level errors they
 * would have seen on the old monolith — 422 payloads from Server B come back
 * as `{errors: {field: [msg]}}` and are re-thrown as ValidationExceptions.
 */
class RegisterController extends Controller
{
    public function __construct(
        private readonly WavadeskApi $api,
        private readonly SsoHandoffCode $codes,
    ) {}

    /**
     * GET /register — the same landing view as before, no auth call.
     */
    public function show(Request $request)
    {
        return view('auth.register', [
            'intendedPlan' => $request->get('plan'),
        ]);
    }

    /**
     * POST /register — forward to Server B, then SSO-redirect on success.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'email'        => 'required|email',
            'password'     => 'required|string|min:8',
            'plan_id'      => 'nullable|integer',
        ]);

        $result = $this->api->register($data);

        if (! $result['ok']) {
            $this->rethrowServerBValidation($result);

            Log::warning('Server A -> B register failed', [
                'status' => $result['status'],
                'body'   => $result['body'],
            ]);

            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'general' => $result['body']['message'] ?? __('auth.register.server_error'),
                ]);
        }

        $body   = $result['body'];
        $userId = (int) ($body['user']['id'] ?? 0);
        $token  = (string) ($body['token'] ?? '');

        if ($userId === 0 || $token === '') {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['general' => __('auth.register.server_error')]);
        }

        // Persist the PAT so subsequent marketing-side requests (billing page,
        // account settings — anything that stays on wavadesk.com) can call
        // Server B without re-prompting for credentials. Session storage is
        // encrypted by Laravel and cleared on logout.
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
     * Convert a 422 payload from Server B into a Laravel validation error the
     * marketing form can render field-by-field, so an "email taken" error
     * looks the same as if the form ran locally.
     */
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
