<?php

namespace App\Support;

/**
 * Wavadesk
 *
 * Which half of the split deployment is this process? Everything that behaves
 * differently on wavadesk.com (marketing) versus app.wavadesk.com (core) asks
 * here rather than reading config keys inline, so the answer has exactly one
 * definition and grepping for `isMarketing` finds every branch.
 *
 * @see config/wavadesk.php
 * @see deploy/split-hosting/README.md
 */
final class Wavadesk
{
    public const ROLE_CORE      = 'core';
    public const ROLE_MARKETING = 'marketing';

    /** Header Server A authenticates itself with when calling Server B. */
    public const CALLER_HEADER = 'X-Wavadesk-Caller';

    /** Session keys the marketing app stashes the core app's PAT under. */
    public const SESSION_TOKEN  = 'wavadesk.api_token';
    public const SESSION_USER   = 'wavadesk.user';
    public const SESSION_TENANT = 'wavadesk.tenant';

    public static function role(): string
    {
        return config('wavadesk.role') === self::ROLE_MARKETING
            ? self::ROLE_MARKETING
            : self::ROLE_CORE;
    }

    /** True on wavadesk.com: no local identity, auth proxies to the core app. */
    public static function isMarketing(): bool
    {
        return self::role() === self::ROLE_MARKETING;
    }

    /** True on app.wavadesk.com, and on every un-split/dev box. */
    public static function isCore(): bool
    {
        return self::role() === self::ROLE_CORE;
    }

    public static function coreUrl(): string
    {
        return rtrim((string) config('wavadesk.core_url'), '/');
    }

    public static function marketingOrigin(): string
    {
        return rtrim((string) config('wavadesk.marketing_origin'), '/');
    }

    public static function sharedSecret(): string
    {
        return (string) config('wavadesk.shared_secret', '');
    }

    /**
     * A secret is usable only if it is long enough to be a real HMAC key.
     * Both the handoff signer and the caller guard fail closed without one,
     * so this is the single "is the split wired up?" test.
     */
    public static function hasSharedSecret(): bool
    {
        return strlen(self::sharedSecret()) >= 32;
    }

    /** Absolute URL on the core app, e.g. url('/auth/sso'). */
    public static function coreUrlTo(string $path): string
    {
        return self::coreUrl() . '/' . ltrim($path, '/');
    }
}
