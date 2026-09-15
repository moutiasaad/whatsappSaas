<?php

namespace App\Services;

use App\Support\Wavadesk;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WavadeskApi
 *
 * The marketing app's (Server A / wavadesk.com) only door into the core app's
 * (Server B / app.wavadesk.com) v1 auth API. Server-to-server: these calls are
 * made by php-fpm, never by a browser, which is why they carry a shared-secret
 * header instead of CORS headers and why /api/v1/auth/* is not in config/cors.php.
 *
 * Every call is wrapped so the caller gets one predictable shape back —
 * ['ok' => bool, 'status' => int, 'body' => array] — and a dead backend
 * produces a rendered form error rather than a stack trace on a sales page.
 */
class WavadeskApi
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $callerSecret,
        private readonly float $timeoutSeconds = 5.0,
    ) {
        if ($this->baseUrl === '') {
            throw new \RuntimeException(
                'WAVADESK_CORE_URL must be set on the marketing app.'
            );
        }
    }

    /**
     * POST /api/v1/auth/register — create the workspace in the core app's DB.
     *
     * @param  array{company_name: string, email: string, password: string, plan_id?: int|null}  $payload
     * @return array{ok: bool, status: int, body: array}
     */
    public function register(array $payload): array
    {
        return $this->call('post', '/api/v1/auth/register', $payload);
    }

    /**
     * POST /api/v1/auth/login — verify credentials against the core app's DB.
     *
     * @param  array{email: string, password: string}  $payload
     * @return array{ok: bool, status: int, body: array}
     */
    public function login(array $payload): array
    {
        return $this->call('post', '/api/v1/auth/login', $payload);
    }

    /**
     * GET /api/v1/auth/me — validate a stored PAT and rehydrate the user
     * without a re-login. Used to keep the marketing header's signed-in state
     * honest after the core app may have disabled the account.
     *
     * @return array{ok: bool, status: int, body: array}
     */
    public function me(string $token): array
    {
        return $this->call('get', '/api/v1/auth/me', [], $token);
    }

    /**
     * POST /api/v1/auth/logout — revoke the PAT on the core app.
     *
     * Best-effort by design: the marketing session is cleared either way, so a
     * core app that is down cannot trap a user in a signed-in-looking page.
     *
     * @return array{ok: bool, status: int, body: array}
     */
    public function logout(string $token): array
    {
        return $this->call('post', '/api/v1/auth/logout', [], $token);
    }

    /**
     * @return array{ok: bool, status: int, body: array}
     */
    private function call(string $method, string $path, array $payload = [], ?string $token = null): array
    {
        $request = $this->client();

        if ($token !== null && $token !== '') {
            $request = $request->withToken($token);
        }

        try {
            /** @var Response $response */
            $response = strtolower($method) === 'get'
                ? $request->get($path)
                : $request->post($path, $payload);
        } catch (ConnectionException $e) {
            // Timeout or DNS/TCP failure. Logged with the path but never the
            // payload — it holds a plaintext password on the register/login call.
            Log::error('WavadeskApi connection failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'status' => 0, 'body' => ['message' => 'Backend unreachable.']];
        } catch (\Throwable $e) {
            Log::error('WavadeskApi unexpected error', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'status' => 0, 'body' => ['message' => 'Backend error.']];
        }

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $response->json() ?? [],
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeoutSeconds)
            // No retries on purpose: a register POST that timed out may well
            // have succeeded, and replaying it would answer the user with a
            // duplicate-email error for the account they just created.
            ->connectTimeout(min(3.0, $this->timeoutSeconds))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                Wavadesk::CALLER_HEADER => $this->callerSecret,
            ]);
    }
}
