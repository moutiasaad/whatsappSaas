<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webchat_widgets', function (Blueprint $table) {
            // Nullable = fall back to widget.js hardcoded default topics
            // so existing widgets keep working without a backfill.
            $table->json('topics')->nullable()->after('available_languages');
        });
    }

    public function down(): void
    {
        Schema::table('webchat_widgets', function (Blueprint $table) {
            $table->dropColumn('topics');
        });
    }
};
