<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            $table->string('scope', 16)->default('tenant');
            $table->string('title', 120);
            $table->string('shortcut', 32)->nullable();
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'scope']);
            $table->index(['tenant_id', 'owner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_replies');
    }
};
