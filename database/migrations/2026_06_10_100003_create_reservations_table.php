<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('availability_slots')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_phone', 50);
            $table->string('customer_name', 150)->nullable();
            $table->text('customer_notes')->nullable();
            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('confirmed');
            $table->timestamp('booked_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'reservation_date', 'status']);
            $table->index(['tenant_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
