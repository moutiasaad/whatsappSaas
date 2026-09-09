<?php

use App\Models\WhatsAppInstance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

// PROC-018 phase 2: instances created before the phase 1 fix never wrote
// webhook_secret, so the verifier's fail-open branch stayed reachable for
// every legacy row. Backfill each null secret so the DB half is uniform;
// the gateway half is done by `php artisan whatsapp:reconfigure-webhooks`
// on deploy, which also stamps webhook_last_set so the verifier can flip
// to fail-closed for that row.
return new class extends Migration {
    public function up(): void
    {
        WhatsAppInstance::query()
            ->whereNull('webhook_secret')
            ->orWhere('webhook_secret', '')
            ->get()
            ->each(function (WhatsAppInstance $instance) {
                $instance->update(['webhook_secret' => Str::random(64)]);
            });
    }

    public function down(): void
    {
        // Not reversible — dropping a secret would break signature verification
        // for every instance whose gateway now knows it. Rollback of PROC-018
        // is done by reverting the verifier code, not by clearing the column.
    }
};
