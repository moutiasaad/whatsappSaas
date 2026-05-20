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

        if (!$settings || $settings->mode === 'off') return null;
        if (!$conversation->isAiEligible()) return null;
        if (!$settings->hasQuota()) {
            $this->disableAndNotify($conversation->tenant);
            return null;
        }

        try {
            $client = new \Anthropic\Client(config('services.anthropic.key'));

            $response = $client->messages()->create([
                'model'     => 'claude-sonnet-4-20250514',
                'max_tokens'=> 1024,
                'system'    => $this->promptBuilder->buildSystemPrompt($conversation->tenant),
                'messages'  => $this->promptBuilder->buildMessages($conversation),
            ]);

            $tokens = ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0);
            $settings->increment('tokens_used_this_period', $tokens);

            $text = $response->content[0]->text ?? '';

            if ($this->shouldEscalate($text, $settings)) {
                $conversation->update(['ai_suspended' => true]);
                return null;
            }

            if ($settings->mode === 'suggestion') {
                return $this->storeSuggestion($conversation, $text);
            }

            return $this->sendAutonomously($conversation, $text);

        } catch (\Exception $e) {
            Log::error("AI reply failed for conversation {$conversation->id}: {$e->getMessage()}");
            return null;
        }
    }

    private function shouldEscalate(string $text, $settings): bool
    {
        $keywords = $settings->escalation_keywords ?? ['manager', 'refund', 'complaint', 'lawsuit'];
        foreach ($keywords as $kw) {
            if (stripos($text, $kw) !== false) return true;
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

        SendOutgoingMessage::dispatch($message);
        return $message;
    }

    private function disableAndNotify($tenant): void
    {
        $tenant->aiSettings()->update(['mode' => 'off']);
        Log::warning("AI disabled for tenant {$tenant->id}: quota exhausted");
    }
}
