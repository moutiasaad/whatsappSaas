<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * messenger_pages
 *
 * One row per Facebook Page a tenant has connected. Mirrors the shape of
 * webchat_widgets: per-tenant channel config with an `enabled` toggle and
 * an encrypted access token. Pages come from Meta's Graph API
 * (`/me/accounts`), where the token is Page-scoped and, when obtained from
 * a long-lived user token, does not expire unless the user changes their
 * password or revokes app access.
 *
 * `page_id` is Meta's numeric ID (kept as a string because Meta's docs
 * treat every ID that way, and JavaScript loses precision on ids > 2^53).
 *
 * A page starts `enabled=false` — the tenant admin flips it after they
 * confirm the connect flow worked, in the same shape as a WhatsApp
 * instance sitting in `qr_pending` before its first successful message.
 *
 * `disconnected_at` + `disconnect_reason` are set when Graph API returns
 * error code 190 (invalid token) so the tenant sees why they need to
 * reconnect instead of the page silently stopping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_pages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Meta IDs are large integers Meta always documents as strings.
            // We follow that convention to avoid JS-side precision loss when
            // the page id is echoed back through APIs.
            $table->string('page_id', 64);
            $table->string('page_name', 190);

            // Encrypted at the model layer via `'access_token' => 'encrypted'`.
            // Text (not string) because Meta doesn't cap Page-token length.
            $table->text('access_token');

            $table->boolean('enabled')->default(false);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->string('disconnect_reason', 190)->nullable();

            // Freeform bucket for the /me/accounts payload extras
            // (category, tasks, perms) that we don't want to hoist to
            // dedicated columns yet.
            $table->json('meta')->nullable();

            $table->timestamps();

            // A Page can be connected to only one tenant at a time — the
            // subscribed_apps API doesn't fan out. Global uniqueness is the
            // right rule; without it two tenants could each "own" the same
            // Page and every webhook would double-fire.
            $table->unique('page_id');

            // Fast lookup from tenant admin index.
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_pages');
    }
};
