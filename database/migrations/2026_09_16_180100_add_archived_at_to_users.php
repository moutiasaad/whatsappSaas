<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `archived_at` on the users table. Same shape as tenants.archived_at
 * (2026_09_16_180000): distinct from is_active — an archived user is hidden
 * from the tenant admin's users list AND cannot log in, whereas a disabled
 * (is_active=false) user stays visible on the list marked inactive.
 *
 * Indexed because the users list filters on it on every load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_active');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
