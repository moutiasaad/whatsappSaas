<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'subscription_status', 'subscription_starts_at', 'subscription_ends_at',
        'plan_id', 'trial_ends_at', 'stripe_id', 'settings', 'is_active',
    ];

    protected $casts = [
        'plan_id'                 => 'integer',
        'settings'               => 'array',
        'trial_ends_at'          => 'datetime',
        'subscription_starts_at' => 'datetime',
        'subscription_ends_at'   => 'datetime',
        'is_active'              => 'boolean',
    ];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function teams(): HasMany { return $this->hasMany(Team::class); }
    public function customers(): HasMany { return $this->hasMany(Customer::class); }
    public function whatsappInstances(): HasMany { return $this->hasMany(WhatsAppInstance::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
    public function aiSettings(): HasOne { return $this->hasOne(AiSettings::class); }
    public function knowledgeEntries(): HasMany { return $this->hasMany(KnowledgeEntry::class); }

    public function isActive(): bool
    {
        if (!$this->is_active) return false;
        if (!in_array($this->subscription_status, ['active', 'trial'], true)) return false;

        // A trial is bounded by trial_ends_at; a paid subscription by
        // subscription_ends_at. Conflating them blocks live trials whose
        // stale subscription_ends_at was pre-populated by the edit form.
        $endsAt = $this->subscription_status === 'trial'
            ? $this->trial_ends_at
            : $this->subscription_ends_at;

        return !$endsAt || $endsAt->isFuture();
    }

    public function isExpired(): bool
    {
        $endsAt = $this->subscription_status === 'trial'
            ? $this->trial_ends_at
            : $this->subscription_ends_at;

        return $endsAt !== null && $endsAt->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        $endsAt = $this->subscription_status === 'trial'
            ? $this->trial_ends_at
            : $this->subscription_ends_at;

        if (!$endsAt) return null;
        return (int) now()->diffInDays($endsAt, false);
    }

    public function hasAiQuotaRemaining(): bool
    {
        // Delegate to AiSettings::hasQuota() so the tenant-level check honours
        // the unified null=unlimited / 0=off semantic instead of doing a raw
        // numeric compare that treats null as 0.
        return (bool) $this->aiSettings?->hasQuota();
    }
}
