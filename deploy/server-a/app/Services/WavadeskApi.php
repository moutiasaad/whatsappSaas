<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WavadeskApi
 *
 * Thin Http client wrapper Server A uses to talk to Server B's v1 auth API at
 * app.wavadesk.com. All calls carry a short timeout so a slow backend can't
 * pin a marketing-page request thread, and errors are normalised into a
 * predictable shape the controllers can render as validation errors without
 * leaking backend internals.
 *
 * Not injected anywhere else — the sole caller is the auth controller pair.
 */
class WavadeskApi
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly float $timeoutSeconds = 5.0,
    ) {
        if ($this->baseUrl === '') {
            throw new \RuntimeException('WAVADESK_APP_URL must be set on Server A.');
        }
    }

    /**
     * POST /api/v1/auth/register on Server B.
     *
     * @return array{ok: bool, status: int, body: array}
     */
    public function register(array $payload): array
    {
        return $this->call('post', '/api/v1/auth/register', $payload);
    }

    /**
     * POST /api/v1/auth/login on Server B.
     *
     * @return array{ok: bool, status: int, body: array}
     */
    public function login(array $payload): array
    {
        return $this->call('post', '/api/v1/auth/login', $payload);
    }

    /**
     * GET /api/v1/auth/me using a Sanctum bearer token.
     *
     * Only used if Server A wants to validate a stored token without a full
     * re-login (e.g. keeping the marketing header signed-in state in sync).
     */
    public function me(string $token): array
    {
        return $this->call('get', '/api/v1/auth/me', [], $token);
    }

    private function call(string $method, string $path, array $payload = [], ?string $token = null): array
    {
        $request = $this->client();
        if ($token) {
            $request = $request->withToken($token);
        }

        try {
            /** @var Response $response */
            $response = $request->send(strtoupper($method), $path, [
                'json' => $payload ?: null,
            ]);
        } catch (ConnectionException $e) {
            Log::error('WavadeskApi connection failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return [
                'ok'     => false,
                'status' => 0,
                'body'   => ['message' => 'Backend unreachable.'],
            ];
        } catch (\Throwable $e) {
            Log::error('WavadeskApi unexpected error', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return [
                'ok'     => false,
                'status' => 0,
                'body'   => ['message' => 'Backend error.'],
            ];
        }

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $response->json() ?? [],
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Origin' => (string) config('app.url'),
            ]);
    }
}
