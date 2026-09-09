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

    /**
     * Persist the send lifecycle marker.
     *
     * messages.status is an ENUM with no 'sending' or 'cancelled' member, and
     * MySQL runs STRICT_TRANS_TABLES here, so writing either raised
     * "1265 Data truncated for column 'status'". That write sits one line
     * before the gateway call, so every AI and agent reply died before it
     * reached WhatsApp while OTP — which calls the gateway directly and never
     * touches this column — kept working.
     *
     * Keep status inside its enum and carry the extra lifecycle state in
     * ai_metadata, a JSON column with no such constraint. The duplicate-send
     * protection PROC-016 added is unchanged; only where the marker lives has
     * moved. Migration 2026_09_09_220000 adds the enum members properly — once
     * it has run, this can move back onto the column.
     */
    private function markSendState(Message $message, string $state): void
    {
        $meta = (array) ($message->ai_metadata ?? []);
        $meta['send_state'] = $state;

        $message->update(['ai_metadata' => $meta]);
    }
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
        // delivered / read) or currently mid-flight / cancelled. A retry firing
        // while the marker reads 'sending' means the previous attempt crashed
        // between the gateway call and the status write — the gateway may
        // already have delivered the message, so re-sending would put a
        // duplicate on the customer's WhatsApp. Manual reconciliation
        // (out of scope) can later promote a stuck 'sending' to sent/failed.
        $sendState = $message->ai_metadata['send_state'] ?? null;

        if (in_array($message->status, ['sent', 'delivered', 'read'], true)
            || in_array($sendState, ['sending', 'cancelled'], true)
        ) {
            Log::channel('whatsapp')->info('SendOutgoingMessage: skipped — already handled', [
                'message_id' => $message->id,
                'status'     => $message->status,
                'send_state' => $sendState,
            ]);
            return;
        }

        // Cancel AI replies whose conversation was taken over (claimed / ai_suspended) or closed
        // between message creation and this job running. Prevents the AI from stealing the last
        // word after an agent grabs the chat.
        if ($message->author_type === 'ai'
            && ($conversation->ai_suspended || $conversation->state === 'claimed' || $conversation->state === 'closed')
        ) {
            $this->markSendState($message, 'cancelled');
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
        // the 'sent' write leaves the marker at 'sending', which the guard
        // above skips on retry — no duplicate on the customer's phone.
        $this->markSendState($message, 'sending');

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

            $meta = (array) ($message->ai_metadata ?? []);
            $meta['send_state'] = 'sent';

            $message->update([
                'status'              => 'sent',
                'external_message_id' => $result['keyId'] ?? $result['id'] ?? data_get($result, 'key.id'),
                'sent_at'             => now(),
                'ai_metadata'         => $meta,
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
