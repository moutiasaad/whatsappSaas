<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-007: Widget::isDomainAllowed now fails closed on an empty allowlist, so
// a widget with enabled=true and allowed_domains=[]/null would silently 403
// every visitor. Flip those widgets to enabled=false in the same deploy so
// tenants notice via a broken embed and go to the settings page to add
// domains, rather than seeing a mysterious drop in web-chat traffic.
//
// No rollback: reversing this would re-enable widgets the tenant may have
// intentionally re-configured since. Turning a widget back on is one click
// in /tenant-admin/webchat/settings.
return new class extends Migration {
    public function up(): void
    {
        $updated = DB::table('webchat_widgets')
            ->where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('allowed_domains')
                  ->orWhere('allowed_domains', '[]')
                  ->orWhere('allowed_domains', '');
            })
            ->update(['enabled' => false]);

        if ($updated > 0) {
            fwrite(STDERR, "  UI-007: disabled {$updated} webchat widget(s) with no allowed domains.\n");
        }
    }

    public function down(): void
    {
        // See top comment.
    }
};
