<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalService
{
    /**
     * Currencies PayPal accepts on the Orders v2 API without account-level
     * restrictions. A checkout priced in anything else must fall back to
     * base USD before hitting createOrder(), otherwise PayPal 422s with
     * UNPROCESSABLE_ENTITY / CURRENCY_NOT_SUPPORTED and the buyer sees the
     * generic "Could not initialize payment" message with no way forward.
     *
     * https://developer.paypal.com/api/rest/reference/currency-codes/
     *
     * INR and RUB are excluded — INR is domestic-only (Indian merchants
     * receiving from Indian buyers), RUB is sanctions-restricted. Neither
     * matches our merchant setup, so treating them as unsupported forces
     * the safe USD fallback.
     */
    public const SUPPORTED_CURRENCIES = [
        'AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF',
        'ILS', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD',
        'THB', 'TWD', 'USD',
    ];

    public static function supportsCurrency(?string $code): bool
    {
        return $code !== null
            && in_array(strtoupper($code), self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Return a `$priced` array (same shape as Plan::priceFor()) that is
     * safe to send to PayPal. When the localised currency isn't on
     * PayPal's supported list, swap to the plan's base USD price and log
     * the fallback for audit.
     *
     * Callers must treat the returned array as authoritative for BOTH the
     * PayPal API call AND any persistence (TenantPayment.currency, view
     * display), otherwise the buyer sees one price and gets charged
     * another — a real bug, not just cosmetics.
     *
     * @param  array{amount?: float, currency?: string, symbol?: string, is_local?: bool}  $priced
     * @return array{amount: float, currency: string, symbol: string, is_local: bool}
     */
    public static function compatiblePricing(\App\Models\Plan $plan, array $priced, ?int $tenantId = null): array
    {
        $currency = strtoupper((string) ($priced['currency'] ?? 'USD'));

        if (self::supportsCurrency($currency)) {
            return [
                'amount'   => (float) ($priced['amount'] ?? 0),
                'currency' => $currency,
                'symbol'   => (string) ($priced['symbol'] ?? '$'),
                'is_local' => (bool)   ($priced['is_local'] ?? false),
            ];
        }

        Log::warning('PayPal cannot take local currency, falling back to USD', [
            'tenant_id'      => $tenantId,
            'plan_id'        => $plan->id,
            'local_currency' => $currency,
            'local_amount'   => (float) ($priced['amount'] ?? 0),
        ]);

        return [
            'amount'   => (float) $plan->price_monthly,
            'currency' => 'USD',
            'symbol'   => '$',
            'is_local' => false,
        ];
    }

    private string $baseUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $webhookId;
    private string $currency;
    private ?string $payeeEmail;
    private ?string $restPayeeEmail;

    public function __construct()
    {
        $mode              = config('services.paypal.mode', 'sandbox');
        $this->baseUrl     = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        $this->clientId    = config('services.paypal.client_id');
        $this->clientSecret= config('services.paypal.client_secret');
        $this->webhookId   = config('services.paypal.webhook_id');
        $this->currency    = config('services.paypal.currency', 'USD');
        $this->payeeEmail  = config('services.paypal.payee_email');
        $this->restPayeeEmail = config('services.paypal.rest_payee_email');
    }

    /**
     * @param int $timeout Seconds to wait. The default suits order calls, where
     *                     the buyer is watching a spinner and we would rather
     *                     wait than fail. credentialsValid() passes something
     *                     much shorter because it runs inside a page render.
     */
    public function getAccessToken(int $timeout = 20): string
    {
        if (!$this->clientId || !$this->clientSecret) {
            throw new RuntimeException('PayPal credentials are not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson()
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout)
            ->post($this->baseUrl . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (!$response->successful()) {
            throw new RuntimeException('PayPal token error: ' . $response->body());
        }

        return (string) $response->json('access_token');
    }

    /**
     * Can we actually get an OAuth token with the configured id/secret?
     *
     * The checkout page needs this to decide between the REST/SDK flow and the
     * email-only Standard flow. A client id that is present but unusable (wrong
     * secret, sandbox key while PAYPAL_MODE=live, revoked app) would otherwise
     * render smart buttons that die on the first create-order call, with the
     * working Standard flow switched off behind them.
     *
     * Cached so a page view never pays for a round trip to PayPal; the negative
     * result is cached briefly so fixing .env takes effect quickly.
     */
    public function credentialsValid(): bool
    {
        if (!$this->clientId || !$this->clientSecret) {
            return false;
        }

        $key = 'paypal:creds_ok:' . config('services.paypal.mode', 'sandbox')
             . ':' . substr(hash('sha256', $this->clientId . '|' . $this->clientSecret), 0, 16);

        $cached = Cache::get($key);
        if ($cached !== null) {
            return (bool) $cached;
        }

        try {
            // Short fuse: an unreachable PayPal must not stall the checkout
            // page. Failing here just means the Standard flow is offered.
            $this->getAccessToken(6);
            Cache::put($key, true, now()->addHours(6));
            return true;
        } catch (\Throwable $e) {
            Log::warning('PayPal REST credentials rejected — falling back to Standard checkout.', [
                'mode'  => config('services.paypal.mode'),
                'error' => $e->getMessage(),
            ]);
            Cache::put($key, false, now()->addMinutes(2));
            return false;
        }
    }

    /**
     * Client token for the JS SDK's card-fields component.
     *
     * Advanced Credit and Debit Card Payments will not initialise without one:
     * the SDK script tag has to carry it as data-client-token, and without it
     * cardFields.isEligible() is false and the buyer never sees an inline card
     * form — only the PayPal-hosted fallback.
     *
     * https://developer.paypal.com/docs/checkout/advanced/integrate/
     *
     * Merchant-scoped (no customer_id, since nothing here vaults cards), so one
     * token serves every buyer and can be cached. PayPal expires these after
     * about an hour; the cache window stays well inside that.
     *
     * @return string|null null when the account is not provisioned for advanced
     *                     card payments, or PayPal is unreachable. The checkout
     *                     page treats that as "no inline fields" and falls back.
     */
    public function clientToken(): ?string
    {
        if (!$this->clientId || !$this->clientSecret) {
            return null;
        }

        $key = 'paypal:client_token:' . config('services.paypal.mode', 'sandbox')
             . ':' . substr(hash('sha256', (string) $this->clientId), 0, 16);

        return Cache::remember($key, now()->addMinutes(20), function () {
            try {
                $response = Http::withToken($this->getAccessToken(6))
                    ->acceptJson()
                    ->withHeaders(['Accept-Language' => 'en_US'])
                    ->connectTimeout(5)
                    ->timeout(8)
                    // (object) [] so the body serialises to `{}`. Passing no
                    // data makes Laravel send `[]`, which this endpoint answers
                    // with a bodyless HTTP 500.
                    ->post($this->baseUrl . '/v1/identity/generate-token', (object) []);

                if (!$response->successful()) {
                    Log::warning('PayPal client token request failed — inline card fields disabled.', [
                        'status' => $response->status(),
                        'body'   => mb_substr($response->body(), 0, 300),
                    ]);
                    return null;
                }

                return $response->json('client_token');
            } catch (\Throwable $e) {
                Log::warning('PayPal client token error — inline card fields disabled.', [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    public function createOrder(float $amount, string $description, string $returnUrl, string $cancelUrl, array $metadata = [], ?string $currency = null): array
    {
        $token = $this->getAccessToken();

        // Per-call currency override — Phase 3 sends the visitor's local
        // currency here. Falls back to the constructor default (PAYPAL_CURRENCY
        // env) when the caller doesn't pass one, so the existing web-flow
        // callers keep working unchanged.
        $orderCurrency = strtoupper($currency ?: $this->currency);

        $purchaseUnit = [
            'amount' => [
                'currency_code' => $orderCurrency,
                'value'         => number_format($amount, 2, '.', ''),
            ],
            'description'   => mb_substr($description, 0, 127),
            'custom_id'     => (string) ($metadata['tenant_id'] ?? ''),
            'invoice_id'    => (string) ($metadata['invoice_id'] ?? uniqid('inv_', true)),
        ];

        // Deliberately NOT PAYPAL_PAYEE_EMAIL. That address belongs to the
        // email-only Standard flow, where it is the merchant. Sending it here
        // names a third-party payee, which PayPal only honours for accounts
        // with partner/multiparty permissions — otherwise the order is created
        // fine and then dies at confirm-payment-source with a bodyless
        // INTERNAL_SERVICE_ERROR, so the buyer types a full card and gets an
        // unexplained failure. Left unset, funds go to the account that owns
        // PAYPAL_CLIENT_ID, which is what a direct integration wants.
        if ($this->restPayeeEmail) {
            $purchaseUnit['payee'] = [
                'email_address' => $this->restPayeeEmail,
            ];
        }

        $payload = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [$purchaseUnit],
            'application_context' => [
                'brand_name'          => config('app.name', 'Wavadesk'),
                'landing_page'        => 'LOGIN',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl . '/v2/checkout/orders', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('PayPal create order failed: ' . $response->body());
        }

        return $response->json();
    }

    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->baseUrl . "/v2/checkout/orders/{$orderId}/capture", (object) []);

        if (!$response->successful()) {
            throw new RuntimeException('PayPal capture failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->baseUrl . "/v2/checkout/orders/{$orderId}");

        if (!$response->successful()) {
            throw new RuntimeException('PayPal get order failed: ' . $response->body());
        }

        return $response->json();
    }

    public function isCompleted(array $order): bool
    {
        return ($order['status'] ?? null) === 'COMPLETED';
    }

    public function extractApproveUrl(array $order): ?string
    {
        // Older PayPal API responses name this link `approve`; newer ones
        // (post-2024) rename it to `payer-action`. Same URL, same purpose —
        // accept either so an API version bump on PayPal's side doesn't
        // start throwing "PayPal approve URL not found in order response."
        foreach ($order['links'] ?? [] as $link) {
            $rel = $link['rel'] ?? null;
            if ($rel === 'approve' || $rel === 'payer-action') {
                return $link['href'] ?? null;
            }
        }
        return null;
    }

    public function extractCaptureId(array $capture): ?string
    {
        return $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
    }

    public function verifyWebhookSignature(array $headers, string $rawBody): bool
    {
        if (!$this->webhookId) {
            Log::warning('PayPal webhook verification skipped — PAYPAL_WEBHOOK_ID not set.');
            return true;
        }

        $token = $this->getAccessToken();

        $payload = [
            'auth_algo'         => $headers['paypal-auth-algo'] ?? $headers['PAYPAL-AUTH-ALGO'] ?? '',
            'cert_url'          => $headers['paypal-cert-url'] ?? $headers['PAYPAL-CERT-URL'] ?? '',
            'transmission_id'   => $headers['paypal-transmission-id'] ?? $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            'transmission_sig'  => $headers['paypal-transmission-sig'] ?? $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'webhook_id'        => $this->webhookId,
            'webhook_event'     => json_decode($rawBody, true),
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl . '/v1/notifications/verify-webhook-signature', $payload);

        return $response->successful()
            && ($response->json('verification_status') === 'SUCCESS');
    }
}
