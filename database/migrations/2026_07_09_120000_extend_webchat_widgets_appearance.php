<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webchat_widgets', function (Blueprint $table) {
            $table->string('header_subtitle', 160)->nullable()->after('name');
            $table->string('launcher_icon', 16)->default('chat')->after('launcher_text');
            $table->string('bubble_style', 16)->default('soft')->after('launcher_icon');
            $table->boolean('show_branding')->default(true)->after('bubble_style');
        });
    }

    public function down(): void
    {
        Schema::table('webchat_widgets', function (Blueprint $table) {
            $table->dropColumn(['header_subtitle', 'launcher_icon', 'bubble_style', 'show_branding']);
        });
    }
};
