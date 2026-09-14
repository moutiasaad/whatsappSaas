<?php

namespace App\Models\WebChat;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToTenant;

    protected $table = 'webchat_conversations';

    public const STATUS_BOT      = 'bot';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_CLOSED   = 'closed';

    protected $fillable = [
        'uuid', 'tenant_id', 'widget_id', 'visitor_id', 'status', 'title',
        'claimed_by', 'claimed_at', 'closed_by', 'closed_at', 'last_activity_at',
        'escalated_at', 'escalation_reason',
        'visitor_name', 'visitor_email', 'page_url', 'referrer', 'user_agent', 'ip',
    ];

    protected $casts = [
        'tenant_id'        => 'integer',
        'widget_id'        => 'integer',
        'visitor_id'       => 'integer',
        'claimed_by'       => 'integer',
        'closed_by'        => 'integer',
        'claimed_at'       => 'datetime',
        'closed_at'        => 'datetime',
        'last_activity_at' => 'datetime',
        'escalated_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_BOT;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null): ?static
    {
        $key = $field ?? $this->getRouteKeyName();
        return $this->withoutGlobalScope('tenant')->where($key, $value)->firstOrFail();
    }

    public function tenant(): BelongsTo   { return $this->belongsTo(Tenant::class); }
    public function widget(): BelongsTo   { return $this->belongsTo(Widget::class, 'widget_id'); }
    public function visitor(): BelongsTo  { return $this->belongsTo(Visitor::class, 'visitor_id'); }
    public function claimer(): BelongsTo  { return $this->belongsTo(User::class, 'claimed_by'); }
    public function closer(): BelongsTo   { return $this->belongsTo(User::class, 'closed_by'); }
    public function messages(): HasMany   { return $this->hasMany(Message::class, 'conversation_id'); }
    public function latestMessage(): HasOne { return $this->hasOne(Message::class, 'conversation_id')->latestOfMany(); }

    public function isBot(): bool      { return $this->status === self::STATUS_BOT; }
    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isAssigned(): bool { return $this->status === self::STATUS_ASSIGNED; }
    public function isClosed(): bool   { return $this->status === self::STATUS_CLOSED; }

    public function scopePending($q)              { return $q->where('status', self::STATUS_PENDING); }
    public function scopeAssignedTo($q, int $uid) { return $q->where('status', self::STATUS_ASSIGNED)->where('claimed_by', $uid); }
    public function scopeOpen($q)                 { return $q->where('status', '!=', self::STATUS_CLOSED); }
}
