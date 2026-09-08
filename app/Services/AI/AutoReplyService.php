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

            $tokens = ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0);
            $settings->increment('tokens_used_this_period', $tokens);

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
                $conversation->update(['ai_suspended' => true]);
                Log::channel('whatsapp')->info('AI: escalation keyword detected — suspended', [
                    'conversation_id' => $conversation->id,
                ]);
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

        SendOutgoingMessage::dispatch($message)->onQueue('whatsapp');
        return $message;
    }

    private function disableAndNotify($tenant): void
    {
        $quota = (int) ($tenant->aiSettings?->monthly_token_quota ?? 0);

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
