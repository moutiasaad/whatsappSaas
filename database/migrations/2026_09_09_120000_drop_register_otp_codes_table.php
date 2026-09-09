<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PROC-024: the register_otp_codes table backed the removed pre-signup OTP flow
// and no code has referenced it since the flow was deleted. Dropping it so the
// schema stops implying a control that is not there.
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('register_otp_codes');
    }

    public function down(): void
    {
        Schema::create('register_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp', 8);
            $table->json('data')->nullable();
            $table->string('password_hash')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->timestamps();
        });
    }
};
