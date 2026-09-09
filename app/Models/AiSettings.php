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
        'tenant_id', 'mode', 'whatsapp_enabled', 'webchat_enabled',
        'reply_language', 'suggestion_count', 'reply_when_claimed',
        'system_prompt', 'escalation_keywords',
        'monthly_token_quota', 'tokens_used_this_period', 'quota_reset_at',
    ];

    protected $casts = [
        'escalation_keywords'    => 'array',
        'whatsapp_enabled'       => 'boolean',
        'webchat_enabled'        => 'boolean',
        'reply_when_claimed'     => 'boolean',
        'monthly_token_quota'    => 'integer',
        'tokens_used_this_period'=> 'integer',
        'suggestion_count'       => 'integer',
        'quota_reset_at'         => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    /** Is the AI allowed to act on this channel at all? */
    public function enabledFor(string $channel): bool
    {
        if ($this->mode === 'off') {
            return false;
        }

        return match ($channel) {
            'whatsapp' => (bool) $this->whatsapp_enabled,
            'webchat'  => (bool) $this->webchat_enabled,
            default    => false,
        };
    }

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
     *
     * CALC-011: rows created by AiController::show or by
     * AiSettingsController::update leave quota_reset_at NULL because they
     * fell through to the column default. The old guard treated NULL as a
     * reason to bail, so those tenants exhausted their 100k tokens once and
     * their AI stayed off forever. Treat NULL as "never rolled" — anchor
     * from updated_at (or now) at the start of that month so the loop below
     * can advance to the current period on the very first pass, then run
     * the same guarded update as the seeded case.
     */
    protected function rolloverIfDue(): void
    {
        $current = $this->quota_reset_at
            ?: ($this->updated_at ?? now())->copy()->startOfMonth();

        if ($current->isFuture()) {
            return;
        }

        // A tenant idle across several boundaries needs more than one month added.
        $next = $current->copy();
        while ($next->isPast()) {
            $next = $next->addMonthNoOverflow();
        }

        // Guarding on the old quota_reset_at means only one of two concurrent
        // workers can win the reset; the loser refreshes instead of double-resetting.
        // The WHERE has to match the DB state — which may be NULL on legacy rows —
        // rather than the value we hydrated from updated_at/now above.
        $query = static::where('tenant_id', $this->tenant_id);
        if ($this->quota_reset_at) {
            $query->where('quota_reset_at', $this->quota_reset_at);
        } else {
            $query->whereNull('quota_reset_at');
        }

        $affected = $query->update([
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
