<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->enum('gateway', ['evolution_api', 'waha', 'cloud_api'])->default('evolution_api');
            $table->string('gateway_instance_id')->nullable();
            $table->string('webhook_token', 64)->unique();
            $table->text('webhook_secret')->nullable();
            $table->string('phone_number')->nullable();
            $table->enum('status', ['connecting', 'qr_pending', 'connected', 'disconnected', 'error', 'banned'])->default('disconnected');
            $table->timestamp('last_status_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->text('qr_code')->nullable();
            $table->string('gateway_url')->nullable();
            $table->text('gateway_api_key')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};
