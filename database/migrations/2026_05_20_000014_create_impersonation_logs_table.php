<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('impersonator_user_id');
            $table->unsignedBigInteger('impersonated_user_id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->index('impersonator_user_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};
