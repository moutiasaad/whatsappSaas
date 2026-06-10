<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
            $table->string('service_name')->default('Appointment Booking');
            $table->json('trigger_keywords')->nullable();
            $table->text('welcome_message')->nullable();
            $table->text('select_date_message')->nullable();
            $table->text('select_slot_message')->nullable();
            $table->text('ask_name_message')->nullable();
            $table->text('ask_notes_message')->nullable();
            $table->text('confirmation_message')->nullable();
            $table->text('cancellation_message')->nullable();
            $table->text('no_slots_message')->nullable();
            $table->boolean('collect_notes')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_settings');
    }
};
