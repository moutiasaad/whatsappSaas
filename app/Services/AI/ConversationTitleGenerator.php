<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebChat\Conversation as WebChatConversation;
use App\Models\WebChat\Message as WebChatMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationTitleGenerator
{
    private const MAX_MESSAGES = 40;
    private const MAX_CHARS    = 180;

    public function forWhatsApp(Conversation $conversation): string
    {
        $lines = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->limit(self::MAX_MESSAGES)
            ->get(['direction', 'author_type', 'body'])
            ->map(function (Message $m) {
                $who = $m->direction === 'in'
                    ? 'Customer'
                    : ($m->author_type === 'ai' ? 'AI' : 'Agent');
                return $who . ': ' . trim((string) $m->body);
            })
            ->filter(fn ($l) => $l !== '' && !Str::endsWith($l, ': '))
            ->values()
            ->all();

        return $this->askClaude($lines, app()->getLocale(), $conversation->tenant_id, $conversation->id);
    }

    public function forWebChat(WebChatConversation $conversation): string
    {
        $lines = WebChatMessage::where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->limit(self::MAX_MESSAGES)
            ->get(['sender_type', 'body'])
            ->map(function (WebChatMessage $m) {
                $who = match ($m->sender_type) {
                    WebChatMessage::SENDER_VISITOR => 'Visitor',
                    WebChatMessage::SENDER_AGENT   => 'Agent',
                    WebChatMessage::SENDER_BOT     => 'AI',
                    default                        => 'System',
                };
                return $who . ': ' . trim((string) $m->body);
            })
            ->filter(fn ($l) => $l !== '' && !Str::endsWith($l, ': '))
            ->values()
            ->all();

        return $this->askClaude($lines, app()->getLocale(), $conversation->tenant_id, $conversation->id);
    }

    private function askClaude(array $lines, string $locale, ?int $tenantId = null, ?int $conversationId = null): string
    {
        if (empty($lines)) {
            return '';
        }

        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            Log::warning('ConversationTitleGenerator: ANTHROPIC_API_KEY missing');
            return '';
        }

        $transcript = implode("\n", $lines);

        try {
            $client   = new \Anthropic\Client($apiKey);
            // anthropic-ai/sdk v0.23: `messages` is a property, create() takes named args.
            $response = $client->messages->create(
                maxTokens: 60,
                messages: [[
                    'role'    => 'user',
                    'content' => "Transcript:\n\n" . $transcript,
                ]],
                model: config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                system: $this->systemPrompt($locale),
            );

            if ($tenantId) {
                app(UsageTracker::class)->record(
                    $tenantId,
                    UsageTracker::SOURCE_TITLE,
                    $response,
                    $conversationId,
                );
            }

            $text = trim($response->content[0]->text ?? '');
            $text = trim($text, "\"'“”‘’ \t\n\r\0\x0B");

            if ($text === '') return '';

            return Str::limit($text, self::MAX_CHARS, '');
        } catch (\Throwable $e) {
            Log::warning('ConversationTitleGenerator: request failed', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function systemPrompt(string $locale): string
    {
        $langHint = match ($locale) {
            'fr'    => 'Réponds en français.',
            'ar'    => 'أجب باللغة العربية.',
            default => 'Answer in English.',
        };

        return "You write concise support-ticket titles. Given a conversation transcript, "
            . "return a single short title (3 to 8 words, no trailing period, no quotes) that "
            . "summarizes the customer's issue or request. Prefer nouns over verbs. "
            . "Do NOT include the customer's name. Output only the title text, nothing else. "
            . $langHint;
    }
}
