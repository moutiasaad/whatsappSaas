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

    /** Absolute URL on the marketing site, e.g. marketingUrlTo('/login'). */
    public static function marketingUrlTo(string $path): string
    {
        return self::marketingOrigin() . '/' . ltrim($path, '/');
    }

    /**
     * Should this core host hand the public pages back to the marketing site?
     *
     * Requires WAVADESK_MARKETING_ORIGIN to name a host that is not this one.
     * Unset means "behave as the monolith always has", which keeps dev boxes
     * and replicas serving their own landing and auth pages. The host check is
     * what makes a rollback safe: a marketing box put back on `core` still has
     * the origin in its .env, and without the check it would redirect /login
     * to itself forever.
     */
    public static function delegatesToMarketing(string $host): bool
    {
        return self::isCore() && self::peerIsElsewhere(self::marketingOrigin(), $host);
    }

    /**
     * Should this marketing host hand the application pages to the core app?
     *
     * True whenever the split is on, because the marketing box has no business
     * answering for a panel, a payment or an API: its database is not the one
     * those pages mean.
     */
    public static function delegatesToCore(string $host): bool
    {
        return self::isMarketing() && self::peerIsElsewhere(self::coreUrl(), $host);
    }

    /** A peer we can redirect to: configured, parseable, and not us. */
    private static function peerIsElsewhere(string $origin, string $host): bool
    {
        if ($origin === '') {
            return false;
        }

        $peer = parse_url($origin, PHP_URL_HOST);

        return is_string($peer) && $peer !== '' && strcasecmp($peer, $host) !== 0;
    }
}
