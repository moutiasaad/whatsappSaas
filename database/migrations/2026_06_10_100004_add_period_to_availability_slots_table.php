<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_slots', function (Blueprint $table) {
            $table->enum('period', ['morning', 'afternoon'])->default('morning')->after('type');
        });

        // Auto-assign existing slots based on their start_time
        DB::statement("UPDATE availability_slots SET period = CASE WHEN start_time >= '12:00:00' THEN 'afternoon' ELSE 'morning' END");
    }

    public function down(): void
    {
        Schema::table('availability_slots', function (Blueprint $table) {
            $table->dropColumn('period');
        });
    }
};
