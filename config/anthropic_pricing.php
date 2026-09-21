<?php

/*
 * Anthropic price list (USD per 1,000,000 tokens).
 *
 * Public pricing as of Jan 2026 — check https://www.anthropic.com/pricing
 * before assuming these are still current. Each rate can be overridden with
 * an env var so a change can be shipped without a code deploy.
 *
 * If a model comes back that isn't listed here, UsageTracker falls back to
 * `default` and logs the unknown id so the platform still bills something
 * plausible instead of $0.
 */
return [

    'default' => [
        'input'  => (float) env('ANTHROPIC_PRICE_DEFAULT_INPUT',  1.00),
        'output' => (float) env('ANTHROPIC_PRICE_DEFAULT_OUTPUT', 5.00),
    ],

    'models' => [

        // Haiku 4.5 — the default model used by every auto-reply service.
        'claude-haiku-4-5' => [
            'input'  => (float) env('ANTHROPIC_PRICE_HAIKU_INPUT',  1.00),
            'output' => (float) env('ANTHROPIC_PRICE_HAIKU_OUTPUT', 5.00),
        ],

        // Sonnet 4.6.
        'claude-sonnet-4-6' => [
            'input'  => (float) env('ANTHROPIC_PRICE_SONNET_INPUT',  3.00),
            'output' => (float) env('ANTHROPIC_PRICE_SONNET_OUTPUT', 15.00),
        ],

        // Opus 4.7.
        'claude-opus-4-7' => [
            'input'  => (float) env('ANTHROPIC_PRICE_OPUS_INPUT',  15.00),
            'output' => (float) env('ANTHROPIC_PRICE_OPUS_OUTPUT', 75.00),
        ],

    ],

];
