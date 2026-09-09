<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PROC-022: eight FK columns were declared bare — no index, and four without a
// foreign-key constraint either — so joins/aggregates degraded to full scans
// and the database allowed orphaned ids. The Inbox now polls the team_id
// column on every open session, which turned a slow report query into the hot
// path of the primary agent screen. This migration:
//
//   1. Nulls orphan FK values on nullable columns so the FK constraints below
//      can be applied on production data (a lingering stale id would otherwise
//      fail the ALTER).
//   2. Adds indexes on the eight columns.
//   3. Adds FK constraints on the four columns where a broken reference is a
//      correctness issue (tenants.plan_id restrict; the three actor columns
//      nullOnDelete since they're nullable and represent "who did this").
//   4. Adds a composite index on messages(tenant_id, sent_at) for the report
//      heat-map and AI-vs-agent queries that filter on exactly that pair.
return new class extends Migration {
    public function up(): void
    {
        // 1. Clean orphans on nullable FK columns so the FK constraints below apply.
        DB::table('tenants')
            ->whereNotNull('plan_id')
            ->whereNotIn('plan_id', DB::table('plans')->select('id'))
            ->update(['plan_id' => null]);

        DB::table('conversations')
            ->whereNotNull('team_id')
            ->whereNotIn('team_id', DB::table('teams')->select('id'))
            ->update(['team_id' => null]);

        DB::table('messages')
            ->whereNotNull('author_id')
            ->whereNotIn('author_id', DB::table('users')->select('id'))
            ->update(['author_id' => null]);

        DB::table('conversation_events')
            ->whereNotNull('actor_id')
            ->whereNotIn('actor_id', DB::table('users')->select('id'))
            ->update(['actor_id' => null]);

        // 2. tenants.plan_id — restrictOnDelete: a plan in use cannot be dropped.
        Schema::table('tenants', function (Blueprint $t) {
            $t->index('plan_id', 'tenants_plan_id_index');
            $t->foreign('plan_id', 'tenants_plan_id_foreign')
                ->references('id')->on('plans')
                ->restrictOnDelete();
        });

        // 3. conversations.team_id + owner_agent_id.
        //    team_id: new FK, nullOnDelete (teams can be deleted; conversation
        //    falls back to pool routing).
        //    owner_agent_id: FK already exists, but no explicit index — add one
        //    so the response-time report and agent leaderboard don't full-scan.
        Schema::table('conversations', function (Blueprint $t) {
            $t->index('team_id', 'conversations_team_id_index');
            $t->index('owner_agent_id', 'conversations_owner_agent_id_index');
            $t->foreign('team_id', 'conversations_team_id_foreign')
                ->references('id')->on('teams')
                ->nullOnDelete();
        });

        // 4. messages.author_id + composite (tenant_id, sent_at).
        Schema::table('messages', function (Blueprint $t) {
            $t->index('author_id', 'messages_author_id_index');
            $t->index(['tenant_id', 'sent_at'], 'messages_tenant_id_sent_at_index');
            $t->foreign('author_id', 'messages_author_id_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        // 5. conversation_events.actor_id.
        Schema::table('conversation_events', function (Blueprint $t) {
            $t->index('actor_id', 'conversation_events_actor_id_index');
            $t->foreign('actor_id', 'conversation_events_actor_id_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        // 6. impersonation_logs.impersonated_user_id — index only; keep the log
        //    intact when a user is deleted (audit trail).
        Schema::table('impersonation_logs', function (Blueprint $t) {
            $t->index('impersonated_user_id', 'impersonation_logs_impersonated_user_id_index');
        });

        // 7. audit_logs.target_id — polymorphic (target_type + target_id).
        //    Index the id column; no FK because target_type varies.
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->index('target_id', 'audit_logs_target_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropIndex('audit_logs_target_id_index');
        });

        Schema::table('impersonation_logs', function (Blueprint $t) {
            $t->dropIndex('impersonation_logs_impersonated_user_id_index');
        });

        Schema::table('conversation_events', function (Blueprint $t) {
            $t->dropForeign('conversation_events_actor_id_foreign');
            $t->dropIndex('conversation_events_actor_id_index');
        });

        Schema::table('messages', function (Blueprint $t) {
            $t->dropForeign('messages_author_id_foreign');
            $t->dropIndex('messages_tenant_id_sent_at_index');
            $t->dropIndex('messages_author_id_index');
        });

        Schema::table('conversations', function (Blueprint $t) {
            $t->dropForeign('conversations_team_id_foreign');
            $t->dropIndex('conversations_owner_agent_id_index');
            $t->dropIndex('conversations_team_id_index');
        });

        Schema::table('tenants', function (Blueprint $t) {
            $t->dropForeign('tenants_plan_id_foreign');
            $t->dropIndex('tenants_plan_id_index');
        });
    }
};
