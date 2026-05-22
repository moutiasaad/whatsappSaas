<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->boolean('webhook_enabled')->default(false)->after('webhook_secret');
            $table->string('webhook_url')->nullable()->after('webhook_enabled');
            $table->timestamp('webhook_last_set')->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->dropColumn(['webhook_enabled', 'webhook_url', 'webhook_last_set']);
        });
    }
};
