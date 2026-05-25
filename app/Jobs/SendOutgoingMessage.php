<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOutgoingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private Message $message) {}

    public function handle(): void
    {
        $message      = Message::withoutGlobalScopes()->find($this->message->id);
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

            $result = $message->type === 'text'
                ? $gateway->sendText($instance->gateway_instance_id, $customer->phone_e164, $message->body)
                : $gateway->sendMedia($instance->gateway_instance_id, $customer->phone_e164, $message->media_url, $message->type);

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
