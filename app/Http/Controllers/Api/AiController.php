<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSettings;
use App\Services\AI\PromptBuilder;
use App\Services\AI\UsageTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $settings = AiSettings::firstOrCreate(
            ['tenant_id' => $request->user()->tenant_id],
            [
                'mode'                => 'off',
                // Inherit from the tenant's plan — null (unlimited), 0 (off),
                // or the configured cap. Same convention flows everywhere.
                'monthly_message_quota' => $tenant?->plan?->ai_message_quota,
                // CALC-011: seed alongside the quota so this creation path
                // doesn't leave the row in the "lifetime quota" state that
                // exhausts once and never rolls over.
                'quota_reset_at'      => now()->startOfMonth()->addMonthNoOverflow(),
            ]
        );
        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode'                 => 'required|in:off,suggestion,autonomous,hybrid',
            'system_prompt'        => 'nullable|string|max:10000',
            'escalation_keywords'  => 'nullable|array',
            'escalation_keywords.*'=> 'string|max:50',
            'monthly_message_quota'  => 'nullable|integer|min:0',
        ]);

        $settings = AiSettings::updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id],
            $data
        );

        return response()->json($settings);
    }

    public function test(Request $request, PromptBuilder $builder): JsonResponse
    {
        $request->validate(['question' => 'required|string|max:500']);

        try {
            $tenant = $request->user()->tenant;

            // The sandbox makes a real API call, so it draws on the same monthly
            // reply allowance as a customer-facing answer. Leaving it unmetered
            // would be an open door around the plan's cap.
            $settings = AiSettings::firstWhere('tenant_id', $request->user()->tenant_id);
            if ($settings && !$settings->hasQuota()) {
                return response()->json([
                    'message' => __('ui.controller_messages.ai_quota_exhausted'),
                    'code'    => 'ai_quota_exhausted',
                ], 429);
            }

            $client = new \Anthropic\Client(config('services.anthropic.key'));

            // anthropic-ai/sdk v0.23: `messages` is a property, create() takes named args.
            $response = $client->messages->create(
                maxTokens: 512,
                messages: [['role' => 'user', 'content' => $request->question]],
                model: config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                system: $builder->buildSystemPrompt($tenant),
            );

            $settings?->consumeReply();

            app(UsageTracker::class)->record(
                $tenant,
                UsageTracker::SOURCE_ASK,
                $response,
            );

            return response()->json([
                'answer'          => $response->content[0]->text ?? '',
                'replies_used'    => $settings?->ai_messages_used_this_period,
                // effectiveQuota() honours the trial cap; the raw column
                // would show the plan's post-trial value here and mislead.
                'replies_allowed' => $settings?->effectiveQuota(),
            ]);

        } catch (\Throwable $e) {
            Log::error("AI test failed: {$e->getMessage()}");
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
