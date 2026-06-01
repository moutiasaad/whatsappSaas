<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->renameColumn('flouci_payment_id', 'stripe_session_id');
            $table->renameColumn('flouci_pay_url', 'stripe_checkout_url');
            $table->renameColumn('flouci_response', 'gateway_response');
            $table->string('currency', 3)->default('USD')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table) {
            $table->renameColumn('stripe_session_id', 'flouci_payment_id');
            $table->renameColumn('stripe_checkout_url', 'flouci_pay_url');
            $table->renameColumn('gateway_response', 'flouci_response');
            $table->string('currency', 3)->default('TND')->change();
        });
    }
};
