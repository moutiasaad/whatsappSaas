<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide settings owned by the super admin, stored one row per key.
 *
 * Values are held as text with a `type` column saying how to read them back,
 * so a key can be an int, a flag or a string without a schema change per
 * setting. Reads are cached because the hot callers (the idle-close sweep, the
 * settings screen) would otherwise re-query the same handful of rows.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    private const CACHE_PREFIX = 'platform-setting:';
    private const CACHE_TTL    = 300;

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL,
            fn () => static::query()->where('key', $key)->first(['value', 'type'])?->only(['value', 'type']),
        );

        if ($row === null) {
            return $default;
        }

        return static::cast($row['value'], $row['type'] ?? 'string', $default);
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'type' => $type],
        );

        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    private static function cast(?string $value, string $type, mixed $default): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            default   => $value,
        };
    }
}
