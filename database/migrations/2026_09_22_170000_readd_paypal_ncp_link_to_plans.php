<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-add per-plan PayPal NCP link.
 *
 * The static NCP link mode was dropped in 2026_09_22_150000 in favour of
 * the dynamic Orders API flow, but the operator prefers the hosted NCP
 * button URL (paypal.com/ncp/payment/XXX) for its one-time setup + zero
 * API surface. Bringing the column back and gating the checkout on it:
 * when set, the button links straight to the plan's NCP URL; when null
 * we fall back to the Orders API flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'paypal_ncp_link')) {
                $table->string('paypal_ncp_link', 500)->nullable()->after('stripe_price_id_annual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'paypal_ncp_link')) {
                $table->dropColumn('paypal_ncp_link');
            }
        });
    }
};
