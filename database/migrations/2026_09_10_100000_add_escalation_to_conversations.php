<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a conversation the AI handed off because the customer used one of the
 * tenant's escalation keywords, so the inbox can badge it as needing a human.
 *
 * Kept as a nullable timestamp rather than a boolean: "when" answers the badge
 * and lets a later report measure how long an escalated thread waited, and
 * clearing it back to null is how a thread stops being escalated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('ai_suspended');
            $table->string('escalation_reason', 40)->nullable()->after('escalated_at');
            $table->index('escalated_at');
        });

        Schema::table('webchat_conversations', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('status');
            $table->string('escalation_reason', 40)->nullable()->after('escalated_at');
            $table->index('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['escalated_at']);
            $table->dropColumn(['escalated_at', 'escalation_reason']);
        });

        Schema::table('webchat_conversations', function (Blueprint $table) {
            $table->dropIndex(['escalated_at']);
            $table->dropColumn(['escalated_at', 'escalation_reason']);
        });
    }
};
