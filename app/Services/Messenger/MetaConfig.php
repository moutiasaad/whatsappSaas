<?php

namespace App\Services\Messenger;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * MetaConfig
 *
 * Single lookup point for Facebook Messenger credentials. Reads from
 * platform_settings first (super-admin editable via the Meta Settings
 * page), falls back to config/services.php (which reads from env) so
 * a fresh dev box works without a DB seed and a rollback to
 * env-only stays viable.
 *
 * Secrets (app_secret, verify_token) are encrypted with Laravel's
 * Crypt facade before storage. The App ID and Graph version are
 * public — no encryption needed, easier to spot in the DB during ops
 * debugging.
 *
 * Every consumer of Meta credentials goes through here:
 *   - GraphApiClient (app_secret + graph_version)
 *   - MessengerConnectController (app_id for OAuth URL)
 *   - MessengerWebhookController (app_secret for HMAC, verify_token
 *     for the subscription handshake)
 *
 * Do NOT read config('services.meta.*') directly anywhere else — the
 * super-admin edit would silently take no effect. Grep for
 * 'services.meta' before adding a new caller.
 */
class MetaConfig
{
    public const KEY_APP_ID        = 'meta.app_id';
    public const KEY_APP_SECRET    = 'meta.app_secret';        // stored encrypted
    public const KEY_VERIFY_TOKEN  = 'meta.verify_token';      // stored encrypted
    public const KEY_GRAPH_VERSION = 'meta.graph_version';

    // ─── Reads ───────────────────────────────────────────────────────

    public static function appId(): string
    {
        return (string) (PlatformSetting::get(self::KEY_APP_ID) ?? config('services.meta.app_id', ''));
    }

    public static function appSecret(): string
    {
        return self::readEncrypted(self::KEY_APP_SECRET, 'services.meta.app_secret');
    }

    public static function verifyToken(): string
    {
        return self::readEncrypted(self::KEY_VERIFY_TOKEN, 'services.meta.verify_token');
    }

    public static function graphVersion(): string
    {
        return (string) (PlatformSetting::get(self::KEY_GRAPH_VERSION)
            ?? config('services.meta.graph_version', 'v21.0'));
    }

    // ─── Writes ──────────────────────────────────────────────────────

    public static function setAppId(string $value): void
    {
        PlatformSetting::set(self::KEY_APP_ID, trim($value));
    }

    public static function setAppSecret(string $value): void
    {
        $trimmed = trim($value);
        PlatformSetting::set(self::KEY_APP_SECRET, $trimmed === '' ? '' : Crypt::encryptString($trimmed));
    }

    public static function setVerifyToken(string $value): void
    {
        $trimmed = trim($value);
        PlatformSetting::set(self::KEY_VERIFY_TOKEN, $trimmed === '' ? '' : Crypt::encryptString($trimmed));
    }

    public static function setGraphVersion(string $value): void
    {
        PlatformSetting::set(self::KEY_GRAPH_VERSION, trim($value));
    }

    // ─── UI helpers ──────────────────────────────────────────────────

    /** True when the DB has a stored value (not just the env fallback). */
    public static function isStoredInDb(string $key): bool
    {
        return PlatformSetting::get($key) !== null;
    }

    /**
     * Mask a secret for display. Keeps the first + last 4 chars so ops
     * can eyeball "yes that's my current secret" without exposing the
     * middle. Empty string in → empty string out.
     */
    public static function maskSecret(string $value): string
    {
        if ($value === '') return '';
        if (strlen($value) <= 8) return str_repeat('•', strlen($value));
        return substr($value, 0, 4) . str_repeat('•', min(20, strlen($value) - 8)) . substr($value, -4);
    }

    // ─── Internals ───────────────────────────────────────────────────

    private static function readEncrypted(string $dbKey, string $envConfigKey): string
    {
        $stored = PlatformSetting::get($dbKey);
        if ($stored !== null && $stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable $e) {
                // A rotated APP_KEY makes every stored secret unreadable.
                // Log loud so ops sees the recovery path (either re-enter
                // the secret in the UI or restore the old APP_KEY).
                Log::warning('MetaConfig: decryption failed', [
                    'key'   => $dbKey,
                    'error' => $e->getMessage(),
                ]);
                return '';
            }
        }
        return (string) config($envConfigKey, '');
    }
}
