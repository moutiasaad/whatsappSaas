<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webchat_visitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('widget_id')->index();
            $table->string('token', 64)->unique();
            $table->string('name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->json('attributes')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'widget_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webchat_visitors');
    }
};
