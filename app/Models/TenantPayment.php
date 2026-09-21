<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPayment extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'kind', 'amount', 'currency', 'base_amount_usd',
        'payment_method', 'metadata',
        'stripe_session_id', 'stripe_checkout_url',
        'paypal_order_id', 'paypal_capture_id',
        'status', 'gateway_response', 'paid_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'base_amount_usd'  => 'decimal:2',
        'gateway_response' => 'array',
        'metadata'         => 'array',
        'paid_at'          => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plan(): BelongsTo   { return $this->belongsTo(Plan::class); }

    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isPending(): bool   { return $this->status === 'pending'; }

    /**
     * A one-off AI message top-up rather than a plan subscription.
     *
     * Rows written before the `kind` column existed default to 'subscription',
     * which is what they were — no backfill needed.
     */
    public function isAiPack(): bool { return $this->kind === self::KIND_AI_PACK; }

    public const KIND_SUBSCRIPTION = 'subscription';
    public const KIND_AI_PACK      = 'ai_pack';
    public const KIND_SEAT_PACK    = 'seat_pack';
    /** Plan and/or add-ons bought together in one checkout. */
    public const KIND_CART         = 'cart';

    public function isCart(): bool { return $this->kind === self::KIND_CART; }

    /** A one-off purchase of extra agent seats. */
    public function isSeatPack(): bool { return $this->kind === self::KIND_SEAT_PACK; }

    /** Seats this payment grants, or 0 when it is not a seat purchase. */
    public function packSeats(): int
    {
        return ($this->isSeatPack() || $this->isCart())
            ? (int) ($this->metadata['seats'] ?? 0)
            : 0;
    }

    /** Messages this payment grants, or 0 when it is not a pack purchase. */
    public function packMessages(): int
    {
        return ($this->isAiPack() || $this->isCart())
            ? (int) ($this->metadata['messages'] ?? 0)
            : 0;
    }
}
