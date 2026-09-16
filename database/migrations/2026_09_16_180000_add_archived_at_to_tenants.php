<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `archived_at` on the tenants table for super-admin's archive/restore
 * action. Distinct from `is_active` — block and archive are orthogonal states:
 *
 *   is_active=false, archived_at=null  → blocked (visible in list, message
 *                                        says "suspended by administrator")
 *   is_active=true,  archived_at=set   → archived (hidden from list, message
 *                                        says "workspace archived")
 *
 * The column is indexed because every super-admin tenants-list query filters
 * on it (default: only non-archived rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_active');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
