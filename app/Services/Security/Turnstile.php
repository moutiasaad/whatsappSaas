<?php

namespace App\Services\Security;

use App\Support\Wavadesk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turnstile
 *
 * Cloudflare's signup challenge, used here in its invisible mode: the widget
 * renders nothing for an ordinary visitor and only puts a checkbox on screen
 * when Cloudflare decides this particular request needs one. Nobody is asked
 * to prove anything unless the traffic looks automated, which is the whole
 * reason to prefer it over a captcha everyone has to solve.
 *
 * Two rules keep it from turning into a wall in front of real customers:
 *
 *  - It is off unless an operator switched it on AND both keys are set.
 *  - A verification that cannot be completed — Cloudflare unreachable, a
 *    timeout, a malformed answer — lets the signup through. An outage at
 *    Cloudflare must not become an outage of our own signup form. Only an
 *    explicit "this token is not valid" is treated as a failure.
 *
 * @see \App\Services\Security\TurnstileConfig  where the keys live
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** The field Cloudflare's script posts with the form. */
    public const FIELD = 'cf-turnstile-response';

    private const TIMEOUT_SECONDS = 5;

    /** How long the marketing host trusts its copy of the site key. */
    private const FRONTEND_TTL = 60;

    /**
     * Cloudflare error codes that describe OUR configuration rather than the
     * visitor. A signup must never be refused for one of these.
     *
     * @see https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
     */
    private const OUR_FAULT = [
        'missing-input-secret',
        'invalid-input-secret',
        'bad-request',
        'internal-error',
    ];

    /**
     * What a signup form needs to render the widget: whether to render it at
     * all, and the public site key.
     *
     * Core answers from its own settings. The marketing host has no keys of
     * its own — it asks core over the platform-preferences API and caches the
     * answer, the same way it already caches the default locale. If core
     * cannot be reached the widget is skipped rather than rendered broken:
     * a form nobody can submit is worse than one bot getting through.
     */
    public static function frontend(): array
    {
        if (Wavadesk::isCore()) {
            return [
                'enabled'  => TurnstileConfig::active(),
                'site_key' => TurnstileConfig::siteKey(),
            ];
        }

        return Cache::remember('wavadesk.turnstile.frontend', self::FRONTEND_TTL, function () {
            try {
                $res = app(\App\Services\WavadeskApi::class)->platformPreferences();

                if (($res['ok'] ?? false) && is_array($res['body']['turnstile'] ?? null)) {
                    return [
                        'enabled'  => (bool) ($res['body']['turnstile']['enabled'] ?? false),
                        'site_key' => (string) ($res['body']['turnstile']['site_key'] ?? ''),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Turnstile: could not read the site key from core', ['error' => $e->getMessage()]);
            }

            return ['enabled' => false, 'site_key' => ''];
        });
    }

    /**
     * Is this submission allowed through?
     *
     * Returns true when the challenge is off, when Cloudflare says the token
     * is good, and when Cloudflare could not be asked. Returns false only for
     * a missing token or an explicit rejection — the two cases that actually
     * describe a bot, or a visitor who was shown the checkbox and did not
     * complete it.
     */
    public static function passes(?string $token, ?string $ip = null): bool
    {
        if (! TurnstileConfig::active()) {
            return true;
        }

        $token = trim((string) $token);

        if ($token === '') {
            return false;
        }

        try {
            $res = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::VERIFY_URL, array_filter([
                    'secret'   => TurnstileConfig::secret(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            $body = $res->json();

            // Cloudflare answers a bad secret or a malformed token with HTTP
            // 400 and a JSON verdict, so the status code is not the signal —
            // the body is. A response we cannot read at all is the only thing
            // that counts as "could not be asked".
            if (! is_array($body) || ! array_key_exists('success', $body)) {
                Log::warning('Turnstile: no readable verdict, letting the signup through', [
                    'status' => $res->status(),
                ]);

                return true;
            }

            if ($body['success'] === true) {
                return true;
            }

            $codes = array_map('strval', (array) ($body['error-codes'] ?? []));

            // Some failures describe our own setup rather than the visitor: a
            // mistyped secret, a missing one, a request Cloudflare could not
            // parse. Treating those as "you are a bot" would turn one typo in
            // the control panel into a signup form nobody on earth can get
            // through — so they let the visitor in and shout at us instead.
            if (array_intersect(self::OUR_FAULT, $codes) !== []) {
                Log::error('Turnstile: signups are NOT being checked — fix the keys', ['errors' => $codes]);

                return true;
            }

            Log::info('Turnstile: signup challenge rejected', [
                'errors' => $codes,
                'ip'     => $ip,
            ]);

            return false;
        } catch (\Throwable $e) {
            // Timeout, DNS, TLS — our problem, not the visitor's.
            Log::warning('Turnstile: verification could not be completed, letting the signup through', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }
}
