<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * Prices and sizes for the billing add-ons, owned by the super admin.
 *
 * Every value lives in platform_settings so it can be changed from
 * /admin-control-panel/platform/addons without a deploy; config/billing.php
 * only supplies the fallback used before the operator has ever saved the form.
 * Reading through here rather than config() directly is what keeps the billing
 * page, the checkout and the order actually agreeing on a price.
 */
class AddonPricing
{
    public const KEY_SEAT_PRICE    = 'addon.seat_price';
    public const KEY_SEAT_MAX      = 'addon.seat_max';
    public const KEY_PACK_PRICE    = 'addon.ai_pack_price';
    public const KEY_PACK_MESSAGES = 'addon.ai_pack_messages';
    public const KEY_PACK_MAX      = 'addon.ai_pack_max';
    public const KEY_SEATS_ENABLED = 'addon.seats_enabled';
    public const KEY_PACKS_ENABLED = 'addon.ai_packs_enabled';

    /** Price of one extra agent seat, in the platform currency. */
    public static function seatPrice(): float
    {
        return self::money(self::KEY_SEAT_PRICE, (float) config('billing.seat.price', 6.00));
    }

    /** Price of one AI message pack. */
    public static function packPrice(): float
    {
        return self::money(self::KEY_PACK_PRICE, (float) config('billing.ai_pack.price', 9.00));
    }

    /** How many AI messages one pack grants. */
    public static function packMessages(): int
    {
        return max(1, (int) PlatformSetting::get(
            self::KEY_PACK_MESSAGES,
            (int) config('billing.ai_pack.messages', 2500),
        ));
    }

    /** Ceiling on the quantity steppers — a guard, not a lifetime cap. */
    public static function maxSeats(): int
    {
        return max(1, (int) PlatformSetting::get(self::KEY_SEAT_MAX, (int) config('billing.seat.max', 50)));
    }

    public static function maxPacks(): int
    {
        return max(1, (int) PlatformSetting::get(self::KEY_PACK_MAX, (int) config('billing.ai_pack.max_packs', 20)));
    }

    /**
     * Is the add-on on sale at all? An operator who has not priced seats yet
     * can hide the row rather than sell something at a placeholder price.
     */
    public static function seatsEnabled(): bool
    {
        return (bool) PlatformSetting::get(self::KEY_SEATS_ENABLED, true) && self::seatPrice() > 0;
    }

    public static function packsEnabled(): bool
    {
        return (bool) PlatformSetting::get(self::KEY_PACKS_ENABLED, true) && self::packPrice() > 0;
    }

    public static function currency(): string
    {
        return (string) config('billing.currency', 'USD');
    }

    /** Total for a seat order, rounded so the gateway charge always matches. */
    public static function seatTotal(int $seats): float
    {
        return round($seats * self::seatPrice(), 2);
    }

    public static function packTotal(int $packs): float
    {
        return round($packs * self::packPrice(), 2);
    }

    /** Everything the billing page and its Alpine state need, in one shape. */
    public static function forView(): array
    {
        return [
            'seat_price'     => self::seatPrice(),
            'seat_max'       => self::maxSeats(),
            'seats_enabled'  => self::seatsEnabled(),
            'pack_price'     => self::packPrice(),
            'pack_messages'  => self::packMessages(),
            'pack_max'       => self::maxPacks(),
            'packs_enabled'  => self::packsEnabled(),
            'currency'       => self::currency(),
        ];
    }

    /**
     * Money settings are stored as text so cents survive; PlatformSetting has
     * no decimal type, and casting through integer would quietly floor $6.50.
     */
    private static function money(string $key, float $default): float
    {
        $raw = PlatformSetting::get($key);

        return $raw === null || $raw === '' ? $default : max(0, round((float) $raw, 2));
    }
}
