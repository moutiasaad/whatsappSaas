<?php

namespace App\Services\WhatsApp\Gateway;

interface GatewayClientInterface
{
    public function createInstance(string $name): array;
    public function getQrCode(string $instanceId): ?string;
    public function getStatus(string $instanceId): string;
    public function sendText(string $instanceId, string $to, string $body): array;
    public function sendMedia(string $instanceId, string $to, string $url, string $type, ?string $caption = null): array;
    public function logout(string $instanceId): void;
    public function restart(string $instanceId): void;
}
