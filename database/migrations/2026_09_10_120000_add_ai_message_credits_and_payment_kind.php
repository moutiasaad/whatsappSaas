<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasable AI message top-ups.
 *
 * `extra_message_credits` is a reserve drawn on only after the plan's monthly
 * allowance is spent, so it survives the period rollover instead of being wiped
 * by it — a pack bought on the 28th would otherwise evaporate on the 1st.
 *
 * `tenant_payments.kind` separates a plan subscription from a one-off pack:
 * without it, completing a pack payment would run the subscription activation
 * path and silently move the tenant's renewal date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->unsignedInteger('extra_message_credits')
                ->default(0)
                ->after('ai_messages_used_this_period');
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->string('kind', 32)->default('subscription')->after('plan_id')->index();
            $table->json('metadata')->nullable()->after('gateway_response');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn('extra_message_credits');
        });

        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'metadata']);
        });
    }
};
