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
        'tenant_id', 'mode', 'reply_language', 'suggestion_count',
        'system_prompt', 'escalation_keywords',
        'monthly_token_quota', 'tokens_used_this_period', 'quota_reset_at',
    ];

    protected $casts = [
        'escalation_keywords'    => 'array',
        'monthly_token_quota'    => 'integer',
        'tokens_used_this_period'=> 'integer',
        'suggestion_count'       => 'integer',
        'quota_reset_at'         => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function hasQuota(): bool
    {
        // 0 means unlimited
        if ($this->monthly_token_quota === 0) return true;

        $this->rolloverIfDue();

        return $this->tokens_used_this_period < $this->monthly_token_quota;
    }

    /**
     * Start a new quota period once quota_reset_at has passed.
     *
     * Without this the "monthly" quota was a lifetime allowance: the counter was
     * only ever incremented, so an exhausted tenant stayed exhausted forever.
     */
    protected function rolloverIfDue(): void
    {
        if (!$this->quota_reset_at || $this->quota_reset_at->isFuture()) {
            return;
        }

        // A tenant idle across several boundaries needs more than one month added.
        $next = $this->quota_reset_at->copy();
        while ($next->isPast()) {
            $next = $next->addMonthNoOverflow();
        }

        // Guarding on the old quota_reset_at means only one of two concurrent
        // workers can win the reset; the loser refreshes instead of double-resetting.
        $affected = static::where('tenant_id', $this->tenant_id)
            ->where('quota_reset_at', $this->quota_reset_at)
            ->update([
                'tokens_used_this_period' => 0,
                'quota_reset_at'          => $next,
            ]);

        if ($affected) {
            $this->tokens_used_this_period = 0;
            $this->quota_reset_at          = $next;
        } else {
            $this->refresh();
        }
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
