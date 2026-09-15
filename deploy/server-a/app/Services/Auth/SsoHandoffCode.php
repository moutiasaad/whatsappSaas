<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SsoHandoffCode (Server A copy — MUST stay byte-identical with Server B).
 *
 * Server A mints; Server B verifies. Keeping the code in the same namespace
 * on both sides means either app could theoretically play either role — CI/CD
 * copies this file verbatim between the two repos.
 *
 * See app/Services/Auth/SsoHandoffCode.php on Server B for the full contract.
 */
class SsoHandoffCode
{
    private const NONCE_CACHE_PREFIX = 'sso:handoff:nonce:';
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
