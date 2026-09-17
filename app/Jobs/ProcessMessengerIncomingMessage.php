<?php

namespace App\Jobs;

use App\Events\Messenger\MessengerMessageSent;
use App\Models\Messenger\Conversation;
use App\Models\Messenger\Message;
use App\Models\Messenger\Page;
use App\Services\Messenger\MessengerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ProcessMessengerIncomingMessage
 *
 * Consumes one Meta webhook `entry` payload and persists what it
 * carries. Meta groups events per Page, so a single entry can contain
 * several `messaging[]` items — a message, a delivery watermark, a
 * read receipt, a postback — for possibly different users.
 *
 * Idempotency lives in the messenger_messages.external_id unique index:
 * a duplicate `mid` (from a Meta redelivery) upserts to the same row
 * instead of creating a second one.
 *
 * Broadcasting + AI auto-reply hook are wired in the same session's
 * later phases (6 and 7). Phase 3 just gets the data on disk.
 */
class ProcessMessengerIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;

    /**
     * @param  int   $pageId  messenger_pages.id (NOT Meta's page_id)
     * @param  array $entry   one item from webhook payload['entry']
     */
    public function __construct(public int $pageId, public array $entry) {}

    public function handle(): void
    {
        $page = Page::withoutGlobalScope('tenant')->find($this->pageId);
        if (! $page) {
            Log::channel('messenger')->warning('Job: page vanished before processing', [
                'page_id' => $this->pageId,
            ]);
            return;
        }

        foreach ($this->entry['messaging'] ?? [] as $event) {
            try {
                $this->handleEvent($page, $event);
            } catch (\Throwable $e) {
                // One bad event doesn't drop the batch. Meta's retries
                // would just re-send everything.
                Log::channel('messenger')->error('Job: event handler threw', [
                    'page_id' => $this->pageId,
                    'event'   => $event,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    private function handleEvent(Page $page, array $event): void
    {
        // is_echo means the message was sent from Meta's own Page inbox
        // (a human replying outside Wavadesk). We store it as an
        // outbound so the timeline stays honest, but don't dispatch AI
        // and don't broadcast to the visitor.
        $isEcho = (bool) ($event['message']['is_echo'] ?? false);

        // Non-message events (delivery, read, postback) are dropped for
        // now. Phase 6 will wire delivery + read watermarks to update
        // messenger_messages.read_at / meta.delivered_at.
        if (! isset($event['message'])) {
            return;
        }

        $psid = $isEcho
            ? (string) ($event['recipient']['id'] ?? '')
            : (string) ($event['sender']['id'] ?? '');

        if ($psid === '') {
            return;
        }

        $conversation = Conversation::withoutGlobalScope('tenant')
            ->firstOrCreate(
                ['page_id' => $page->id, 'psid' => $psid],
                ['tenant_id' => $page->tenant_id]
            );

        // Fill contact_name + contact_avatar_url on first sight of a new PSID
        // (or backfill an old row whose profile was never fetched). Meta's
        // profile_pic URLs expire, so the same call is worth repeating
        // periodically — 7 days is a safe default. Rescued because the
        // profile call is cosmetic; a failure must not drop the message.
        $needsProfile = $conversation->wasRecentlyCreated
            || $conversation->contact_name === null
            || (
                $conversation->contact_profile_refreshed_at !== null
                && $conversation->contact_profile_refreshed_at->lt(now()->subDays(7))
            );
        if ($needsProfile) {
            rescue(fn () => app(MessengerService::class)->refreshContactProfile($conversation->id));
        }

        $mid       = (string) ($event['message']['mid'] ?? '');
        $text      = $event['message']['text'] ?? null;
        $attachs   = $event['message']['attachments'] ?? null;
        $timestamp = isset($event['timestamp'])
            ? Carbon::createFromTimestampMs((int) $event['timestamp'])
            : now();

        // firstOrCreate on external_id is the dedup guard. Meta retries
        // deliver the same mid; this collapses them to one row.
        $message = Message::firstOrCreate(
            ['external_id' => $mid !== '' ? $mid : null],
            [
                'conversation_id' => $conversation->id,
                'sender_type'     => $isEcho ? Message::SENDER_AGENT : Message::SENDER_VISITOR,
                'body'            => $text,
                'attachments'     => $attachs,
                'meta'            => array_filter([
                    'timestamp'      => $timestamp->toIso8601String(),
                    'is_echo'        => $isEcho ?: null,
                    'reply_to'       => $event['message']['reply_to']['mid'] ?? null,
                    'quick_reply'    => $event['message']['quick_reply']['payload'] ?? null,
                    'app_id'         => $event['message']['app_id'] ?? null,
                ]),
                'created_at'      => $timestamp,
                'updated_at'      => $timestamp,
            ]
        );

        // Bookkeeping that drives the inbox + 24h messaging-window check.
        // Only update on genuine inbound — an echo (agent-sent) doesn't
        // reset the visitor's clock.
        if (! $isEcho) {
            $conversation->last_inbound_at  = $timestamp;
            $conversation->last_activity_at = $timestamp;
            $conversation->save();
        } else {
            $conversation->last_activity_at = $timestamp;
            $conversation->save();
        }

        Log::channel('messenger')->info('Message ingested', [
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'sender'          => $isEcho ? 'agent' : 'visitor',
            'mid'             => $mid,
        ]);

        // AI dispatch: only on fresh visitor messages (an echo is us or
        // an off-platform agent, and re-processed retries reuse the
        // same row so wasRecentlyCreated=false → don't double-answer).
        if (! $isEcho && $message->wasRecentlyCreated) {
            ProcessMessengerAiReply::dispatch($conversation->id, $message->id);
        }

        // Broadcast to the agent inbox in real time. Only fresh rows —
        // a redelivered mid firstOrCreate'd back an existing row and
        // rebroadcasting would just double-render the same bubble.
        // Rescue: a Reverb outage must not fail the ingest job.
        if ($message->wasRecentlyCreated) {
            rescue(fn () => event(new MessengerMessageSent($message->fresh(['conversation']))));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('messenger')->error('Job: permanent failure', [
            'page_id' => $this->pageId,
            'entry'   => $this->entry,
            'error'   => $e->getMessage(),
        ]);
    }
}
