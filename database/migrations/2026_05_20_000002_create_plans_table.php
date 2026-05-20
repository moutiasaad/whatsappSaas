<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_annual')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_annual', 10, 2)->default(0);
            $table->unsignedInteger('max_users')->default(5);
            $table->unsignedInteger('max_instances')->default(1);
            $table->unsignedInteger('max_conversations_per_month')->default(1000);
            $table->boolean('ai_included')->default(false);
            $table->unsignedInteger('ai_token_quota')->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
