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
        return $this->post('/instance/create', ['instanceName' => $name]);
    }

    public function getQrCode(string $instanceId): ?string
    {
        try {
            $res = $this->get("/instance/connect/{$instanceId}");
            $code = data_get($res, 'base64')
                ?? data_get($res, 'data.base64')
                ?? data_get($res, 'qr_code')
                ?? data_get($res, 'code');

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
            $res = $this->fetchInstance($instanceId);
            $rawStatus = strtolower((string) (
                data_get($res, 'connectionStatus')
                ?? data_get($res, 'status')
                ?? data_get($res, 'instance.connectionStatus')
                ?? data_get($res, 'state')
                ?? data_get($res, 'instance.state')
                ?? 'close'
            ));

            return match ($rawStatus) {
                'open', 'online', 'connected' => 'connected',
                'connecting', 'pairing', 'pending' => 'connecting',
                'close', 'closed', 'offline', 'disconnected' => 'disconnected',
                default => 'disconnected',
            };
        } catch (\Exception) {
            return 'disconnected';
        }
    }

    public function fetchInstance(string $instanceId): array
    {
        return $this->get("/instance/fetchInstance/{$instanceId}");
    }

    public function setWebhook(string $instanceId, string $url, array $events = []): array
    {
        return $this->putJson("/webhook/set/{$instanceId}", [
            'enabled' => true,
            'url'     => $url,
            'events'  => $events ?: $this->defaultWebhookEvents(),
        ]);
    }

    public function sendText(string $instanceId, string $to, string $body): array
    {
        return $this->post("/message/sendText/{$instanceId}", [
            'number'      => $this->normalizeRecipient($to),
            'options'     => ['delay' => 1200],
            'textMessage' => ['text' => $body],
        ]);
    }

    public function sendMedia(string $instanceId, string $to, string $url, string $type, ?string $caption = null): array
    {
        return $this->post("/message/sendMedia/{$instanceId}", [
            'number'       => $this->normalizeRecipient($to),
            'options'      => ['delay' => 1200],
            'mediaMessage' => ['mediatype' => $type, 'media' => $url, 'caption' => $caption],
        ]);
    }

    public function markMessagesRead(string $instanceId, array $ids): array
    {
        return $this->patch("/chat/readMessages/{$instanceId}", ['ids' => $ids]);
    }

    public function logout(string $instanceId): void
    {
        $this->delete("/instance/delete/{$instanceId}");
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

    private function patch(string $path, array $data): array
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->patch(rtrim($this->baseUrl, '/') . $path, $data)
            ->throw()->json();
    }

    private function putJson(string $path, array $data): array
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->put(rtrim($this->baseUrl, '/') . $path, $data)
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

    private function normalizeRecipient(string $to): string
    {
        $to = trim($to);

        if (str_contains($to, '@')) {
            return $to;
        }

        $digits = preg_replace('/\D+/', '', $to) ?: $to;

        return str_contains($digits, '@') ? $digits : "{$digits}@s.whatsapp.net";
    }

    private function defaultWebhookEvents(): array
    {
        return [
            'qrcodeUpdated' => true,
            'messagesSet' => false,
            'messagesUpsert' => true,
            'messagesUpdated' => true,
            'sendMessage' => true,
            'contactsSet' => true,
            'contactsUpsert' => true,
            'contactsUpdated' => true,
            'chatsSet' => false,
            'chatsUpsert' => true,
            'chatsUpdated' => true,
            'chatsDeleted' => true,
            'presenceUpdated' => true,
            'groupsUpsert' => true,
            'groupsUpdated' => true,
            'groupsParticipantsUpdated' => true,
            'connectionUpdated' => true,
            'statusInstance' => true,
            'refreshToken' => true,
        ];
    }
}
