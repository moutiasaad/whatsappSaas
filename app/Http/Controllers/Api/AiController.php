<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSettings;
use App\Services\AI\PromptBuilder;
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
                'monthly_token_quota' => $tenant?->plan?->ai_token_quota,
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
            'monthly_token_quota'  => 'nullable|integer|min:0',
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
            $client = new \Anthropic\Client(config('services.anthropic.key'));

            // anthropic-ai/sdk v0.23: `messages` is a property, create() takes named args.
            $response = $client->messages->create(
                maxTokens: 512,
                messages: [['role' => 'user', 'content' => $request->question]],
                model: config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                system: $builder->buildSystemPrompt($tenant),
            );

            return response()->json([
                'answer'      => $response->content[0]->text ?? '',
                'tokens_used' => ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0),
            ]);

        } catch (\Throwable $e) {
            Log::error("AI test failed: {$e->getMessage()}");
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
