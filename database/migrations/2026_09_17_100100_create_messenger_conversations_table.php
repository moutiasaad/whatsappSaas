<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * messenger_conversations
 *
 * One row per Facebook user × Page pair. Mirrors webchat_conversations
 * (same status machine, same claim/close/escalate fields) with three
 * Messenger-specific differences:
 *
 *   - `psid` (Page-Scoped ID) replaces webchat's `visitor_id`. A PSID is
 *     unique per (user, page); the same real person has a different PSID
 *     talking to Wavadesk than to any other Meta app. Because of that,
 *     the unique key is (page_id, psid) — never psid alone.
 *
 *   - `contact_name` and `contact_avatar_url` are copied off Meta's
 *     Graph `/{psid}?fields=first_name,last_name,profile_pic` call the
 *     first time we see a new PSID. profile_pic URLs expire, so a
 *     periodic refresh job re-fetches them.
 *
 *   - `last_inbound_at` is what we compare against to enforce Meta's
 *     24-hour messaging window. Outside 24 h a reply requires
 *     `messaging_type: MESSAGE_TAG` with a permitted tag (HUMAN_AGENT
 *     is the useful one for support, but requires a separate App
 *     Review permission).
 *
 * Fields `page_url`, `referrer`, `user_agent`, `ip` are absent — a
 * visitor arrives from Messenger, not a webpage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('page_id')->index();

            // PSID is a Meta numeric ID (up to 16 digits). Stored as string
            // for the same JS-precision reason as messenger_pages.page_id.
            $table->string('psid', 64);

            // Optional title, backfilled by the same
            // ConversationTitleGenerator that the WhatsApp + WebChat
            // channels use on close.
            $table->string('title', 190)->nullable();

            // Status machine matches WebChat's exactly so the shared
            // agent inbox filters work with no channel-specific branches.
            $table->string('status', 16)->default('bot');

            $table->unsignedBigInteger('claimed_by')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamp('last_activity_at')->nullable();

            // Critical for the 24-hour rule. Set on every inbound
            // message; the composer disables when now() > last_inbound_at + 24h.
            $table->timestamp('last_inbound_at')->nullable();

            $table->timestamp('escalated_at')->nullable();
            $table->string('escalation_reason', 190)->nullable();

            // Snapshotted from Graph on first sight of a new PSID. Not a
            // relation — we don't have a `messenger_users` table because
            // there is nothing else to store about a PSID beyond these two.
            $table->string('contact_name', 190)->nullable();
            $table->string('contact_avatar_url', 512)->nullable();
            $table->timestamp('contact_profile_refreshed_at')->nullable();

            $table->timestamps();

            // A user cannot have two open conversations with the same
            // Page at once. Meta doesn't distinguish either — a returning
            // PSID belongs to the same conversation history.
            $table->unique(['page_id', 'psid']);

            // The two hottest scan patterns from the agent inbox.
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_conversations');
    }
};
