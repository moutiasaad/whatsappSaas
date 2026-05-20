<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('instance_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->enum('state', ['pool', 'claimed', 'closed'])->default('pool');
            $table->unsignedBigInteger('owner_agent_id')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('ai_suspended')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('instance_id')->references('id')->on('whatsapp_instances')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('owner_agent_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['tenant_id', 'state']);
            $table->index(['instance_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
