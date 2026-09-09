<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PROC-016 (056a8a5) taught SendOutgoingMessage to reserve a row as 'sending'
// before the gateway call, and to mark an AI reply 'cancelled' when an agent
// takes the conversation over. Neither value was ever added to the enum, and
// MySQL runs STRICT_TRANS_TABLES here, so both writes raise
// "1265 Data truncated for column 'status'".
//
// The 'sending' write sits one line before $gateway->sendText(), so the job
// died before contacting WhatsApp: every AI and agent reply since 2026-09-08
// 21:18 was created in the DB and never delivered. OTP and reservations were
// unaffected because they call the gateway directly and never touch this
// column.
//
// Purely additive — every value already stored stays valid.
return new class extends Migration {
    private const WITH_NEW    = "'pending','sending','sent','delivered','read','failed','cancelled'";
    private const WITHOUT_NEW = "'pending','sent','delivered','read','failed'";

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE messages MODIFY status ENUM(' . self::WITH_NEW . ") NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        // Rows sitting on a value that is about to disappear would otherwise be
        // truncated to ''. Map them to the nearest surviving state first:
        // an unfinished send is retryable, a cancelled AI reply never went out.
        DB::table('messages')->where('status', 'sending')->update(['status' => 'pending']);
        DB::table('messages')->where('status', 'cancelled')->update(['status' => 'failed']);

        DB::statement(
            'ALTER TABLE messages MODIFY status ENUM(' . self::WITHOUT_NEW . ") NOT NULL DEFAULT 'pending'"
        );
    }
};
