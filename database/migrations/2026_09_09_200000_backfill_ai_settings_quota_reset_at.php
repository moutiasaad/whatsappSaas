<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// CALC-011: two of the three ai_settings creation paths (AiController::show
// and the pre-fix AiSettingsController::update fallback) left
// quota_reset_at NULL. The rollover then bailed on NULL and the tenant's
// AI stayed off forever once its 100k tokens were consumed. Backfill each
// null row to start a fresh period at the next month boundary, and zero
// tokens_used_this_period so exhausted tenants come back online at the
// same moment. Trade-off is intentional: without a historical audit of
// when each tenant actually consumed tokens, resetting them is more honest
// than leaving them disabled through no fault of theirs.
//
// Rows with an already-populated quota_reset_at are untouched.
return new class extends Migration {
    public function up(): void
    {
        $updated = DB::table('ai_settings')
            ->whereNull('quota_reset_at')
            ->update([
                'quota_reset_at'          => now()->startOfMonth()->addMonthNoOverflow(),
                'tokens_used_this_period' => 0,
                'updated_at'              => now(),
            ]);

        if ($updated > 0) {
            fwrite(STDERR, "  CALC-011: seeded quota_reset_at + zeroed tokens for {$updated} ai_settings row(s).\n");
        }
    }

    public function down(): void
    {
        // Not reversible — nulling quota_reset_at again would re-open the
        // lifetime-quota bug this migration was written to close.
    }
};
