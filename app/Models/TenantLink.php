<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TenantLink — evidence that two tenants may be the same real user.
 *
 * The link is UNDIRECTED: (A links B) === (B links A). The row stores the
 * pair with tenant_a_id < tenant_b_id so the unique index deduplicates.
 * Callers should always use TenantLink::link() rather than a bare
 * TenantLink::create() so this normalisation happens in one place.
 */
class TenantLink extends Model
{
    public const REASON_SHARED_WHATSAPP_INSTANCE = 'shared_whatsapp_instance';

    protected $fillable = [
        'tenant_a_id',
        'tenant_b_id',
        'reason',
        'evidence',
        'first_detected_at',
    ];

    protected $casts = [
        'evidence'          => 'array',
        'first_detected_at' => 'datetime',
    ];

    public function tenantA(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_a_id'); }
    public function tenantB(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_b_id'); }

    /**
     * Idempotently record a link between two tenants.
     *
     * Returns null when the two ids are the same (self-links are never
     * useful — a tenant sharing a phone with itself just means they
     * connected the same instance twice).
     *
     * Adds new evidence to the existing row rather than overwriting it,
     * so a link that started with one phone number and later picks up
     * another still shows both under `evidence.phone_numbers` in the UI.
     */
    public static function link(int $tenantAId, int $tenantBId, string $reason, array $evidence = []): ?self
    {
        if ($tenantAId === $tenantBId) {
            return null;
        }

        // Enforce a < b so the unique index actually catches duplicates.
        // Without this (A,B) and (B,A) would both live.
        [$low, $high] = [
            min($tenantAId, $tenantBId),
            max($tenantAId, $tenantBId),
        ];

        $link = static::firstOrNew([
            'tenant_a_id' => $low,
            'tenant_b_id' => $high,
            'reason'      => $reason,
        ]);

        if (! $link->exists) {
            $link->first_detected_at = now();
            $link->evidence          = $evidence;
            $link->save();
            return $link;
        }

        // Merge the new evidence into the existing blob. For the
        // WhatsApp-instance case that means appending the new phone
        // number to the phone_numbers list without duplication.
        $existing = $link->evidence ?? [];
        $merged   = self::mergeEvidence($existing, $evidence);

        if ($merged !== $existing) {
            $link->evidence = $merged;
            $link->save();
        }

        return $link;
    }

    /**
     * Merge two evidence blobs. For list-valued keys (like phone_numbers)
     * union and de-duplicate; for scalars, the new value wins. Keeps
     * evidence as a growing audit trail rather than a snapshot of the
     * most recent trigger.
     */
    private static function mergeEvidence(array $existing, array $incoming): array
    {
        $out = $existing;

        foreach ($incoming as $key => $value) {
            if (is_array($value) && isset($out[$key]) && is_array($out[$key])) {
                $out[$key] = array_values(array_unique(array_merge($out[$key], $value)));
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
