<?php

namespace App\Services\AI;

use App\Jobs\SendOutgoingMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class AutoReplyService
{
    public function __construct(private PromptBuilder $promptBuilder) {}

    public function maybeReply(Conversation $conversation, Message $incoming): ?Message
    {
        $settings = $conversation->tenant->aiSettings;

        if (!$settings || $settings->mode === 'off') {
            Log::channel('whatsapp')->debug('AI: skipped — mode off or no settings', [
                'conversation_id' => $conversation->id,
                'mode' => $settings?->mode ?? 'none',
            ]);
            return null;
        }

        if (!$settings->whatsapp_enabled) {
            Log::channel('whatsapp')->debug('AI: skipped — auto-reply disabled for WhatsApp', [
                'conversation_id' => $conversation->id,
            ]);
            return null;
        }

        if (!$conversation->isAiEligible()) {
            Log::channel('whatsapp')->debug('AI: skipped — not eligible', [
                'conversation_id' => $conversation->id,
                'state'           => $conversation->state,
                'ai_suspended'    => $conversation->ai_suspended,
            ]);
            return null;
        }

        // The customer asking for a human is the escalation the keywords are
        // there to catch, and it is worth catching before the API call rather
        // than after: the reply would be billed and then thrown away.
        if ($this->shouldEscalate((string) $incoming->body, $settings)) {
            $this->markEscalated($conversation, 'customer_keyword');
            return null;
        }

        if (!$settings->hasQuota()) {
            $this->disableAndNotify($conversation->tenant);
            return null;
        }

        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            Log::channel('whatsapp')->error('AI: ANTHROPIC_API_KEY is not set in .env — cannot reply', [
                'conversation_id' => $conversation->id,
            ]);
            return null;
        }

        try {
            $client = new \Anthropic\Client($apiKey);

            // anthropic-ai/sdk v0.23 exposes `messages` as a property and
            // create() takes named arguments, not a single payload array.
            $response = $client->messages->create(
                maxTokens: 1024,
                messages: $this->promptBuilder->buildMessages($conversation),
                model: config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                system: $this->promptBuilder->buildSystemPrompt($conversation->tenant),
            );

            // One generated reply = one unit of the plan's monthly allowance.
            // Billed on a successful API call, before the text is inspected:
            // the call was made and paid for either way.
            $settings->consumeReply();

            $tokens = ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0);

            $text = PromptBuilder::sanitizeReply((string) ($response->content[0]->text ?? ''));

            if ($text === '') {
                Log::channel('whatsapp')->warning('AI: empty response', ['conversation_id' => $conversation->id]);
                return null;
            }

            Log::channel('whatsapp')->info('AI: generated reply', [
                'conversation_id' => $conversation->id,
                'mode'            => $settings->mode,
                'tokens'          => $tokens,
            ]);

            if ($this->shouldEscalate($text, $settings)) {
                $this->markEscalated($conversation, 'reply_keyword');
                return null;
            }

            if ($settings->mode === 'suggestion') {
                return $this->storeSuggestion($conversation, $text);
            }

            return $this->sendAutonomously($conversation, $text);

        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('AI: reply failed', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
                'at'              => $e->getFile() . ':' . $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Hand the thread to a human and flag it, so the inbox can badge it as
     * escalated rather than leaving it looking like any other pooled thread.
     *
     * Suspending the AI is what actually stops it answering; escalated_at is
     * the part an agent sees. Left alone if it is already flagged, so the
     * timestamp keeps saying when the escalation began.
     */
    private function markEscalated(Conversation $conversation, string $reason): void
    {
        $updates = ['ai_suspended' => true];

        if ($conversation->escalated_at === null) {
            $updates['escalated_at']      = now();
            $updates['escalation_reason'] = $reason;
        }

        $wasFlagged = $conversation->escalated_at !== null;

        $conversation->update($updates);

        // conversation_events already has an 'escalated' type; recording it puts
        // the handoff on the thread's timeline next to claims and closes.
        if (!$wasFlagged) {
            rescue(fn () => \App\Models\ConversationEvent::create([
                'conversation_id' => $conversation->id,
                'type'            => 'escalated',
                'actor_id'        => null,
                'payload'         => ['reason' => $reason],
                'created_at'      => now(),
            ]));
        }

        Log::channel('whatsapp')->info('AI: escalation keyword detected — suspended', [
            'conversation_id' => $conversation->id,
            'reason'          => $reason,
        ]);
    }

    private function shouldEscalate(string $text, $settings): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $keywords = $settings->escalation_keywords ?? ['manager', 'refund', 'complaint', 'lawsuit'];

        foreach ($keywords as $kw) {
            // An empty keyword makes stripos match every message, which would
            // escalate the entire inbox — the webchat service already guards
            // for this, so guard here too.
            $kw = trim((string) $kw);
            if ($kw !== '' && stripos($text, $kw) !== false) {
                return true;
            }
        }

        return false;
    }

    private function storeSuggestion(Conversation $conversation, string $text): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id'       => $conversation->tenant_id,
            'direction'       => 'out',
            'author_type'     => 'ai',
            'type'            => 'text',
            'body'            => $text,
            'status'          => 'pending',
            'ai_metadata'     => ['mode' => 'suggestion', 'is_suggestion' => true],
        ]);
    }

    private function sendAutonomously(Conversation $conversation, string $text): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id'       => $conversation->tenant_id,
            'direction'       => 'out',
            'author_type'     => 'ai',
            'type'            => 'text',
            'body'            => $text,
            'status'          => 'pending',
            'ai_metadata'     => ['mode' => 'autonomous'],
        ]);

        SendOutgoingMessage::dispatch($message)->onQueue('whatsapp');
        return $message;
    }

    private function disableAndNotify($tenant): void
    {
        $quota = (int) ($tenant->aiSettings?->effectiveQuota() ?? 0);

        $tenant->aiSettings()->update(['mode' => 'off']);

        // Previously this only wrote a log line, so the tenant was never told the
        // AI had stopped answering their customers.
        $tenant->users()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get()
            ->each(function ($admin) use ($tenant, $quota) {
                \App\Models\AppNotification::create([
                    'tenant_id' => $tenant->id,
                    'user_id'   => $admin->id,
                    // app_notifications.type is enum('manual','renewal','system');
                    // the specific event lives in data.event.
                    'type'      => 'system',
                    'title'     => __('ui.notification_messages.ai_quota_exhausted_title'),
                    'body'      => __('ui.notification_messages.ai_quota_exhausted_body'),
                    'data'      => [
                        'event' => 'ai.quota_exhausted',
                        'quota' => $quota,
                    ],
                ]);
            });

        Log::warning("AI disabled for tenant {$tenant->id}: quota exhausted");
    }
}
