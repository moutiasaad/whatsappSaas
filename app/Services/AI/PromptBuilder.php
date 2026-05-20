<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\Tenant;

class PromptBuilder
{
    public function buildSystemPrompt(Tenant $tenant): string
    {
        $entries = KnowledgeEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $profile  = $entries->firstWhere('type', 'company_profile');
        $faqs     = $entries->where('type', 'faq');
        $policies = $entries->where('type', 'policy');
        $custom   = $entries->firstWhere('type', 'custom_instruction');

        $parts = [
            "You are a helpful customer support assistant for {$tenant->name}.",
            "Answer ONLY using the knowledge base provided. If unsure, politely say you don't know and suggest contacting a human agent.",
            "Be concise, warm, and professional.",
        ];

        if ($profile) {
            $parts[] = "\n## Company\n{$profile->body}";
        }

        if ($faqs->isNotEmpty()) {
            $parts[] = "\n## FAQs";
            foreach ($faqs as $faq) {
                $parts[] = "Q: {$faq->title}\nA: {$faq->body}";
            }
        }

        if ($policies->isNotEmpty()) {
            $parts[] = "\n## Policies";
            foreach ($policies as $policy) {
                $parts[] = "### {$policy->title}\n{$policy->body}";
            }
        }

        if ($custom) {
            $parts[] = "\n## Instructions\n{$custom->body}";
        }

        return implode("\n\n", $parts);
    }

    public function buildMessages(Conversation $conversation, int $limit = 20): array
    {
        return $conversation->messages()
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
    }
}
