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

class SendOutgoingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private Message $message)
    {
        $this->onQueue('whatsapp');
    }

    public function handle(): void
    {
        $conversation = $this->message->conversation;
        $instance     = $conversation->instance;
        $customer     = $conversation->customer;

        Log::channel('whatsapp')->info('SendOutgoingMessage: start', [
            'message_id'          => $this->message->id,
            'gateway_instance_id' => $instance->gateway_instance_id,
            'gateway_url'         => $instance->effectiveGatewayUrl(),
            'customer_phone'      => $customer->phone_e164,
            'type'                => $this->message->type,
            'body_preview'        => mb_substr((string) $this->message->body, 0, 80),
        ]);

        try {
            $gateway = new EvolutionApiClient($instance->effectiveGatewayUrl(), $instance->effectiveGatewayApiKey());

            $result = $this->message->type === 'text'
                ? $gateway->sendText($instance->gateway_instance_id, $customer->phone_e164, $this->message->body)
                : $gateway->sendMedia($instance->gateway_instance_id, $customer->phone_e164, $this->message->media_url, $this->message->type);

            Log::channel('whatsapp')->info('SendOutgoingMessage: gateway response', [
                'message_id' => $this->message->id,
                'result'     => $result,
            ]);

            $this->message->update([
                'status'              => 'sent',
                'external_message_id' => $result['keyId'] ?? $result['id'] ?? data_get($result, 'key.id'),
                'sent_at'             => now(),
            ]);

            broadcast(new MessageSent($this->message->fresh()));

        } catch (\Exception $e) {
            $this->message->update(['status' => 'failed']);
            Log::channel('whatsapp')->error('SendOutgoingMessage: failed', [
                'message_id' => $this->message->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
