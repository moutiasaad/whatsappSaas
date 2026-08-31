<?php

namespace App\Services\AI;

use App\Events\WebChat\WebChatConversationRequested;
use App\Events\WebChat\WebChatMessageSent;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Message;
use Illuminate\Support\Facades\Log;

class WebChatAutoReplyService
{
    public function __construct(private PromptBuilder $promptBuilder) {}

    /**
     * Try to answer a visitor message with Claude. On success, persists the
     * bot reply and fires WebChatMessageSent. On any refusal (mode off,
     * quota out, escalation keyword, empty text, transport error) promotes
     * the conversation to `pending` so a human can pick it up — mirrors
     * the WhatsApp AutoReplyService failure semantics.
     */
    public function maybeReply(Conversation $conversation, Message $incoming): ?Message
    {
        if ($conversation->isClosed()) {
            return null;
        }

        $tenant   = $conversation->tenant;
        $settings = $tenant?->aiSettings;

        if (!$settings || $settings->mode === 'off') {
            $this->promoteToPending($conversation, 'ai_mode_off');
            return null;
        }

        // `suggestion` isn't meaningful for public visitors — nothing would
        // ever deliver the draft. Treat it as off + promote.
        if (!in_array($settings->mode, ['autonomous', 'hybrid'], true)) {
            $this->promoteToPending($conversation, 'ai_mode_' . $settings->mode);
            return null;
        }

        if (!$settings->hasQuota()) {
            $this->promoteToPending($conversation, 'ai_quota_exhausted');
            Log::channel('webchat')->warning('AI quota exhausted', [
                'tenant_id' => $tenant->id,
            ]);
            return null;
        }

        if ($conversation->isAssigned() && !$this->replyWhenClaimed($settings)) {
            return null;
        }

        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            $this->promoteToPending($conversation, 'no_api_key');
            Log::channel('webchat')->error('AI: ANTHROPIC_API_KEY missing');
            return null;
        }

        try {
            $client   = new \Anthropic\Client($apiKey);
            $response = $client->messages()->create([
                'model'      => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => 1024,
                'system'     => $this->promptBuilder->buildSystemPrompt($tenant),
                'messages'   => $this->buildMessages($conversation),
            ]);

            $tokens = ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0);
            if ($tokens > 0) {
                $settings->increment('tokens_used_this_period', $tokens);
            }

            $text = trim($response->content[0]->text ?? '');

            if ($text === '') {
                Log::channel('webchat')->warning('AI: empty response', [
                    'conversation_id' => $conversation->id,
                ]);
                $this->promoteToPending($conversation, 'ai_empty_response');
                return null;
            }

            if ($this->shouldEscalate($text, $settings)) {
                $this->promoteToPending($conversation, 'ai_escalation_keyword');
                return null;
            }

            // Anthropic can take several seconds — an agent may have taken over
            // (assigned) or closed the conversation while we waited. Re-check
            // fresh state and drop the reply if the takeover already happened
            // (unless the tenant opted-in to AI replies on claimed chats).
            $fresh = Conversation::withoutGlobalScope('tenant')->find($conversation->id);
            if (!$fresh || $fresh->isClosed()) {
                Log::channel('webchat')->info('AI: reply discarded — conversation closed mid-call', [
                    'conversation_id' => $conversation->id,
                ]);
                return null;
            }
            if ($fresh->isAssigned() && !$this->replyWhenClaimed($settings)) {
                Log::channel('webchat')->info('AI: reply discarded — agent took over mid-call', [
                    'conversation_id' => $conversation->id,
                    'claimed_by'      => $fresh->claimed_by,
                ]);
                return null;
            }

            Log::channel('webchat')->info('AI: reply generated', [
                'conversation_id' => $conversation->id,
                'tenant_id'       => $tenant->id,
                'tokens'          => $tokens,
                'was_pending'     => $fresh->isPending(),
            ]);

            // If we were rescuing a stale `pending` conversation, drop it back
            // to `bot` now that the AI has answered — otherwise the widget
            // keeps showing "Connecting to a support specialist…" indefinitely.
            // Claimed conversations stay assigned; the AI reply just co-exists.
            if ($fresh->isPending()) {
                $fresh->status = Conversation::STATUS_BOT;
                $fresh->save();
            }

            return $this->persistBotReply($fresh, $text);
        } catch (\Throwable $e) {
            Log::channel('webchat')->error('AI: reply failed', [
                'conversation_id' => $conversation->id,
                'tenant_id'       => $tenant?->id,
                'error'           => $e->getMessage(),
            ]);
            $this->promoteToPending($conversation, 'ai_error');
            return null;
        }
    }

    /**
     * Adapt the webchat message thread to Claude's role-alternating format.
     * Visitor -> user, agent/bot -> assistant. System messages are dropped
     * (they're purely UI hints like "Visitor ended the chat").
     */
    private function buildMessages(Conversation $conversation, int $limit = 20): array
    {
        $raw = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->takeLast($limit)
            ->map(function (Message $msg) {
                if ($msg->isSystem()) {
                    return null;
                }
                $role = $msg->isFromVisitor() ? 'user' : 'assistant';
                return ['role' => $role, 'content' => (string) ($msg->body ?? '')];
            })
            ->filter(fn ($m) => $m && trim($m['content']) !== '')
            ->values()
            ->all();

        $merged = [];
        foreach ($raw as $msg) {
            if (!empty($merged) && $merged[array_key_last($merged)]['role'] === $msg['role']) {
                $merged[array_key_last($merged)]['content'] .= "\n" . $msg['content'];
            } else {
                $merged[] = $msg;
            }
        }

        while (!empty($merged) && $merged[0]['role'] !== 'user') {
            array_shift($merged);
        }

        return $merged;
    }

    private function shouldEscalate(string $text, $settings): bool
    {
        $keywords = $settings->escalation_keywords ?? [];
        foreach ($keywords as $kw) {
            if ($kw && stripos($text, (string) $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    private function replyWhenClaimed($settings): bool
    {
        return (bool) ($settings->reply_when_claimed ?? false);
    }

    private function persistBotReply(Conversation $conversation, string $text): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::SENDER_BOT,
            'sender_id'       => null,
            'body'            => $text,
        ]);

        $conversation->last_activity_at = now();
        $conversation->save();

        rescue(fn () => event(new WebChatMessageSent($message->fresh(['conversation']))));

        return $message;
    }

    private function promoteToPending(Conversation $conversation, string $reason): void
    {
        if (!$conversation->isBot()) {
            return;
        }

        $conversation->status           = Conversation::STATUS_PENDING;
        $conversation->last_activity_at = now();
        $conversation->save();

        Log::channel('webchat')->info('AI: promoted to pending', [
            'conversation_id' => $conversation->id,
            'reason'          => $reason,
        ]);

        rescue(fn () => event(new WebChatConversationRequested($conversation->fresh())));
    }
}
