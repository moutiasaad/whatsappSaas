<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webchat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('widget_id')->index();
            $table->unsignedBigInteger('visitor_id')->index();
            $table->string('status', 16)->default('bot');
            $table->unsignedBigInteger('claimed_by')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('visitor_name', 120)->nullable();
            $table->string('visitor_email', 190)->nullable();
            $table->text('page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'widget_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webchat_conversations');
    }
};
