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
        if ($this->subscription_ends_at && $this->subscription_ends_at->isPast()) return false;
        return true;
    }

    public function isExpired(): bool
    {
        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->subscription_ends_at) return null;
        $diff = (int) now()->diffInDays($this->subscription_ends_at, false);
        return $diff;
    }

    public function hasAiQuotaRemaining(): bool
    {
        $settings = $this->aiSettings;
        return $settings && $settings->tokens_used_this_period < $settings->monthly_token_quota;
    }
}
