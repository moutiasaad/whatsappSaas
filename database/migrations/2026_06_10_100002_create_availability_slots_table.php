<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // 'recurring' = same time every week on a given day, 'specific' = one-off date
            $table->enum('type', ['recurring', 'specific'])->default('recurring');
            $table->tinyInteger('day_of_week')->nullable()->comment('0=Sunday … 6=Saturday, used when type=recurring');
            $table->date('specific_date')->nullable()->comment('Used when type=specific');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('max_bookings')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'day_of_week']);
            $table->index(['tenant_id', 'specific_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_slots');
    }
};
