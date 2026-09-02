<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webchat_widgets', function (Blueprint $table) {
            $table->string('default_lang', 8)->default('ar')->after('show_branding');
            $table->json('available_languages')->nullable()->after('default_lang');
        });

        DB::table('webchat_widgets')->update([
            'available_languages' => json_encode(['ar', 'en']),
        ]);
    }

    public function down(): void
    {
        Schema::table('webchat_widgets', function (Blueprint $table) {
            $table->dropColumn(['default_lang', 'available_languages']);
        });
    }
};
