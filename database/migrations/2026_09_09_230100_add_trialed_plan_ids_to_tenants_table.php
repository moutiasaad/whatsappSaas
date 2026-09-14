<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which plans a tenant has already used a free trial on, so a plan
     * switch can grant a trial exactly once per plan instead of letting a
     * tenant hop between plans to stay permanently un-billed.
     *
     * Backfilled with the tenant's current plan wherever a trial has already
     * been used, so existing trialists cannot immediately re-trial the plan
     * they are sitting on.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('trialed_plan_ids')->nullable()->after('trial_ends_at');
        });

        \App\Models\Tenant::query()
            ->whereNotNull('trial_ends_at')
            ->whereNotNull('plan_id')
            ->get(['id', 'plan_id'])
            ->each(fn ($tenant) => $tenant->forceFill([
                'trialed_plan_ids' => [(int) $tenant->plan_id],
            ])->saveQuietly());
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('trialed_plan_ids');
        });
    }
};
