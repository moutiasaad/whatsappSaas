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
                "You are a customer support assistant for {$tenant->name}.",
                "If you don't know the answer, say so plainly and suggest the customer wait for a human agent.",
            ]);
        }

        // Style guardrails — appended even to custom prompts because the
        // widget/WhatsApp bubbles render plain text (markdown shows as raw
        // asterisks) and the model otherwise opens with sycophantic
        // pleasantries like "Excellente question !".
        $base .= "\n\n" . implode("\n", [
            "## Response style (strict)",
            "- Answer immediately. Never open with pleasantries like \"Great question\", \"Excellent question\", \"Excellente question\", \"Bien sûr\", \"Certainly\", \"Of course\", \"Absolutely\", or any variant. Start with the actual answer.",
            "- Keep replies short: 1–3 short sentences by default. Only go longer if the user explicitly asks for detail.",
            "- Plain text only. NO markdown: no **bold**, no *italics*, no ## headings, no bullet lists with `-` or `*`. Write natural prose.",
            "- Do not restate the user's question before answering.",
            "- Do not end with meta phrases like \"I hope this helps\" or \"Let me know if you need more info\" unless a follow-up question is genuinely useful.",
            "- Match the user's language automatically (French, English, or Arabic).",
        ]);

        // Append Knowledge Base entries on top of whatever base prompt is set
        $entries = KnowledgeEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $parts = [$base];

        $profiles = $entries->where('type', 'company_profile');
        $products = $entries->where('type', 'product');
        $faqs     = $entries->where('type', 'faq');
        $policies = $entries->where('type', 'policy');
        $customs  = $entries->where('type', 'custom_instruction');

        if ($profiles->isNotEmpty()) {
            $parts[] = "## Company";
            foreach ($profiles as $profile) {
                $parts[] = "### {$profile->title}\n{$profile->body}";
            }
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

        if ($customs->isNotEmpty()) {
            $parts[] = "## Additional Instructions";
            foreach ($customs as $custom) {
                $parts[] = "### {$custom->title}\n{$custom->body}";
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Clean an AI-generated reply before it hits WhatsApp/webchat bubbles.
     * Strips markdown that would render as raw asterisks in plain-text
     * surfaces and drops sycophantic openers that leak past the prompt.
     */
    public static function sanitizeReply(string $text): string
    {
        // Bold + italic (both `**x**` / `__x__` and `*x*` / `_x_`)
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = preg_replace('/__(.+?)__/s', '$1', $text);
        $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '$1', $text);
        $text = preg_replace('/(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/s', '$1', $text);

        // Leading `#`, `##`, `###` heading marks at line start
        $text = preg_replace('/^\s{0,3}#{1,6}\s+/m', '', $text);

        // Sycophantic openers on the first line. The pattern matches the
        // whole phrase up to and including the punctuation + space that
        // usually follows it, so the real answer starts cleanly.
        $openers = [
            'excellente question',
            'excellent question',
            'great question',
            'good question',
            'très bonne question',
            'bonne question',
            'bien sûr',
            'bien sur',
            'of course',
            'certainly',
            'absolutely',
            'sure thing',
            'sure',
        ];
        $pattern = '/^\s*(?:' . implode('|', array_map('preg_quote', $openers)) . ')\s*[!.,:;]+\s*/i';
        $text = preg_replace($pattern, '', $text);

        return trim($text);
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
