<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the per-plan PayPal NCP link.
 *
 * The static NCP link mode is retired in favour of the dynamic PayPal
 * Orders API flow that /payment/paypal/initiate now runs unconditionally.
 * Nothing reads this column any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'paypal_ncp_link')) {
                $table->dropColumn('paypal_ncp_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'paypal_ncp_link')) {
                $table->string('paypal_ncp_link', 500)->nullable()->after('stripe_price_id_annual');
            }
        });
    }
};
