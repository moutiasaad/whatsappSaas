<?php

namespace App\Services\WhatsApp\Gateway;

use Illuminate\Support\Facades\Http;

class EvolutionApiClient implements GatewayClientInterface
{
    /**
     * /instance/connect long-polls on the iStoreBox build. Cap it so a pairing
     * session that is still warming up cannot hold a PHP-FPM worker for the
     * HTTP client's 30s default.
     */
    private const QR_REQUEST_TIMEOUT = 8;

    public function __construct(
        private string $baseUrl,
        private string $apiKey
    ) {}

    public function createInstance(string $name): array
    {
        return $this->post('/instance/create', ['instanceName' => $name]);
    }

    /**
     * Ask the gateway to start (or resume) pairing, returning the QR when the
     * response happens to carry it.
     *
     * On the production iStoreBox build /instance/connect does not reliably
     * return the QR: for a fresh instance it long-polls past 30s, and for an
     * existing one it answers {"count":0}. The QR is always delivered
     * out-of-band by the qrcode.updated webhook instead. This call is therefore
     * made mostly for its side effect — it is what triggers QR generation — so a
     * timeout means "no QR in the body yet", not a failure.
     */
    public function getQrCode(string $instanceId): ?string
    {
        try {
            $res = Http::withHeaders(['apikey' => $this->apiKey])
                ->timeout(self::QR_REQUEST_TIMEOUT)
                ->get(rtrim($this->baseUrl, '/') . "/instance/connect/{$instanceId}")
                ->throw()->json();
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
                'open', 'online', 'connected'                    => 'connected',
                'connecting', 'pairing', 'pending', 'qr', 'qrcode' => 'connecting',
                'close', 'closed', 'offline', 'disconnected'    => 'disconnected',
                default                                          => 'disconnected',
            };
        } catch (\Exception) {
            return 'disconnected';
        }
    }

    public function fetchInstance(string $instanceId): array
    {
        return $this->get("/instance/fetchInstance/{$instanceId}");
    }

    public function setWebhook(string $instanceId, string $url, array $events = [], ?string $hmacSecret = null): array
    {
        $payload = [
            'enabled' => true,
            'url'     => $url,
            'events'  => $events ?: $this->defaultWebhookEvents(),
        ];

        // Advertise the HMAC secret to the gateway under the field names various
        // Evolution / CodeChat forks use. Unknown fields are ignored, so this
        // is safe against every version — if the gateway supports one of these
        // names it will sign each webhook post with an X-Gateway-Signature
        // header the receiver can verify (PROC-018).
        if ($hmacSecret) {
            $payload['hmac_secret']    = $hmacSecret;
            $payload['webhook_secret'] = $hmacSecret;
            $payload['secret']         = $hmacSecret;
        }

        return $this->putJson("/webhook/set/{$instanceId}", $payload);
    }

    public function findContacts(string $instanceId, string $remoteJid): array
    {
        try {
            $result = $this->post("/chat/findContacts/{$instanceId}", [
                'where' => ['remoteJid' => $remoteJid],
            ]);
            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function checkNumbers(string $instanceId, array $numbers): array
    {
        $normalized = array_values(array_filter(array_map(function (string $n): ?string {
            $digits = preg_replace('/\D+/', '', $n);
            return $digits !== '' ? $digits : null;
        }, $numbers)));

        if (empty($normalized)) {
            return [];
        }

        return $this->post("/chat/whatsappNumbers/{$instanceId}", ['numbers' => $normalized]);
    }

    public function sendText(string $instanceId, string $to, string $body): array
    {
        return $this->post("/message/sendText/{$instanceId}", [
            'number'      => $this->normalizeRecipient($to),
            'options'     => [
                'delay'    => 1200,
                'presence' => 'composing',
            ],
            'textMessage' => ['text' => $body],
        ]);
    }

    public function sendList(string $instanceId, string $to, string $title, string $description, string $buttonText, array $sections): array
    {
        // CodeChat / Evolution API v1 uses sendListMessage; v2 uses sendList.
        // We try v1 first. If the gateway returns 4xx the caller can fall back to text.
        return $this->post("/message/sendListMessage/{$instanceId}", [
            'number'      => $this->normalizeRecipient($to),
            'options'     => ['delay' => 1200, 'presence' => 'composing'],
            'listMessage' => [
                'title'       => $title,
                'description' => $description,
                'buttonText'  => $buttonText,
                'footerText'  => 'أرسل إلغاء للإلغاء',
                'sections'    => $sections,
            ],
        ]);
    }

    /**
     * Send native-flow interactive quick-reply buttons (max 3). Unlike sendListMessage
     * (legacy listMessage format, deprecated by WhatsApp for Baileys gateways), the gateway
     * renders these via the modern interactiveMessage/nativeFlowMessage path, which still
     * delivers as tappable buttons on current WhatsApp clients.
     *
     * @param array<int,array{id:string,text:string}> $buttons
     */
    public function sendButtons(string $instanceId, string $to, string $title, string $description, array $buttons, string $footer = ''): array
    {
        return $this->post("/message/sendButtons/{$instanceId}", [
            'number'         => $this->normalizeRecipient($to),
            'options'        => ['delay' => 1200, 'presence' => 'composing'],
            'buttonsMessage' => [
                'title'       => $title,
                'description' => $description,
                'footer'      => $footer,
                'buttons'     => array_map(
                    fn($b) => ['type' => 'reply', 'displayText' => $b['text'], 'id' => $b['id']],
                    $buttons
                ),
            ],
        ]);
    }

    public function sendMedia(string $instanceId, string $to, string $url, string $type, ?string $caption = null, ?string $fileName = null): array
    {
        $mediaMessage = ['mediatype' => $type, 'media' => $url];
        if ($caption !== null && $caption !== '') {
            $mediaMessage['caption'] = $caption;
        }
        if ($fileName !== null && $fileName !== '') {
            $mediaMessage['fileName'] = $fileName;
        }

        return $this->post("/message/sendMedia/{$instanceId}", [
            'number'       => $this->normalizeRecipient($to),
            'options'      => [
                'delay'    => 1200,
                'presence' => $type === 'audio' ? 'recording' : 'composing',
            ],
            'mediaMessage' => $mediaMessage,
        ]);
    }

    public function sendMediaFile(string $instanceId, string $to, string $filePath, string $fileName, string $type, ?string $caption = null): array
    {
        $presence = $type === 'audio' ? 'recording' : 'composing';

        $request = Http::withHeaders(['apikey' => $this->apiKey])
            ->attach('attachment', file_get_contents($filePath), $fileName);

        $formData = [
            'number'    => $this->normalizeRecipient($to),
            'mediatype' => $type,
            'delay'     => '1200',
            'presence'  => $presence,
        ];
        if ($caption !== null && $caption !== '') {
            $formData['caption'] = $caption;
        }

        return $request
            ->post(rtrim($this->baseUrl, '/') . "/message/sendMediaFile/{$instanceId}", $formData)
            ->throw()
            ->json();
    }

    public function markMessagesRead(string $instanceId, array $ids): array
    {
        $numericIds = array_values(array_filter(
            array_map(fn($id) => is_numeric($id) ? (int) $id : null, $ids),
            fn($id) => $id !== null
        ));

        if (empty($numericIds)) {
            return ['message' => 'No numeric ids', 'read' => 'skipped'];
        }

        return $this->patch("/chat/readMessages/{$instanceId}", ['ids' => $numericIds]);
    }

    public function updatePresence(string $instanceId, string $number, string $presence): void
    {
        $this->patch("/chat/updatePresence/{$instanceId}", [
            'number'   => $this->normalizeRecipient($number),
            'presence' => $presence,
        ]);
    }

    public function logout(string $instanceId): void
    {
        $this->delete("/instance/delete/{$instanceId}");
    }

    public function deleteInstance(string $instanceId): void
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

        $digits = preg_replace('/\D+/', '', $to) ?? '';

        if ($digits === '') {
            throw new \InvalidArgumentException("Cannot send to non-numeric recipient: {$to}");
        }

        return "{$digits}@s.whatsapp.net";
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
