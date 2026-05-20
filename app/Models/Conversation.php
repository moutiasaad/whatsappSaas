<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'instance_id', 'customer_id', 'team_id', 'state',
        'owner_agent_id', 'claimed_at', 'closed_at', 'ai_suspended',
        'last_message_at', 'last_message_preview', 'unread_count',
    ];

    protected $casts = [
        'claimed_at'      => 'datetime',
        'closed_at'       => 'datetime',
        'last_message_at' => 'datetime',
        'ai_suspended'    => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function instance(): BelongsTo { return $this->belongsTo(WhatsAppInstance::class, 'instance_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function ownerAgent(): BelongsTo { return $this->belongsTo(User::class, 'owner_agent_id'); }
    public function messages(): HasMany { return $this->hasMany(Message::class)->orderBy('created_at'); }
    public function events(): HasMany { return $this->hasMany(ConversationEvent::class)->orderBy('created_at'); }

    public function isPool(): bool { return $this->state === 'pool'; }
    public function isClaimed(): bool { return $this->state === 'claimed'; }
    public function isClosed(): bool { return $this->state === 'closed'; }
    public function isAiEligible(): bool { return $this->state === 'pool' && !$this->ai_suspended; }

    public function scopePool($q) { return $q->where('state', 'pool'); }
    public function scopeClaimed($q) { return $q->where('state', 'claimed'); }
    public function scopeClosed($q) { return $q->where('state', 'closed'); }
}
