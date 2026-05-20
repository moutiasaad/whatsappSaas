<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('tenant_id');
            $table->enum('direction', ['in', 'out']);
            $table->enum('author_type', ['customer', 'agent', 'ai', 'system']);
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('external_message_id')->nullable();
            $table->enum('type', ['text', 'image', 'video', 'audio', 'document', 'location', 'contact'])->default('text');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_filename')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->json('ai_metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->unique(['tenant_id', 'external_message_id']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
