<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `tenant_links` — records evidence that two separate tenants may be the
 * same real user. First reason ever recorded is "shared_whatsapp_instance":
 * two tenants connected the same phone number, which for a trial-abuse
 * detector is a strong signal that someone is spinning up multiple free
 * trials from the same WhatsApp account.
 *
 * Normalisation: tenant_a_id < tenant_b_id is enforced by the app layer
 * (TenantLink::link) so the unique index actually deduplicates. Storing
 * the pair without normalisation would let (A,B) and (B,A) both live and
 * the super-admin would see two rows for the same relationship.
 *
 * Evidence is a JSON blob because different reasons carry different shapes
 * — phone_number for the WhatsApp case, potentially email domain / payment
 * card fingerprint / signup IP for future reasons.
 *
 * ON DELETE CASCADE: a tenant deletion drops its link records. That's
 * intentional — if the tenant is gone, the link's other half is dangling
 * anyway.
 *
 * Backfill: on install, scan existing whatsapp_instances for phone-number
 * collisions across tenants and materialise those links. Otherwise the
 * "linked accounts" UI would falsely say "no links found" on data that
 * already has abuse patterns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_a_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_b_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->json('evidence')->nullable();
            $table->timestamp('first_detected_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_a_id', 'tenant_b_id', 'reason'], 'tenant_links_pair_reason_unique');
            $table->index('reason');
        });

        // Backfill: any whatsapp_instances phone_number shared by two or
        // more tenants gets a link row. Uses raw SQL because it's a
        // one-off data heal, not application logic — no need to route it
        // through the TenantLink model.
        $duplicates = DB::table('whatsapp_instances')
            ->select('phone_number')
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->groupBy('phone_number')
            ->havingRaw('COUNT(DISTINCT tenant_id) > 1')
            ->pluck('phone_number');

        $now = now();

        foreach ($duplicates as $phone) {
            $tenantIds = DB::table('whatsapp_instances')
                ->where('phone_number', $phone)
                ->distinct()
                ->pluck('tenant_id')
                ->sort()
                ->values()
                ->all();

            // Every unordered pair of tenants that share this phone.
            for ($i = 0; $i < count($tenantIds); $i++) {
                for ($j = $i + 1; $j < count($tenantIds); $j++) {
                    DB::table('tenant_links')->updateOrInsert(
                        [
                            'tenant_a_id' => $tenantIds[$i],
                            'tenant_b_id' => $tenantIds[$j],
                            'reason'      => 'shared_whatsapp_instance',
                        ],
                        [
                            'evidence'          => json_encode(['phone_numbers' => [$phone]]),
                            'first_detected_at' => $now,
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_links');
    }
};
