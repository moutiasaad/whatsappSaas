<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalService
{
    private string $baseUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $webhookId;
    private string $currency;
    private ?string $payeeEmail;

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
    }

    public function getAccessToken(): string
    {
        if (!$this->clientId || !$this->clientSecret) {
            throw new RuntimeException('PayPal credentials are not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson()
            ->post($this->baseUrl . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (!$response->successful()) {
            throw new RuntimeException('PayPal token error: ' . $response->body());
        }

        return (string) $response->json('access_token');
    }

    public function createOrder(float $amount, string $description, string $returnUrl, string $cancelUrl, array $metadata = []): array
    {
        $token = $this->getAccessToken();

        $purchaseUnit = [
            'amount' => [
                'currency_code' => $this->currency,
                'value'         => number_format($amount, 2, '.', ''),
            ],
            'description'   => mb_substr($description, 0, 127),
            'custom_id'     => (string) ($metadata['tenant_id'] ?? ''),
            'invoice_id'    => (string) ($metadata['invoice_id'] ?? uniqid('inv_', true)),
        ];

        // Route funds to a specific PayPal account when PAYPAL_PAYEE_EMAIL is set.
        // Without this, payment goes to whichever merchant owns PAYPAL_CLIENT_ID.
        if ($this->payeeEmail) {
            $purchaseUnit['payee'] = [
                'email_address' => $this->payeeEmail,
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
        foreach ($order['links'] ?? [] as $link) {
            if (($link['rel'] ?? null) === 'approve') {
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
