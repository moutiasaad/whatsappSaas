<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\Message;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendOutgoingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $messageId;

    public function __construct(Message $message)
    {
        $this->messageId = $message->id;
    }

    public function handle(): void
    {
        $message      = Message::withoutGlobalScopes()->find($this->messageId);
        $conversation = $message?->conversation()->withoutGlobalScopes()->first();
        $instance     = $conversation?->instance()->withoutGlobalScopes()->first();
        $customer     = $conversation?->customer()->withoutGlobalScopes()->first();

        if (!$message || !$conversation || !$instance || !$customer) {
            Log::channel('whatsapp')->warning('SendOutgoingMessage: skipped — missing relations', [
                'message_id'      => $this->message->id,
                'has_message'     => (bool) $message,
                'has_conversation' => (bool) $conversation,
                'has_instance'    => (bool) $instance,
                'has_customer'    => (bool) $customer,
            ]);
            return;
        }

        Log::channel('whatsapp')->info('SendOutgoingMessage: start', [
            'message_id'          => $message->id,
            'gateway_instance_id' => $instance->gateway_instance_id,
            'gateway_url'         => $instance->effectiveGatewayUrl(),
            'customer_phone'      => $customer->phone_e164,
            'type'                => $message->type,
            'body_preview'        => mb_substr((string) $message->body, 0, 80),
        ]);

        try {
            $gateway = new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());

            if ($message->type === 'text') {
                $result = $gateway->sendText($instance->gateway_instance_id, $customer->phone_e164, $message->body);
            } else {
                $mediaPath = data_get($message->ai_metadata, 'media_path');
                $fileName  = data_get($message->ai_metadata, 'file_name') ?? basename((string) $message->media_url);
                $caption   = $message->body ?: null;

                if ($mediaPath && Storage::disk('public')->exists($mediaPath)) {
                    $result = $gateway->sendMediaFile(
                        $instance->gateway_instance_id,
                        $customer->phone_e164,
                        Storage::disk('public')->path($mediaPath),
                        $fileName,
                        $message->type,
                        $caption,
                    );
                } else {
                    $result = $gateway->sendMedia(
                        $instance->gateway_instance_id,
                        $customer->phone_e164,
                        $message->media_url,
                        $message->type,
                        $caption,
                        $fileName,
                    );
                }
            }

            Log::channel('whatsapp')->info('SendOutgoingMessage: gateway response', [
                'message_id' => $message->id,
                'result'     => $result,
            ]);

            $message->update([
                'status'              => 'sent',
                'external_message_id' => $result['keyId'] ?? $result['id'] ?? data_get($result, 'key.id'),
                'sent_at'             => now(),
            ]);

            try {
                broadcast(new MessageSent($message->fresh()));
            } catch (\Throwable) {}

        } catch (\Exception $e) {
            $message->update(['status' => 'failed']);
            Log::channel('whatsapp')->error('SendOutgoingMessage: failed', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
