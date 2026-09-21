<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plan PayPal NCP (No Code Payment) link.
 *
 * Overrides the platform-wide PAYPAL_NCP_LINK env when set. Each plan
 * can carry its own fixed-amount PayPal button URL — the checkout page
 * uses the plan's link first, then falls back to the env, then falls
 * back to the SDK/REST flow when neither is set. See
 * PaymentController::initiatePaypalNcp for the resolution order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('paypal_ncp_link', 500)->nullable()->after('stripe_price_id_annual');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('paypal_ncp_link');
        });
    }
};
