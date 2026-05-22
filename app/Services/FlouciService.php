<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlouciService
{
    private string $baseUrl;
    private string $publicToken;
    private string $privateToken;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('flouci.base_url', 'https://developers.flouci.com/api/v2'), '/');
        $this->publicToken  = config('flouci.public_token', '');
        $this->privateToken = config('flouci.private_token', '');
    }

    public function initPayment(array $params): array
    {
        $payload = [
            'amount'                 => (string) ($params['amount_millimes'] ?? 0),
            'developer_tracking_id'  => $params['order_id'] ?? null,
            'accept_card'            => (bool) ($params['accept_card'] ?? true),
            'success_link'           => config('flouci.success_url') ?? route('payment.success'),
            'fail_link'              => config('flouci.fail_url') ?? route('payment.failed'),
            'webhook'                => config('flouci.webhook_url') ?? route('payment.webhook'),
            'client_id'              => $params['client_id'] ?? trim(($params['first_name'] ?? '') . ' ' . ($params['last_name'] ?? '')),
            'session_timeout_secs'   => (int) ($params['session_timeout_secs'] ?? 1200),
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->authorizationHeader(),
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/generate_payment", $payload);

        if ($response->failed()) {
            Log::error('Flouci initPayment failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Échec de l\'initialisation du paiement Flouci : ' . $response->body());
        }

        $data   = $response->json();
        $result = $data['result'] ?? [];

        $hasExplicitSuccessFlag = array_key_exists('success', $result);
        $isSuccess = !$hasExplicitSuccessFlag || $result['success'] === true;

        if (!$isSuccess || empty($result['payment_id']) || empty($result['link'])) {
            Log::error('Flouci initPayment unexpected payload', ['payload' => $data]);
            throw new \RuntimeException('Réponse Flouci invalide pendant l\'initialisation du paiement.');
        }

        return [
            'paymentId' => $result['payment_id'],
            'payUrl'    => $result['link'],
            'raw'       => $data,
        ];
    }

    public function getPayment(string $paymentId): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->authorizationHeader(),
        ])->get("{$this->baseUrl}/verify_payment/{$paymentId}");

        if ($response->failed()) {
            Log::error('Flouci getPayment failed', [
                'payment_id' => $paymentId,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            throw new \RuntimeException('Impossible de récupérer le paiement Flouci.');
        }

        return $response->json();
    }

    public function isCompleted(array $flouciPayment): bool
    {
        if (($flouciPayment['success'] ?? false) !== true) {
            return false;
        }

        $status = strtoupper((string) ($flouciPayment['result']['status'] ?? ''));

        return $status === 'SUCCESS';
    }

    private function authorizationHeader(): string
    {
        return 'Bearer ' . $this->publicToken . ':' . $this->privateToken;
    }
}
