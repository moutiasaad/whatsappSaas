<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'subscription_status', 'plan_id',
        'trial_ends_at', 'stripe_id', 'settings', 'is_active',
    ];

    protected $casts = [
        'settings'       => 'array',
        'trial_ends_at'  => 'datetime',
        'is_active'      => 'boolean',
    ];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function teams(): HasMany { return $this->hasMany(Team::class); }
    public function whatsappInstances(): HasMany { return $this->hasMany(WhatsAppInstance::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
    public function aiSettings(): HasOne { return $this->hasOne(AiSettings::class); }
    public function knowledgeEntries(): HasMany { return $this->hasMany(KnowledgeEntry::class); }

    public function isActive(): bool
    {
        return $this->is_active && in_array($this->subscription_status, ['trial', 'active']);
    }

    public function hasAiQuotaRemaining(): bool
    {
        $settings = $this->aiSettings;
        return $settings && $settings->tokens_used_this_period < $settings->monthly_token_quota;
    }
}
