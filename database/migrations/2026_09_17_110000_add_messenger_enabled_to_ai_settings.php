<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds messenger_enabled toggle to ai_settings, matching the existing
 * whatsapp_enabled + webchat_enabled columns from
 * 2026_09_08_120000_add_channel_toggles_to_ai_settings.
 *
 * Default TRUE so the AI answers Messenger for every tenant with AI
 * turned on. Existing rows are backfilled to TRUE on the same
 * "opt-out" model — a tenant who wants the AI OFF on Messenger flips
 * it in AI Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('messenger_enabled')->default(true)->after('webchat_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn('messenger_enabled');
        });
    }
};
