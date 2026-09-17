<?php

namespace App\Models\Messenger;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Messenger\Conversation
 *
 * Mirrors WebChat\Conversation on purpose so the shared agent inbox
 * (list, claim, close, reassign, escalate) works with zero
 * channel-specific branches. The only real semantic addition is
 * `withinMessagingWindow()`, which is what a channel-agnostic reply
 * controller checks before calling MessengerService::sendText().
 */
class Conversation extends Model
{
    use BelongsToTenant;

    protected $table = 'messenger_conversations';

    /** Sub-24h messaging window (Meta's own limit). */
    public const MESSAGING_WINDOW_HOURS = 24;

    public const STATUS_BOT      = 'bot';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_CLOSED   = 'closed';

    protected $fillable = [
        'uuid', 'tenant_id', 'page_id', 'psid', 'title',
        'status',
        'claimed_by', 'claimed_at', 'closed_by', 'closed_at',
        'last_activity_at', 'last_inbound_at',
        'escalated_at', 'escalation_reason',
        'contact_name', 'contact_avatar_url', 'contact_profile_refreshed_at',
    ];

    protected $casts = [
        'tenant_id'                     => 'integer',
        'page_id'                       => 'integer',
        'claimed_by'                    => 'integer',
        'closed_by'                     => 'integer',
        'claimed_at'                    => 'datetime',
        'closed_at'                     => 'datetime',
        'last_activity_at'              => 'datetime',
        'last_inbound_at'               => 'datetime',
        'escalated_at'                  => 'datetime',
        'contact_profile_refreshed_at'  => 'datetime',
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
        // Cross-tenant lookup deliberately unscoped — the tenant guard is
        // the panel's role middleware, not the model scope. Matches the
        // pattern used in WebChat\Conversation.
        return $this->withoutGlobalScope('tenant')->where($key, $value)->firstOrFail();
    }

    public function tenant(): BelongsTo  { return $this->belongsTo(Tenant::class); }
    public function page(): BelongsTo    { return $this->belongsTo(Page::class, 'page_id'); }
    public function claimer(): BelongsTo { return $this->belongsTo(User::class, 'claimed_by'); }
    public function closer(): BelongsTo  { return $this->belongsTo(User::class, 'closed_by'); }
    public function messages(): HasMany  { return $this->hasMany(Message::class, 'conversation_id'); }
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }

    public function isBot(): bool      { return $this->status === self::STATUS_BOT; }
    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isAssigned(): bool { return $this->status === self::STATUS_ASSIGNED; }
    public function isClosed(): bool   { return $this->status === self::STATUS_CLOSED; }

    public function scopePending($q)              { return $q->where('status', self::STATUS_PENDING); }
    public function scopeAssignedTo($q, int $uid) { return $q->where('status', self::STATUS_ASSIGNED)->where('claimed_by', $uid); }
    public function scopeOpen($q)                 { return $q->where('status', '!=', self::STATUS_CLOSED); }

    /**
     * True when a plain-text reply is allowed without a MESSAGE_TAG.
     *
     * Meta enforces this on their end: sending outside the 24h window
     * with `messaging_type: RESPONSE` returns error code 10 / 200. We
     * check locally first so the composer can go read-only + show a
     * hint, instead of the agent typing a reply that fails on submit.
     *
     * A conversation that never received an inbound message
     * (last_inbound_at is null) is treated as OUT of window — you
     * can't proactively DM a stranger, so a MESSAGE_TAG would be
     * required regardless.
     */
    public function withinMessagingWindow(): bool
    {
        if ($this->last_inbound_at === null) {
            return false;
        }
        return $this->last_inbound_at->gt(Carbon::now()->subHours(self::MESSAGING_WINDOW_HOURS));
    }
}
