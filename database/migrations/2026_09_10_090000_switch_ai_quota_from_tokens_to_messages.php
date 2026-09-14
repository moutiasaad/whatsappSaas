<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI allowance is now counted in replies, not tokens.
     *
     * Tokens were a billing-side unit that meant nothing to a tenant: nobody
     * can tell whether 100,000 tokens is a lot. A plan now grants a number of
     * AI replies per month, which is the thing the tenant actually watches.
     *
     * The columns are renamed rather than duplicated so there is one code path
     * and no window where the two disagree. The old *counters* are reset — a
     * token count is not a reply count, and carrying it over would instantly
     * exhaust every tenant.
     *
     * The null / 0 / N convention is unchanged:
     *   null = unlimited, 0 = AI off, N = hard cap for the period.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->renameColumn('ai_token_quota', 'ai_message_quota');
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->renameColumn('monthly_token_quota', 'monthly_message_quota');
            $table->renameColumn('tokens_used_this_period', 'ai_messages_used_this_period');
        });

        // Token counts are meaningless as reply counts — start everyone at zero
        // for the current period rather than importing a bogus number.
        DB::table('ai_settings')->update(['ai_messages_used_this_period' => 0]);

        // Seed a sensible ladder by price so the plans arrive with real numbers
        // instead of leftover token figures. The super admin edits these on
        // /admin-control-panel/platform/plans; anything past the ladder gets the
        // top rung rather than accidentally becoming unlimited.
        $ladder = [100, 500, 2000];
        $top    = 5000;

        DB::table('plans')->orderBy('price_monthly')->orderBy('id')
            ->pluck('id')
            ->each(function ($id, $index) use ($ladder, $top) {
                DB::table('plans')
                    ->where('id', $id)
                    ->update(['ai_message_quota' => $ladder[$index] ?? $top]);
            });

        // A plan that never included AI keeps meaning "AI off" (0), not a fresh
        // allowance handed out by the ladder above.
        DB::table('plans')->where('ai_included', false)->update(['ai_message_quota' => 0]);

        // Tenant rows inherit their plan's new allowance so nobody is left
        // holding a token-sized cap.
        DB::table('ai_settings')
            ->join('tenants', 'tenants.id', '=', 'ai_settings.tenant_id')
            ->join('plans', 'plans.id', '=', 'tenants.plan_id')
            ->update(['ai_settings.monthly_message_quota' => DB::raw('plans.ai_message_quota')]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->renameColumn('ai_message_quota', 'ai_token_quota');
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->renameColumn('monthly_message_quota', 'monthly_token_quota');
            $table->renameColumn('ai_messages_used_this_period', 'tokens_used_this_period');
        });
    }
};
