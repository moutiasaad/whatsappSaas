<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPayment extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'amount', 'currency',
        'payment_method',
        'stripe_session_id', 'stripe_checkout_url',
        'paypal_order_id', 'paypal_capture_id',
        'status', 'gateway_response', 'paid_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plan(): BelongsTo   { return $this->belongsTo(Plan::class); }

    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isPending(): bool   { return $this->status === 'pending'; }
}
