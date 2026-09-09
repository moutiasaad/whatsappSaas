<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Unify AI quota semantics across plans + ai_settings:
//   null       = unlimited (no cap)
//   0          = AI OFF for this plan/tenant
//   positive   = hard token cap per period
//
// Historically 0 meant "unlimited" in ai_settings. To keep every existing
// tenant on their current effective behaviour, backfill each existing 0 to
// NULL — so nobody who was previously "unlimited" suddenly gets disabled.
// After this migration, any operator who genuinely wants OFF types 0 and
// the change flows through the SuperAdmin plan sync.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $t) {
            $t->unsignedInteger('monthly_token_quota')->nullable()->default(null)->change();
        });

        Schema::table('plans', function (Blueprint $t) {
            $t->unsignedInteger('ai_token_quota')->nullable()->default(null)->change();
        });

        $tenantsFlipped = DB::table('ai_settings')
            ->where('monthly_token_quota', 0)
            ->update(['monthly_token_quota' => null]);

        $plansFlipped = DB::table('plans')
            ->where('ai_token_quota', 0)
            ->update(['ai_token_quota' => null]);

        fwrite(STDERR, "  Quota unify: {$tenantsFlipped} ai_settings + {$plansFlipped} plans "
            . "had quota=0 (historical 'unlimited') and were converted to NULL.\n");
    }

    public function down(): void
    {
        // Revert would need to decide what to write for NULL rows; NULL used to
        // mean nothing in either table. Skip.
    }
};
