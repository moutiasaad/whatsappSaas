<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'tenant_id', 'slot_id', 'customer_id', 'conversation_id',
        'customer_phone', 'customer_name', 'customer_notes',
        'reservation_date', 'start_time', 'end_time', 'status', 'booked_at',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'booked_at'        => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function slot(): BelongsTo { return $this->belongsTo(AvailabilitySlot::class, 'slot_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
}
