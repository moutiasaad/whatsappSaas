<?php

namespace App\Services\AI;

use App\Models\AiApiUsage;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Records one Anthropic call as an ai_api_usages row, snapshotting the USD
 * cost so a later price-list edit never rewrites history.
 *
 * All five call sites (AutoReplyService, WebChatAutoReplyService,
 * MessengerAutoReplyService, ConversationTitleGenerator, AiController@ask)
 * hand the raw SDK response here and get billing-safe persistence in one line.
 */
class UsageTracker
{
    public const SOURCE_WHATSAPP  = 'whatsapp';
    public const SOURCE_WEBCHAT   = 'webchat';
    public const SOURCE_MESSENGER = 'messenger';
    public const SOURCE_TITLE     = 'title';
    public const SOURCE_ASK       = 'ask';

    /**
     * Persist a usage row. Always swallows its own errors — a cost-tracking
     * bug must never break the AI reply path that just succeeded.
     *
     * @param  int|Tenant|null  $tenant
     * @param  object|array|null $response  Anthropic SDK response (with `usage`) or a pre-shaped array.
     */
    public function record(
        $tenant,
        string $source,
        ?object $response,
        ?int $conversationId = null,
        ?string $modelOverride = null,
        array $meta = []
    ): ?AiApiUsage {
        try {
            $tenantId = $tenant instanceof Tenant ? $tenant->id : (int) $tenant;
            if ($tenantId <= 0) {
                return null;
            }

            $inputTokens  = (int) ($response?->usage?->inputTokens  ?? 0);
            $outputTokens = (int) ($response?->usage?->outputTokens ?? 0);

            // The SDK echoes the model that actually served the request,
            // which can differ from what was requested (aliases, dated
            // suffixes). Prefer the response value when present.
            $model = (string) ($modelOverride
                ?? ($response?->model ?? config('services.anthropic.model', 'claude-haiku-4-5-20251001')));

            $cost = $this->priceFor($model, $inputTokens, $outputTokens);

            return AiApiUsage::create([
                'tenant_id'       => $tenantId,
                'source'          => $source,
                'conversation_id' => $conversationId,
                'model'           => $model,
                'input_tokens'    => $inputTokens,
                'output_tokens'   => $outputTokens,
                'cost_usd'        => $cost,
                'meta'            => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('UsageTracker: failed to record row', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * $/MTok → USD for this call. Unknown models fall back to `default`
     * and log once so ops can spot a new model that needs a price.
     */
    public function priceFor(string $model, int $inputTokens, int $outputTokens): float
    {
        $rates = $this->ratesFor($model);

        // /1_000_000 not /1000 — Anthropic quotes per million tokens.
        $inCost  = ($inputTokens  / 1_000_000) * (float) $rates['input'];
        $outCost = ($outputTokens / 1_000_000) * (float) $rates['output'];

        return round($inCost + $outCost, 6);
    }

    /**
     * Match the model id against the pricing table. The SDK returns dated
     * suffixes like `claude-haiku-4-5-20251001`, so a startswith match is
     * used before falling back to `default`.
     *
     * @return array{input: float, output: float}
     */
    private function ratesFor(string $model): array
    {
        $models   = (array) config('anthropic_pricing.models', []);
        $default  = (array) config('anthropic_pricing.default', ['input' => 1.00, 'output' => 5.00]);

        if (isset($models[$model])) {
            return $models[$model];
        }

        foreach ($models as $prefix => $rates) {
            if (str_starts_with($model, $prefix)) {
                return $rates;
            }
        }

        Log::info('UsageTracker: unpriced Anthropic model — using default', [
            'model' => $model,
        ]);

        return $default;
    }
}
