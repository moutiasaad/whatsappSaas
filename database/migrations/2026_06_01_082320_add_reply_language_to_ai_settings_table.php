<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('reply_language', 10)->default('auto')->after('mode');
            $table->unsignedTinyInteger('suggestion_count')->default(3)->after('reply_language');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['reply_language', 'suggestion_count']);
        });
    }
};
