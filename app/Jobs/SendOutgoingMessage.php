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
                'message_id'       => $this->messageId,
                'has_message'      => (bool) $message,
                'has_conversation' => (bool) $conversation,
                'has_instance'     => (bool) $instance,
                'has_customer'     => (bool) $customer,
            ]);
            return;
        }

        // Idempotency guard: skip any row that is already terminal (sent /
        // delivered / cancelled) or currently mid-flight (sending). A retry
        // firing while status is 'sending' means the previous attempt crashed
        // between the gateway call and the status write — the gateway may
        // already have delivered the message, so re-sending would put a
        // duplicate on the customer's WhatsApp. Manual reconciliation
        // (out of scope) can later promote a stuck 'sending' to sent/failed.
        if (in_array($message->status, ['sent', 'delivered', 'cancelled', 'sending'], true)) {
            Log::channel('whatsapp')->info('SendOutgoingMessage: skipped — already handled', [
                'message_id' => $message->id,
                'status'     => $message->status,
            ]);
            return;
        }

        // Cancel AI replies whose conversation was taken over (claimed / ai_suspended) or closed
        // between message creation and this job running. Prevents the AI from stealing the last
        // word after an agent grabs the chat.
        if ($message->author_type === 'ai'
            && ($conversation->ai_suspended || $conversation->state === 'claimed' || $conversation->state === 'closed')
        ) {
            $message->update(['status' => 'cancelled']);
            Log::channel('whatsapp')->info('SendOutgoingMessage: AI reply cancelled — conversation taken over', [
                'message_id'     => $message->id,
                'conversation'   => $conversation->id,
                'state'          => $conversation->state,
                'ai_suspended'   => (bool) $conversation->ai_suspended,
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

        // Reserve the row before the network call. A crash between here and
        // the 'sent' write leaves the row at 'sending', which the guard above
        // skips on retry — no duplicate on the customer's phone.
        $message->update(['status' => 'sending']);

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
            // Do NOT flip the row back to 'failed' and re-throw — an exception
            // can fire AFTER the gateway accepted the message (e.g. read
            // timeout on the response body), and the Laravel retry would
            // then re-send. Leave the row at 'sending' so the guard above
            // makes any retry a no-op. Operators can requeue by resetting
            // the row to 'pending' after confirming non-delivery with the
            // gateway.
            Log::channel('whatsapp')->error('SendOutgoingMessage: send exception — row left as sending for reconciliation', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
