<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * messenger_messages
 *
 * Mirrors webchat_messages with two Messenger-specific additions:
 *
 *   - `external_id` holds Meta's `mid` and is uniquely indexed. Meta
 *     retries webhooks on 5xx / slow responses, and every retry carries
 *     the same mid — the unique constraint is what makes ingest
 *     idempotent. Without it a laggy queue produces duplicated
 *     messages in the inbox.
 *
 *   - `attachments` (JSON) is separate from `body` because Messenger
 *     messages routinely carry no text (image-only, sticker, quick
 *     reply). Body being nullable follows.
 *
 * `meta` catches everything else worth keeping around: delivery /
 * read watermarks, postback payloads, echo flags, reply-to context,
 * messaging_type ("RESPONSE" | "MESSAGE_TAG" for outbound), etc. Kept
 * as JSON so schema evolves without a migration each time Meta ships
 * a new field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('conversation_id')->index();

            // Meta's `mid` field. Nullable because outbound messages
            // that fail before Meta assigns a mid still need a row so
            // the agent sees "failed to send" in the inbox.
            $table->string('external_id', 190)->nullable()->unique();

            // sender_type mirrors WebChat: visitor | agent | bot | system.
            $table->string('sender_type', 16);
            $table->unsignedBigInteger('sender_id')->nullable()->index();

            // Nullable — image-only / sticker / quick-reply messages
            // carry no text.
            $table->text('body')->nullable();

            // Meta attachment array. Each item has type
            // (image|video|audio|file|template|fallback) + payload.
            $table->json('attachments')->nullable();

            // See class-level PHPDoc for what lives here.
            $table->json('meta')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_messages');
    }
};
