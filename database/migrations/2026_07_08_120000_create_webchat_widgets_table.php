<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webchat_widgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->string('public_key', 64)->unique();
            $table->string('name', 120);
            $table->boolean('enabled')->default(true);
            $table->text('welcome_message');
            $table->json('suggestions')->nullable();
            $table->boolean('pre_chat_ask_email')->default(false);
            $table->text('offline_message')->nullable();
            $table->string('theme_color', 16)->default('#2563eb');
            $table->string('position', 8)->default('right');
            $table->string('launcher_text', 120)->nullable();
            $table->json('allowed_domains')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webchat_widgets');
    }
};
