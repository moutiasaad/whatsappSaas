<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchased agent seats, held on the tenant rather than the plan.
 *
 * TenantQuota adds this to the plan's max_users, so a seat pack survives a plan
 * change instead of being wiped by it — the tenant paid for the seat, not for
 * the tier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('extra_seats')->default(0)->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('extra_seats');
        });
    }
};
