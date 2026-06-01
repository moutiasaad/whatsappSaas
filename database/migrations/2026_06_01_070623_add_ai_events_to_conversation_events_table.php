<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE conversation_events MODIFY COLUMN type ENUM(
            'claimed','released','reassigned','closed','reopened',
            'escalated','joined','note_added','ai_suspended','ai_resumed'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE conversation_events MODIFY COLUMN type ENUM(
            'claimed','released','reassigned','closed','reopened',
            'escalated','joined','note_added'
        ) NOT NULL");
    }
};
