<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SsoHandoffCode
 *
 * Signs and verifies short-lived, single-use codes used to pass an authenticated
 * identity from the marketing app (wavadesk.com / Server A) to the core app
 * (app.wavadesk.com / Server B) without ever putting a Sanctum bearer token
 * into a URL, referrer header, or browser history.
 *
 * The wire format is `base64url(payload).base64url(hmac_sha256(payload, secret))`.
 * Payload is `{"uid":<int>,"exp":<unix>,"n":"<nonce>"}`. Only Server B verifies;
 * Server A only mints. The HMAC secret (`WAVADESK_SHARED_SECRET`) is set on both
 * sides and MUST match — a mismatch fails closed with an invalid-code error.
 *
 * Nonce is cached on verify for `ttl+30s` so a leaked code can only be redeemed
 * once. TTL is 60 seconds by default — long enough for a redirect, short enough
 * that a stolen URL is useless after the round-trip completes.
 */
class SsoHandoffCode
{
    /** Cache prefix for consumed nonces. */
    private const NONCE_CACHE_PREFIX = 'sso:handoff:nonce:';

    /** Default TTL for a fresh code, in seconds. */
    public const DEFAULT_TTL = 60;

    public function __construct(
        private readonly string $secret,
    ) {
        if ($this->secret === '' || strlen($this->secret) < 32) {
            throw new \RuntimeException(
                'SsoHandoffCode requires WAVADESK_SHARED_SECRET (>=32 chars).'
            );
        }
    }

    /**
     * Mint a signed handoff code for the given user id.
     *
     * @param  int  $userId
     * @param  int  $ttlSeconds  Lifetime of the code, capped at 300s.
     */
    public function mint(int $userId, int $ttlSeconds = self::DEFAULT_TTL): string
    {
        $ttl = max(15, min(300, $ttlSeconds));

        $payload = [
            'uid' => $userId,
            'exp' => time() + $ttl,
            'n'   => Str::random(16),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig  = hash_hmac('sha256', $json, $this->secret, true);

        return $this->base64UrlEncode($json) . '.' . $this->base64UrlEncode($sig);
    }

    /**
     * Verify a handoff code and return the user id it carries.
     *
     * Throws on any of: malformed input, bad signature, expired, nonce already
     * seen. Callers should treat any exception the same way — a generic
     * "invalid handoff" response — so verifiers can't be probed for oracle
     * behavior.
     *
     * @return int  The verified user id.
     */
    public function verify(string $code): int
    {
        $parts = explode('.', $code, 3);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('malformed');
        }

        $json = $this->base64UrlDecode($parts[0]);
        $sig  = $this->base64UrlDecode($parts[1]);

        $expected = hash_hmac('sha256', $json, $this->secret, true);
        if (! hash_equals($expected, $sig)) {
            throw new \InvalidArgumentException('signature');
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)
            || ! isset($payload['uid'], $payload['exp'], $payload['n'])) {
            throw new \InvalidArgumentException('payload');
        }

        if ((int) $payload['exp'] < time()) {
            throw new \InvalidArgumentException('expired');
        }

        // Single-use: reject any nonce we've already consumed. Cache TTL is
        // slightly longer than the code TTL so a code that expires in-flight
        // still can't be replayed the moment before its expiry cache eviction.
        $key = self::NONCE_CACHE_PREFIX . $payload['n'];
        if (Cache::has($key)) {
            throw new \InvalidArgumentException('replayed');
        }
        Cache::put($key, 1, ((int) $payload['exp'] - time()) + 30);

        return (int) $payload['uid'];
    }

    private function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($s, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('base64');
        }
        return $decoded;
    }
}
