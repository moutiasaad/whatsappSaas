<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('stripe')->after('currency')->index();
            $table->string('paypal_order_id')->nullable()->after('stripe_checkout_url')->index();
            $table->string('paypal_capture_id')->nullable()->after('paypal_order_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['paypal_order_id']);
            $table->dropIndex(['paypal_capture_id']);
            $table->dropColumn(['payment_method', 'paypal_order_id', 'paypal_capture_id']);
        });
    }
};
