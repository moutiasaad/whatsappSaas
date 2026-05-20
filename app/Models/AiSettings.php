<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSettings extends Model
{
    protected $table = 'ai_settings';
    protected $primaryKey = 'tenant_id';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'mode', 'system_prompt', 'escalation_keywords',
        'monthly_token_quota', 'tokens_used_this_period', 'quota_reset_at',
    ];

    protected $casts = [
        'escalation_keywords' => 'array',
        'monthly_token_quota' => 'integer',
        'tokens_used_this_period' => 'integer',
        'quota_reset_at'      => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function hasQuota(): bool
    {
        return $this->tokens_used_this_period < $this->monthly_token_quota;
    }

    public function remainingQuota(): int
    {
        return max(0, $this->monthly_token_quota - $this->tokens_used_this_period);
    }

    public function quotaPercentage(): int
    {
        if ($this->monthly_token_quota === 0) return 100;
        return (int) round(($this->tokens_used_this_period / $this->monthly_token_quota) * 100);
    }
}
