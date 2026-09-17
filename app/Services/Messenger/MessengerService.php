<?php

namespace App\Services\Messenger;

use App\Models\Messenger\Conversation;
use App\Models\Messenger\Message;
use App\Models\Messenger\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MessengerService
 *
 * High-level Messenger operations used by the reply controller and the
 * AI auto-reply worker. Wraps GraphApiClient with the business rules:
 *
 *   - 24-hour messaging-window enforcement (Meta rejects outside
 *     without a MESSAGE_TAG; we check first to give the composer a
 *     read-only hint before the request is even attempted).
 *
 *   - Token invalidation handling: Graph error 190 marks the Page
 *     disconnected so the tenant sees why the channel stopped instead
 *     of silently failing.
 *
 *   - Persistence: every outbound message gets a messenger_messages
 *     row with the Meta mid on external_id (unique-indexed) so a retry
 *     never duplicates.
 */
class MessengerService
{
    public function __construct(private GraphApiClient $graph) {}

    /**
     * Send a text reply on a conversation.
     *
     * @param  int          $conversationId  messenger_conversations.id
     * @param  string       $body            reply text
     * @param  int|null     $senderUserId    Wavadesk user id (agent) — null for bot
     * @param  string       $senderType      Message::SENDER_AGENT | SENDER_BOT
     * @return Message                       persisted outbound row
     *
     * @throws MessengerSendException on any Meta rejection or
     *         business-rule failure. Callers should log + surface to
     *         the agent instead of retrying blindly (a 24h-window
     *         failure won't be fixed by a retry).
     */
    public function sendText(
        int $conversationId,
        string $body,
        ?int $senderUserId = null,
        string $senderType = Message::SENDER_AGENT
    ): Message {
        $body = trim($body);
        if ($body === '') {
            throw new MessengerSendException('empty_body', 'Cannot send an empty message.');
        }

        $conversation = Conversation::withoutGlobalScope('tenant')->find($conversationId);
        if (! $conversation) {
            throw new MessengerSendException('conversation_not_found', "Conversation {$conversationId} not found.");
        }

        $page = Page::withoutGlobalScope('tenant')->find($conversation->page_id);
        if (! $page || ! $page->enabled || $page->disconnected_at !== null) {
            throw new MessengerSendException(
                'page_disconnected',
                'The Facebook Page is not connected. Reconnect it in Settings.'
            );
        }

        if (! $conversation->withinMessagingWindow()) {
            // Outside 24h without a MESSAGE_TAG. Meta would 400 with
            // code 10; we short-circuit here so the agent gets a
            // human-readable error instead.
            throw new MessengerSendException(
                'outside_messaging_window',
                'This conversation is outside the 24-hour messaging window. Ask the customer to send a new message first.'
            );
        }

        $result = $this->graph->sendText($page->access_token, $conversation->psid, $body);

        if (! $result['ok']) {
            $err = $result['error'] ?? ['code' => 0, 'message' => 'Unknown'];

            // Token invalidated — mark the Page disconnected so the
            // tenant sees a clear "reconnect" prompt on next inbox load
            // instead of every reply silently failing.
            if (($err['code'] ?? 0) === 190) {
                $page->disconnected_at   = now();
                $page->disconnect_reason = 'token_invalidated_190';
                $page->save();
                Log::channel('messenger')->warning('Page marked disconnected: token 190', [
                    'page_id'         => $page->page_id,
                    'conversation_id' => $conversationId,
                    'subcode'         => $err['subcode'] ?? null,
                ]);
            }

            Log::channel('messenger')->warning('Send failed', [
                'conversation_id' => $conversationId,
                'error'           => $err,
                'status'          => $result['status'] ?? null,
            ]);

            throw new MessengerSendException(
                'meta_rejected',
                "Meta rejected the send ({$err['code']}): {$err['message']}"
            );
        }

        // Success — persist the outbound row. firstOrCreate on the
        // mid guards against a duplicate if this method is somehow
        // called twice for the same reply (retry job, double-click).
        $message = Message::firstOrCreate(
            ['external_id' => $result['mid']],
            [
                'conversation_id' => $conversation->id,
                'sender_type'     => $senderType,
                'sender_id'       => $senderUserId,
                'body'            => $body,
                'attachments'     => null,
                'meta'            => [
                    'messaging_type' => 'RESPONSE',
                    'sent_at'        => now()->toIso8601String(),
                ],
            ]
        );

        // Bookkeeping — agent reply resets last_activity_at (but NOT
        // last_inbound_at; the 24h window is measured from the customer's
        // last message, not the agent's response).
        $conversation->last_activity_at = now();
        $conversation->save();

        Log::channel('messenger')->info('Outbound sent', [
            'conversation_id' => $conversationId,
            'message_id'      => $message->id,
            'mid'             => $result['mid'],
            'sender_type'     => $senderType,
        ]);

        return $message;
    }

    /**
     * Refresh the visitor profile snapshot (name, avatar) on a
     * conversation. Called lazily on first sight of a new PSID and
     * periodically because profile_pic URLs expire.
     */
    public function refreshContactProfile(int $conversationId): ?array
    {
        $conversation = Conversation::withoutGlobalScope('tenant')->find($conversationId);
        if (! $conversation) return null;

        $page = Page::withoutGlobalScope('tenant')->find($conversation->page_id);
        if (! $page || ! $page->enabled) return null;

        $result = $this->graph->getUserProfile($page->access_token, $conversation->psid);
        if (! $result['ok']) {
            Log::channel('messenger')->info('Profile refresh failed', [
                'conversation_id' => $conversationId,
                'error'           => $result['error'] ?? null,
            ]);
            return null;
        }

        $profile = $result['profile'];
        $name    = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));

        $conversation->contact_name                  = $name !== '' ? $name : $conversation->contact_name;
        $conversation->contact_avatar_url            = $profile['profile_pic'] ?? $conversation->contact_avatar_url;
        $conversation->contact_profile_refreshed_at  = now();
        $conversation->save();

        return $profile;
    }
}
