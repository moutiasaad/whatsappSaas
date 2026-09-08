<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-channel AI switches.
 *
 * `mode` says HOW the AI answers; these say WHERE it is allowed to. A tenant can
 * now run the AI on WhatsApp while keeping Live Chat human-only, or vice versa,
 * without flipping the mode off for both. Default true so existing tenants keep
 * the behaviour they have today.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(true)->after('mode');
            $table->boolean('webchat_enabled')->default(true)->after('whatsapp_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_enabled', 'webchat_enabled']);
        });
    }
};
