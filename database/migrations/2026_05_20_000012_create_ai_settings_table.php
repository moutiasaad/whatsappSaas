<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->primary();
            $table->enum('mode', ['off', 'suggestion', 'autonomous', 'hybrid'])->default('off');
            $table->text('system_prompt')->nullable();
            $table->json('escalation_keywords')->nullable();
            $table->unsignedInteger('monthly_token_quota')->default(100000);
            $table->unsignedInteger('tokens_used_this_period')->default(0);
            $table->timestamp('quota_reset_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
