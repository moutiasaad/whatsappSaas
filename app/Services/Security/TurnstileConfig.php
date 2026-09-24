<?php

namespace App\Services\Security;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * TurnstileConfig
 *
 * Single lookup point for the Cloudflare Turnstile keys, held in
 * platform_settings so the super admin can turn the signup challenge on and
 * off from the control panel without a deploy or an SSH session. Falls back
 * to config/services.php (env) so a fresh box and a rollback both work.
 *
 * The secret key is encrypted with Crypt before storage, the site key is not:
 * it is published in the page's HTML by design, and leaving it readable makes
 * ops debugging ("is this the key I pasted?") possible at a glance.
 *
 * Only core stores these. The marketing host renders the widget from the site
 * key it fetches over the platform-preferences API and never holds the secret
 * — verification happens on core, which is the box that owns the user table
 * the signup writes to.
 *
 * @see \App\Services\Security\Turnstile  the verifier and the frontend payload
 */
class TurnstileConfig
{
    public const KEY_ENABLED  = 'turnstile.enabled';
    public const KEY_SITE     = 'turnstile.site_key';
    public const KEY_SECRET   = 'turnstile.secret_key';   // stored encrypted

    // ─── Reads ───────────────────────────────────────────────────────

    /** The operator's switch, independent of whether keys are present. */
    public static function switchedOn(): bool
    {
        $stored = PlatformSetting::get(self::KEY_ENABLED);

        return $stored === null
            ? (bool) config('services.turnstile.enabled', false)
            : (bool) $stored;
    }

    public static function siteKey(): string
    {
        return (string) (PlatformSetting::get(self::KEY_SITE) ?? config('services.turnstile.site_key', ''));
    }

    public static function secret(): string
    {
        $stored = PlatformSetting::get(self::KEY_SECRET);

        if ($stored !== null && $stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable $e) {
                // A rotated APP_KEY makes the stored secret unreadable. Loud,
                // because the recovery is to re-enter it in the UI and the
                // alternative is a challenge nobody can pass.
                Log::warning('TurnstileConfig: secret decryption failed', ['error' => $e->getMessage()]);

                return '';
            }
        }

        return (string) config('services.turnstile.secret_key', '');
    }

    /**
     * The only question the rest of the app asks: should a signup be
     * challenged at all?
     *
     * Switched on but missing a key means half-configured, and half-configured
     * must not mean "reject everyone" — it means the challenge is simply not
     * in play yet. The settings page says so plainly rather than leaving the
     * operator to discover it from the signup form.
     */
    public static function active(): bool
    {
        return self::switchedOn() && self::siteKey() !== '' && self::secret() !== '';
    }

    // ─── Writes ──────────────────────────────────────────────────────

    public static function setEnabled(bool $on): void
    {
        PlatformSetting::set(self::KEY_ENABLED, $on, 'bool');
    }

    public static function setSiteKey(string $value): void
    {
        PlatformSetting::set(self::KEY_SITE, trim($value));
    }

    public static function setSecret(string $value): void
    {
        $trimmed = trim($value);
        PlatformSetting::set(self::KEY_SECRET, $trimmed === '' ? '' : Crypt::encryptString($trimmed));
    }

    // ─── UI helpers ──────────────────────────────────────────────────

    /** True when the DB holds a value, rather than the env fallback. */
    public static function isStoredInDb(string $key): bool
    {
        return PlatformSetting::get($key) !== null;
    }

    /** Keeps the ends visible so ops can recognise a key without exposing it. */
    public static function mask(string $value): string
    {
        if ($value === '')          return '';
        if (strlen($value) <= 8)    return str_repeat('•', strlen($value));

        return substr($value, 0, 4) . str_repeat('•', min(20, strlen($value) - 8)) . substr($value, -4);
    }
}
