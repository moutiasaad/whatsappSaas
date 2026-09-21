<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-call Anthropic usage ledger.
 *
 * Cost is snapshotted at record time from config('anthropic_pricing') so that
 * a later rate change never rewrites history — a super-admin needs to trust
 * that the platform cost total lines up with what was actually billed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_api_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // whatsapp | webchat | messenger | title | ask
            $table->string('source', 32);
            // conversation_id is not FK-constrained: three different tables
            // (conversations, webchat_conversations, messenger_conversations)
            // can be the source, and a FK to one would reject rows from the
            // other two. Store the raw id + rely on `source` to disambiguate.
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            // Anthropic pricing is quoted in $/MTok, so single-call USD is
            // usually below one cent. decimal(12,6) keeps six decimal places
            // — enough to avoid rounding a $0.000032 call down to zero.
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index('model');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_api_usages');
    }
};
