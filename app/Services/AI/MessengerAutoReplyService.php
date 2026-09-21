<?php

namespace App\Services\AI;

use App\Models\Messenger\Conversation;
use App\Models\Messenger\Message;
use App\Services\AI\UsageTracker;
use App\Services\Messenger\MessengerSendException;
use App\Services\Messenger\MessengerService;
use Illuminate\Support\Facades\Log;

/**
 * MessengerAutoReplyService
 *
 * Answers Messenger visitor messages with Claude, then hands the
 * reply back through MessengerService::sendText — which persists the
 * outbound row AND POSTs it to Meta's Send API. That is the crucial
 * difference from WebChatAutoReplyService: on webchat the bot reply
 * lives entirely inside our DB; on Messenger every bot reply must
 * traverse Meta before it can reach the visitor.
 *
 * Structure otherwise mirrors WebChatAutoReplyService exactly — same
 * refusal semantics, same `promoteToPending` failure path, same
 * mid-call takeover guards. Kept aligned on purpose so future
 * PromptBuilder / quota / escalation changes ripple through all three
 * channels the same way.
 */
class MessengerAutoReplyService
{
    public function __construct(
        private PromptBuilder $promptBuilder,
        private MessengerService $messenger,
    ) {}

    public function maybeReply(Conversation $conversation, Message $incoming): ?Message
    {
        if ($conversation->isClosed()) {
            return null;
        }

        $tenant = $conversation->tenant;

        // Blocked/archived/expired tenant → don't answer on their behalf,
        // but still promote to pending so a restore later doesn't lose
        // the visitor question.
        if ($tenant && ! $tenant->canRunAutomations()) {
            Log::channel('messenger')->info('AI: skipped — tenant not eligible for automations', [
                'tenant_id'           => $tenant->id,
                'is_active'           => (bool) $tenant->is_active,
                'archived'            => $tenant->isArchived(),
                'subscription_status' => $tenant->subscription_status,
                'conversation_id'     => $conversation->id,
            ]);
            $this->promoteToPending($conversation, 'tenant_ineligible');
            return null;
        }

        $settings = $tenant?->aiSettings;

        if (! $settings || $settings->mode === 'off') {
            $this->promoteToPending($conversation, 'ai_mode_off');
            return null;
        }

        if (! $settings->enabledFor('messenger')) {
            $this->promoteToPending($conversation, 'ai_disabled_for_messenger');
            return null;
        }

        // `suggestion` mode has no delivery mechanism for a Messenger visitor
        // — the draft would sit in our DB, and the person on the phone would
        // never see it. Treat as off.
        if (! in_array($settings->mode, ['autonomous', 'hybrid'], true)) {
            $this->promoteToPending($conversation, 'ai_mode_' . $settings->mode);
            return null;
        }

        if ($this->shouldEscalate((string) $incoming->body, $settings)) {
            $this->promoteToPending($conversation, 'ai_escalation_keyword', 'customer_keyword');
            return null;
        }

        if (! $settings->hasQuota()) {
            $this->promoteToPending($conversation, 'ai_quota_exhausted');
            Log::channel('messenger')->warning('AI quota exhausted', [
                'tenant_id' => $tenant->id,
            ]);
            return null;
        }

        if ($conversation->isAssigned() && ! $this->replyWhenClaimed($settings)) {
            return null;
        }

        // 24-hour window check — no point calling Claude if Meta will
        // reject the send anyway. MessengerService::sendText re-checks
        // as belt-and-braces, so we can't send outside the window even
        // if this early guard has a bug.
        if (! $conversation->withinMessagingWindow()) {
            $this->promoteToPending($conversation, 'outside_messaging_window');
            return null;
        }

        $apiKey = config('services.anthropic.key');
        if (! $apiKey) {
            $this->promoteToPending($conversation, 'no_api_key');
            Log::channel('messenger')->error('AI: ANTHROPIC_API_KEY missing');
            return null;
        }

        try {
            $client   = new \Anthropic\Client($apiKey);
            $response = $client->messages->create(
                maxTokens: 1024,
                messages: $this->buildMessages($conversation),
                model: config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                system: $this->promptBuilder->buildSystemPrompt($tenant),
            );

            $settings->consumeReply();

            app(UsageTracker::class)->record(
                $tenant,
                UsageTracker::SOURCE_MESSENGER,
                $response,
                $conversation->id,
            );

            $tokens = ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0);
            $text   = PromptBuilder::sanitizeReply((string) ($response->content[0]->text ?? ''));

            if ($text === '') {
                Log::channel('messenger')->warning('AI: empty response', [
                    'conversation_id' => $conversation->id,
                ]);
                $this->promoteToPending($conversation, 'ai_empty_response');
                return null;
            }

            if ($this->shouldEscalate($text, $settings)) {
                Log::channel('messenger')->info('AI: reply tripped escalation keyword', [
                    'conversation_id' => $conversation->id,
                    'reply_head'      => mb_substr($text, 0, 200),
                    'keywords'        => $settings->escalation_keywords ?? [],
                ]);
                $this->promoteToPending($conversation, 'ai_escalation_keyword', 'reply_keyword');
                return null;
            }

            // Reload fresh state — Claude can take a few seconds and an
            // agent may have claimed or closed the conversation mid-call.
            $fresh = Conversation::withoutGlobalScope('tenant')->find($conversation->id);
            if (! $fresh || $fresh->isClosed()) {
                Log::channel('messenger')->info('AI: reply discarded — conversation closed mid-call', [
                    'conversation_id' => $conversation->id,
                ]);
                return null;
            }
            if ($fresh->isAssigned() && ! $this->replyWhenClaimed($settings)) {
                Log::channel('messenger')->info('AI: reply discarded — agent took over mid-call', [
                    'conversation_id' => $conversation->id,
                    'claimed_by'      => $fresh->claimed_by,
                ]);
                return null;
            }

            Log::channel('messenger')->info('AI: reply generated', [
                'conversation_id' => $conversation->id,
                'tenant_id'       => $tenant->id,
                'tokens'          => $tokens,
                'was_pending'     => $fresh->isPending(),
            ]);

            if ($fresh->isPending()) {
                $fresh->status = Conversation::STATUS_BOT;
                $fresh->save();
            }

            // The reply has to traverse Meta before the visitor sees it.
            // MessengerService::sendText persists the outbound row with
            // Meta's mid AND POSTs to /me/messages. On any Meta rejection
            // we log + promote to pending so a human can pick it up.
            try {
                return $this->messenger->sendText(
                    conversationId: $fresh->id,
                    body: $text,
                    senderUserId: null,
                    senderType: Message::SENDER_BOT,
                );
            } catch (MessengerSendException $sendErr) {
                Log::channel('messenger')->warning('AI: reply generated but Meta send failed', [
                    'conversation_id' => $fresh->id,
                    'reason'          => $sendErr->reason,
                    'message'         => $sendErr->getMessage(),
                ]);
                $this->promoteToPending($fresh, 'ai_send_failed_' . $sendErr->reason);
                return null;
            }
        } catch (\Throwable $e) {
            Log::channel('messenger')->error('AI: reply failed', [
                'conversation_id' => $conversation->id,
                'tenant_id'       => $tenant?->id,
                'error'           => $e->getMessage(),
            ]);
            $this->promoteToPending($conversation, 'ai_error');
            return null;
        }
    }

    /**
     * Adapt the Messenger message thread to Claude's role-alternating
     * format. Same shape WebChat uses so PromptBuilder can stay
     * channel-agnostic.
     */
    private function buildMessages(Conversation $conversation, int $limit = 20): array
    {
        $raw = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->slice(-$limit)
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

        // Merge consecutive same-role messages so Claude's alternating
        // requirement doesn't reject the payload.
        $merged = [];
        foreach ($raw as $msg) {
            if (! empty($merged) && $merged[array_key_last($merged)]['role'] === $msg['role']) {
                $merged[array_key_last($merged)]['content'] .= "\n" . $msg['content'];
            } else {
                $merged[] = $msg;
            }
        }

        // Claude requires the first message to be `user`.
        while (! empty($merged) && $merged[0]['role'] !== 'user') {
            array_shift($merged);
        }

        return $merged;
    }

    private function shouldEscalate(string $text, $settings): bool
    {
        if (trim($text) === '') {
            return false;
        }
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

    private function promoteToPending(Conversation $conversation, string $reason, ?string $escalation = null): void
    {
        // Always log the DECISION even if we can't act on it, so
        // debugging never sits in the dark when an AI job silently
        // returns on a non-bot conversation. Cost 30 min on the
        // 2026-09-17 Messenger launch — worth the extra line.
        Log::channel('messenger')->info('AI: promoted to pending', [
            'conversation_id' => $conversation->id,
            'reason'          => $reason,
            'source'          => $escalation, // customer_keyword | reply_keyword | null
            'current_status'  => $conversation->status,
            'took_effect'     => $conversation->isBot(),
        ]);

        if (! $conversation->isBot()) {
            // Already claimed / already pending / already closed. The
            // AI has nothing to promote — an agent (or a previous AI
            // pass) already owns the conversation. Don't touch state.
            return;
        }

        $conversation->status           = Conversation::STATUS_PENDING;
        $conversation->last_activity_at = now();

        if ($escalation !== null && $conversation->escalated_at === null) {
            $conversation->escalated_at      = now();
            $conversation->escalation_reason = $escalation;
        }

        $conversation->save();

        // Phase 6 will fire a Reverb broadcast here so the agent inbox
        // updates in real time. For now the conversation just changes
        // status silently — an agent refresh picks it up.
    }
}
