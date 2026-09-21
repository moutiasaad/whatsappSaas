<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PayPal Standard Payments — the legacy "email-only" flow.
 *
 * No OAuth. No client id / secret. No REST webhook signing.
 * The merchant is identified purely by PAYPAL_PAYEE_EMAIL.
 *
 * Trade-offs vs the REST Orders v2 flow:
 *   + Zero setup — no PayPal developer app, no webhook registration
 *   + Works with any personal or business PayPal account
 *   - Slightly older UX (server-hosted PayPal checkout page)
 *   - Payment confirmation depends on Instant Payment Notification (IPN)
 *     which must be validated by echoing it back to PayPal
 */
class PayPalStandardService
{
    private string $checkoutUrl;
    private string $ipnValidateUrl;
    private ?string $payeeEmail;
    private string $currency;

    public function __construct()
    {
        $mode = config('services.paypal.mode', 'sandbox');
        $host = $mode === 'live' ? 'www.paypal.com' : 'www.sandbox.paypal.com';

        $this->checkoutUrl    = "https://{$host}/cgi-bin/webscr";
        $this->ipnValidateUrl = "https://ipnpb." . ($mode === 'live' ? 'paypal.com' : 'sandbox.paypal.com') . '/cgi-bin/webscr';
        $this->payeeEmail     = config('services.paypal.payee_email');
        $this->currency       = config('services.paypal.currency', 'USD');
    }

    public function isConfigured(): bool
    {
        return !empty($this->payeeEmail);
    }

    /**
     * Build the query the browser needs to POST to open PayPal-hosted checkout.
     * The caller renders an auto-submitting form pointing at getCheckoutUrl().
     */
    /**
     * @param bool $preferCard Open PayPal on the card form rather than the
     *                         account login. Guest checkout is requested either
     *                         way; this only changes which screen shows first.
     */
    public function buildCheckoutParams(
        float $amount,
        string $itemName,
        string $invoiceId,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl,
        string $customPayload = '',
        bool $preferCard = false,
        ?string $currency = null
    ): array {
        if (!$this->isConfigured()) {
            throw new RuntimeException('PayPal payee email is not configured.');
        }

        return [
            'cmd'           => '_xclick',
            'business'      => $this->payeeEmail,
            'item_name'     => mb_substr($itemName, 0, 127),
            'amount'        => number_format($amount, 2, '.', ''),
            'currency_code' => strtoupper($currency ?: $this->currency),
            'invoice'       => $invoiceId,
            'custom'        => $customPayload,
            'no_shipping'   => '1',
            'no_note'       => '1',
            // 'Sole' is PayPal's flag for guest checkout — a buyer can pay by
            // card without creating an account. Without it the hosted page
            // demands a PayPal login and there is no card option at all.
            //
            // It only takes effect if "PayPal account optional" is ON in the
            // receiving account (Account Settings -> Website payments ->
            // Website preferences). If it is off, PayPal silently ignores this
            // and shows the login screen.
            'solution_type' => 'Sole',
            // Which screen opens first: the card form, or the account login.
            'landing_page'  => $preferCard ? 'Billing' : 'Login',
            'return'        => $returnUrl,
            'cancel_return' => $cancelUrl,
            'notify_url'    => $notifyUrl,
            'rm'            => '2', // POST return with variables
            'charset'       => 'utf-8',
        ];
    }

    public function getCheckoutUrl(): string
    {
        return $this->checkoutUrl;
    }

    /**
     * PayPal IPN handshake: POST the raw payload back to PayPal prefixed with
     * cmd=_notify-validate. PayPal responds VERIFIED or INVALID.
     *
     * https://developer.paypal.com/api/nvp-soap/ipn/IPNIntro/
     */
    public function verifyIpn(string $rawBody): bool
    {
        try {
            $response = Http::asForm()
                ->withHeaders(['User-Agent' => 'wavadesk-ipn/1.0'])
                ->withBody('cmd=_notify-validate&' . $rawBody, 'application/x-www-form-urlencoded')
                ->post($this->ipnValidateUrl);

            $body = trim((string) $response->body());
            return $response->successful() && $body === 'VERIFIED';
        } catch (\Throwable $e) {
            Log::warning('PayPal IPN verify network error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
