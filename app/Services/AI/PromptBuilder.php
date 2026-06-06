<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\Tenant;

class PromptBuilder
{
    public function buildSystemPrompt(Tenant $tenant): string
    {
        $settings = $tenant->aiSettings;

        // Use the admin-configured system prompt if set; otherwise fall back to a default
        $customPrompt = trim((string) ($settings?->system_prompt ?? ''));

        if ($customPrompt !== '') {
            $base = $customPrompt;
        } else {
            $base = implode("\n", [
                "You are a helpful customer support assistant for {$tenant->name}.",
                "Be concise, warm, and professional.",
                "If you don't know the answer, politely say so and suggest the customer contact a human agent.",
            ]);
        }

        // Append Knowledge Base entries on top of whatever base prompt is set
        $entries = KnowledgeEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $parts = [$base];

        $profile  = $entries->firstWhere('type', 'company_profile');
        $products = $entries->where('type', 'product');
        $faqs     = $entries->where('type', 'faq');
        $policies = $entries->where('type', 'policy');
        $custom   = $entries->firstWhere('type', 'custom_instruction');

        if ($profile) {
            $parts[] = "## Company\n{$profile->body}";
        }

        if ($products->isNotEmpty()) {
            $parts[] = "## Products & Services";
            foreach ($products as $product) {
                $parts[] = "### {$product->title}\n{$product->body}";
            }
        }

        if ($faqs->isNotEmpty()) {
            $parts[] = "## FAQs";
            foreach ($faqs as $faq) {
                $parts[] = "Q: {$faq->title}\nA: {$faq->body}";
            }
        }

        if ($policies->isNotEmpty()) {
            $parts[] = "## Policies";
            foreach ($policies as $policy) {
                $parts[] = "### {$policy->title}\n{$policy->body}";
            }
        }

        if ($custom) {
            $parts[] = "## Additional Instructions\n{$custom->body}";
        }

        return implode("\n\n", $parts);
    }

    public function buildMessages(Conversation $conversation, int $limit = 20): array
    {
        $raw = $conversation->messages()
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn($msg) => [
                'role'    => $msg->direction === 'in' ? 'user' : 'assistant',
                'content' => $msg->body ?? '[media]',
            ])
            ->values()
            ->toArray();

        // Claude requires strictly alternating user/assistant roles — merge consecutive same-role messages
        $merged = [];
        foreach ($raw as $msg) {
            if (!empty($merged) && $merged[array_key_last($merged)]['role'] === $msg['role']) {
                $merged[array_key_last($merged)]['content'] .= "\n" . $msg['content'];
            } else {
                $merged[] = $msg;
            }
        }

        // First message must be 'user' — drop any leading assistant turns
        while (!empty($merged) && $merged[0]['role'] !== 'user') {
            array_shift($merged);
        }

        return $merged;
    }
}
