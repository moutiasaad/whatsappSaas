<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base USD reference on tenant_payments — so the super-admin payments
 * list can show "149 SAR ($39 USD equivalent)" even after a customer paid
 * in a local currency. Snapshotted at checkout time from the plan's
 * price_monthly so an FX-rate change later doesn't rewrite history.
 *
 * Nullable because historical rows predate the column (all older payments
 * were USD, so base_amount_usd is redundant / assumed equal to amount).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->decimal('base_amount_usd', 10, 2)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropColumn('base_amount_usd');
        });
    }
};
