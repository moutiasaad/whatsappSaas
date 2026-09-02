<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('title', 180)->nullable()->after('team_id');
        });

        Schema::table('webchat_conversations', function (Blueprint $table) {
            $table->string('title', 180)->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('webchat_conversations', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
