<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    /**
     * Has first-run setup stopped being the most useful page for an admin?
     *
     * True once they skip it, and once the three things it sets up are all
     * true. Read from the rows themselves rather than a progress flag, so
     * finishing a step on its own page counts for just as much.
     */
    public function onboardingSettled(): bool
    {
        if (data_get($this->settings, 'onboarding.skipped_at')) {
            return true;
        }

        $linked = WhatsAppInstance::where('tenant_id', $this->id)
            ->where('status', 'connected')
            ->exists();

        $knows = KnowledgeEntry::where('tenant_id', $this->id)->exists();

        return $linked && $knows && ($this->aiSettings?->mode ?? 'off') !== 'off';
    }

    protected $fillable = [
        'name', 'slug', 'subscription_status', 'subscription_starts_at', 'subscription_ends_at',
        'plan_id', 'trial_ends_at', 'stripe_id', 'settings', 'timezone', 'is_active',
        'trialed_plan_ids', 'extra_seats', 'archived_at',
    ];

    protected $casts = [
        'plan_id'                 => 'integer',
        'extra_seats'             => 'integer',
        'settings'               => 'array',
        'trial_ends_at'          => 'datetime',
        'subscription_starts_at' => 'datetime',
        'subscription_ends_at'   => 'datetime',
        'archived_at'            => 'datetime',
        'is_active'              => 'boolean',
        'trialed_plan_ids'       => 'array',
    ];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function teams(): HasMany { return $this->hasMany(Team::class); }
    public function customers(): HasMany { return $this->hasMany(Customer::class); }
    public function whatsappInstances(): HasMany { return $this->hasMany(WhatsAppInstance::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
    public function aiSettings(): HasOne { return $this->hasOne(AiSettings::class); }
    public function knowledgeEntries(): HasMany { return $this->hasMany(KnowledgeEntry::class); }

    /**
     * Is the given module included in this tenant's plan? A tenant with no plan
     * gets nothing — same posture as the reservations check this generalises.
     */
    public function planAllows(string $module): bool
    {
        return (bool) $this->plan?->hasModule($module);
    }

    /**
     * Minutes of customer silence before the AI-idle sweep closes a thread.
     *
     * The tenant's own setting wins; NULL means "follow the platform default",
     * which is different from 0 — 0 is the tenant deliberately switching the
     * sweep off for themselves.
     */
    public function autoCloseMinutes(): int
    {
        $own = data_get($this->settings, 'conversations.auto_close_minutes');

        if ($own === null || $own === '') {
            return (int) PlatformSetting::get(
                \App\Console\Commands\CloseIdleAiConversations::SETTING_KEY,
                \App\Console\Commands\CloseIdleAiConversations::DEFAULT_MINUTES,
            );
        }

        return max(0, (int) $own);
    }

    /**
     * Grant purchased agent seats.
     *
     * Atomic increment, not read-modify-write: a webhook racing the browser
     * capture must add up rather than overwrite.
     */
    public function creditSeats(int $seats): void
    {
        if ($seats > 0) {
            $this->increment('extra_seats', $seats);
        }
    }

    /**
     * A plan's free trial is granted once per tenant per plan. Without this a
     * tenant could hop between plans to keep re-triggering trials instead of
     * ever paying.
     */
    public function hasTrialedPlan(int $planId): bool
    {
        return in_array($planId, (array) ($this->trialed_plan_ids ?? []), true);
    }

    public function markPlanTrialed(int $planId): void
    {
        $trialed = (array) ($this->trialed_plan_ids ?? []);
        if (!in_array($planId, $trialed, true)) {
            $trialed[] = $planId;
            $this->trialed_plan_ids = array_values($trialed);
        }
    }

    /**
     * True when the workspace is on a trial, regardless of whether the trial
     * has expired. Callers that care about "trial and still usable" should
     * combine this with `isActive()`. The bare status is what gates feature
     * lockouts (e.g. instance deletion during trial), because the point of
     * that gate is that a trial tenant never gets there in the first place.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial';
    }

    /**
     * Archived tenants are shelved long-term — hidden from the main tenants
     * list, blocked from all access, but data preserved. Distinct from
     * `is_active` (block): a workspace can be blocked AND archived, and
     * "restore" clears only archived_at, leaving is_active untouched.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->forceFill(['archived_at' => now()])->save();
    }

    public function restoreFromArchive(): void
    {
        // Not called restore() because that name collides with Eloquent's
        // SoftDeletes restore(). This model doesn't use SoftDeletes, but
        // future-proofing against someone adding it later.
        $this->forceFill(['archived_at' => null])->save();
    }

    /**
     * Every other tenant linked to this one, regardless of which side of
     * the tenant_links pair this tenant sits on. Each returned Tenant
     * carries a `pivot_link` attribute with the TenantLink row so the UI
     * can render reason + evidence + when-detected without a second lookup.
     *
     * Not a proper Eloquent BelongsToMany because the link table is
     * asymmetric-stored (a<b) but semantically symmetric — the standard
     * pivot machinery would need two relations and a union to cover both
     * sides. Simpler to build the collection by hand here.
     */
    public function linkedTenants()
    {
        $links = TenantLink::query()
            ->with(['tenantA', 'tenantB'])
            ->where(function ($q) {
                $q->where('tenant_a_id', $this->id)->orWhere('tenant_b_id', $this->id);
            })
            ->get();

        return $links->map(function (TenantLink $link) {
            $other = $link->tenant_a_id === $this->id ? $link->tenantB : $link->tenantA;
            if ($other) {
                $other->setAttribute('pivot_link', $link);
            }
            return $other;
        })->filter()->values();
    }

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

    /**
     * Gate for background automation triggered by inbound events —
     * WhatsApp AI auto-reply, WhatsApp reservation bot, web-chat AI.
     *
     * A blocked, archived, cancelled, or trial-expired tenant must not
     * have automations continue to fire on their behalf: the panel is
     * already gated by CheckSubscription for that reason, and the
     * inbound handlers need the same treatment. Message rows still get
     * persisted so history is preserved when the tenant is restored;
     * only the automatic outbound reply is suppressed.
     */
    public function canRunAutomations(): bool
    {
        return $this->isActive() && ! $this->isArchived();
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

    /**
     * The IANA timezone identifier reports and reservation counters bucket
     * dates in for this tenant. Falls back to the app default so an old row
     * with a null/empty column still renders. CALC-010.
     */
    public function effectiveTimezone(): string
    {
        $tz = $this->timezone ?: config('app.timezone', 'UTC');
        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }
}
