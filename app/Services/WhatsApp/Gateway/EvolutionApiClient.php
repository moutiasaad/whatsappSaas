<?php

namespace App\Services\WhatsApp\Gateway;

use Illuminate\Support\Facades\Http;

class EvolutionApiClient implements GatewayClientInterface
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey
    ) {}

    public function createInstance(string $name): array
    {
        return $this->post('/instance/create', ['instanceName' => $name, 'qrcode' => true]);
    }

    public function getQrCode(string $instanceId): ?string
    {
        try {
            $res = $this->get("/instance/connect/{$instanceId}");
            $code = $res['base64'] ?? $res['code'] ?? ($res['qr_code'] ?? null);

            if (is_array($code)) {
                $code = $code['base64'] ?? $code['code'] ?? $code['data'] ?? null;
            }

            if (!is_string($code) || trim($code) === '') {
                return null;
            }

            if (str_starts_with($code, 'data:image/')) {
                return $code;
            }

            $normalized = preg_replace('/\s+/', '', $code);

            return "data:image/png;base64,{$normalized}";
        } catch (\Exception) {
            return null;
        }
    }

    public function getStatus(string $instanceId): string
    {
        try {
            $res = $this->get("/instance/connectionState/{$instanceId}");
            return match($res['instance']['state'] ?? 'close') {
                'open'       => 'connected',
                'connecting' => 'connecting',
                default      => 'disconnected',
            };
        } catch (\Exception) {
            return 'disconnected';
        }
    }

    public function sendText(string $instanceId, string $to, string $body): array
    {
        return $this->post("/message/sendText/{$instanceId}", [
            'number'      => $to,
            'options'     => ['delay' => 1200],
            'textMessage' => ['text' => $body],
        ]);
    }

    public function sendMedia(string $instanceId, string $to, string $url, string $type, ?string $caption = null): array
    {
        return $this->post("/message/sendMedia/{$instanceId}", [
            'number'       => $to,
            'options'      => ['delay' => 1200],
            'mediaMessage' => ['mediatype' => $type, 'url' => $url, 'caption' => $caption],
        ]);
    }

    public function logout(string $instanceId): void
    {
        $this->delete("/instance/logout/{$instanceId}");
    }

    public function restart(string $instanceId): void
    {
        $this->put("/instance/restart/{$instanceId}");
    }

    private function get(string $path): array
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->get(rtrim($this->baseUrl, '/') . $path)
            ->throw()->json();
    }

    private function post(string $path, array $data): array
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->post(rtrim($this->baseUrl, '/') . $path, $data)
            ->throw()->json();
    }

    private function put(string $path): void
    {
        Http::withHeaders(['apikey' => $this->apiKey])
            ->put(rtrim($this->baseUrl, '/') . $path)
            ->throw();
    }

    private function delete(string $path): void
    {
        Http::withHeaders(['apikey' => $this->apiKey])
            ->delete(rtrim($this->baseUrl, '/') . $path)
            ->throw();
    }
}
