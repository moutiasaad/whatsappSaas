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

    public function __construct(private Message $message) {}

    public function handle(): void
    {
        $conversation = $this->message->conversation;
        $instance     = $conversation->instance;
        $customer     = $conversation->customer;

        try {
            $gateway = new EvolutionApiClient($instance->gateway_url, $instance->gateway_api_key);

            $result = $this->message->type === 'text'
                ? $gateway->sendText($instance->gateway_instance_id, $customer->phone_e164, $this->message->body)
                : $gateway->sendMedia($instance->gateway_instance_id, $customer->phone_e164, $this->message->media_url, $this->message->type);

            $this->message->update([
                'status'              => 'sent',
                'external_message_id' => $result['key']['id'] ?? null,
                'sent_at'             => now(),
            ]);

            broadcast(new MessageSent($this->message->fresh()));

        } catch (\Exception $e) {
            $this->message->update(['status' => 'failed']);
            Log::error("SendOutgoingMessage failed [{$this->message->id}]: {$e->getMessage()}");
            throw $e;
        }
    }
}
