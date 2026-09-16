<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Remembers the mode a tenant was on before quota exhaustion auto-flipped
     * them to `off`, so buying a top-up pack — or the period rolling over —
     * can restore that mode instead of leaving the tenant silently disabled
     * with fresh credits sitting on the row.
     *
     * Before this column: AutoReplyService::disableAndNotify wrote `mode=off`
     * on quota exhaustion, then the top-up flow credited extra_message_credits
     * but did nothing about mode, so hasQuota() returned true again but every
     * inbound message short-circuited on `mode === 'off'` at the top of
     * maybeReply. The customer's money was taken and no more AI replies went
     * out until the admin manually re-enabled mode on the AI settings page.
     */
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('mode_before_auto_off', 20)->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn('mode_before_auto_off');
        });
    }
};
