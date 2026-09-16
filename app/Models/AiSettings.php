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
        'tenant_id', 'mode', 'mode_before_auto_off', 'whatsapp_enabled', 'webchat_enabled',
        'reply_language', 'suggestion_count', 'reply_when_claimed',
        'system_prompt', 'escalation_keywords',
        'monthly_message_quota', 'ai_messages_used_this_period', 'quota_reset_at',
        'extra_message_credits',
    ];

    protected $casts = [
        'escalation_keywords'    => 'array',
        'whatsapp_enabled'       => 'boolean',
        'webchat_enabled'        => 'boolean',
        'reply_when_claimed'     => 'boolean',
        'monthly_message_quota'       => 'integer',
        'ai_messages_used_this_period'=> 'integer',
        'extra_message_credits'       => 'integer',
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

    /**
     * The cap actually in force right now.
     *
     * A tenant on trial is capped at `config('app.trial_ai_message_quota')`
     * regardless of what the picked plan would normally allow — the trial is
     * a bounded demo, not the plan's real allowance. Once the tenant converts
     * to `active`, this transparently returns to `monthly_message_quota` (the
     * plan-synced value stored on the row), so no data migration is needed on
     * conversion.
     *
     * null = unlimited, 0 = off, positive N = hard cap of N replies.
     */
    public function effectiveQuota(): ?int
    {
        if (optional($this->tenant)->isOnTrial()) {
            return (int) config('app.trial_ai_message_quota', 100);
        }

        return $this->monthly_message_quota;
    }

    public function hasQuota(): bool
    {
        // Unified semantics (see migration 2026_09_10_090000):
        //   null       = unlimited  → always has quota
        //   0          = AI off      → never has quota
        //   positive N = hard cap of N AI replies per period
        $quota = $this->effectiveQuota();

        if ($quota === null) return true;
        if ($quota === 0)    return false;

        $this->rolloverIfDue();

        if ($this->ai_messages_used_this_period < $quota) {
            return true;
        }

        // Trial cap is a hard ceiling: purchased top-ups don't extend it —
        // the demo shouldn't be growable. Paid tenants fall through to
        // credits as before.
        if (optional($this->tenant)->isOnTrial()) {
            return false;
        }

        return $this->extra_message_credits > 0;
    }

    /** Is the tenant currently answering out of purchased credits? */
    public function isOnPurchasedCredits(): bool
    {
        // Trial tenants can't spend credits (the cap is hard). Same short-circuit
        // for unlimited / off — no allowance boundary to cross.
        if (optional($this->tenant)->isOnTrial()) {
            return false;
        }

        if ($this->monthly_message_quota === null || $this->monthly_message_quota === 0) {
            return false;
        }

        return $this->ai_messages_used_this_period >= $this->monthly_message_quota
            && $this->extra_message_credits > 0;
    }

    /**
     * Grant purchased AI messages.
     *
     * Atomic increment rather than read-modify-write: two packs paid for at the
     * same moment (an IPN racing a capture callback) must add up, not overwrite.
     */
    public function creditMessages(int $messages): void
    {
        if ($messages <= 0) {
            return;
        }

        $this->increment('extra_message_credits', $messages);

        // A top-up pack has to actually resume AI, otherwise the tenant
        // has fresh paid credits sitting on a mode='off' row and the
        // customer's next message goes unanswered. If quota exhaustion
        // was what flipped mode to off, restore it now that credits give
        // hasQuota() something to hand out.
        $this->restoreModeAfterAutoOff();
    }

    /**
     * Bill one AI reply against the period allowance.
     *
     * Called once per generated reply — an unlimited tenant is still counted so
     * the usage figure on the AI settings page stays truthful.
     */
    public function consumeReply(int $count = 1): void
    {
        $this->rolloverIfDue();

        // Trial: hard-count only, never touch purchased credits — they're
        // inert while on trial. Falls through the plan-vs-credit split below.
        if (optional($this->tenant)->isOnTrial()) {
            $this->increment('ai_messages_used_this_period', $count);
            return;
        }

        // Spend the monthly allowance first; only the overflow touches
        // purchased credits, so a top-up is never burnt while plan messages
        // are still available. Unlimited (null) and off (0) tenants have no
        // allowance boundary to cross, so they only ever bump the counter.
        $quota = $this->monthly_message_quota;

        if ($quota !== null && $quota > 0) {
            $free      = max(0, $quota - $this->ai_messages_used_this_period);
            $fromPlan  = min($count, $free);
            $fromCredit= min($count - $fromPlan, $this->extra_message_credits);

            if ($fromCredit > 0) {
                $this->decrement('extra_message_credits', $fromCredit);
            }

            // Usage still counts every reply, including credit-funded ones, so
            // the usage figure on the AI settings page stays truthful.
            $this->increment('ai_messages_used_this_period', $count);

            return;
        }

        $this->increment('ai_messages_used_this_period', $count);
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
     * reason to bail, so those tenants exhausted their allowance once and
     * their AI stayed off forever. Treat NULL as "never rolled" — anchor
     * from updated_at (or now) at the start of that month so the loop below
     * can advance to the current period on the very first pass, then run
     * the same guarded update as the seeded case.
     */
    protected function rolloverIfDue(): void
    {
        // Trial tenants get one allowance for the whole trial, not one per
        // calendar month. Skipping rollover here means a trial that crosses a
        // month boundary (register Aug 30, trial ends Sep 6) still totals 100
        // AI replies instead of resetting to 100 fresh on Sep 1.
        if (optional($this->tenant)->isOnTrial()) {
            return;
        }

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
            'ai_messages_used_this_period' => 0,
            'quota_reset_at'               => $next,
        ]);

        if ($affected) {
            $this->ai_messages_used_this_period = 0;
            $this->quota_reset_at               = $next;

            // Fresh period == fresh allowance: if the last one ran out
            // and disableAndNotify auto-flipped mode to off, restore it.
            $this->restoreModeAfterAutoOff();
        } else {
            $this->refresh();
        }
    }

    /**
     * Undo the mode='off' that disableAndNotify wrote when quota ran out,
     * once the tenant has a live allowance again. Silent no-op unless
     * mode_before_auto_off is set — an admin who manually turned AI off
     * (mode='off' with no shadow value) stays off.
     *
     * Called from creditMessages (top-up pack settled) and rolloverIfDue
     * (period boundary crossed). Not called at check time — hasQuota is
     * on the reply path, and doing an UPDATE from a read would fight
     * with itself under concurrency.
     */
    private function restoreModeAfterAutoOff(): void
    {
        if ($this->mode !== 'off' || ! $this->mode_before_auto_off) {
            return;
        }

        // hasQuota() internally runs rolloverIfDue, but the reset-branch
        // has already set quota_reset_at to a future value by the time we
        // reach here, so rolloverIfDue short-circuits and there is no
        // recursion. Trial cap still returns false hard — no restore on
        // an in-trial tenant with a hard-capped counter, credits inert.
        if (! $this->hasQuota()) {
            return;
        }

        $previous = $this->mode_before_auto_off;

        $this->update([
            'mode'                 => $previous,
            'mode_before_auto_off' => null,
        ]);
    }

    public function remainingQuota(): ?int
    {
        $quota = $this->effectiveQuota();

        // null = unlimited → no remainder to report; caller renders "∞".
        if ($quota === null) return null;

        // Trial cap is hard — credits are inert. Paid tenants get the credit
        // top-up added on top so the number they see reflects everything they
        // can actually spend.
        if (optional($this->tenant)->isOnTrial()) {
            return max(0, $quota - $this->ai_messages_used_this_period);
        }

        return max(0, $quota - $this->ai_messages_used_this_period)
            + $this->extra_message_credits;
    }

    public function quotaPercentage(): int
    {
        $quota = $this->effectiveQuota();

        // Unlimited → nothing consumed relative to infinity → 0%.
        // OFF       → the bar is by definition full (all "N of 0" used).
        if ($quota === null) return 0;
        if ($quota === 0)    return 100;
        return (int) round(($this->ai_messages_used_this_period / $quota) * 100);
    }
}
