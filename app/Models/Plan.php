<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'stripe_price_id_monthly', 'stripe_price_id_annual',
        'price_monthly', 'price_annual', 'max_users', 'max_instances',
        'max_conversations_per_month', 'ai_included', 'ai_message_quota',
        'reservations_enabled', 'features', 'is_active',
        'trial_enabled', 'trial_days', 'modules', 'landing_features',
    ];

    protected $casts = [
        'price_monthly'               => 'decimal:2',
        'price_annual'                => 'decimal:2',
        'max_users'                   => 'integer',
        'max_instances'               => 'integer',
        'max_conversations_per_month' => 'integer',
        'ai_included'                 => 'boolean',
        'ai_message_quota'            => 'integer',
        'reservations_enabled'        => 'boolean',
        'features'                    => 'array',
        'is_active'                   => 'boolean',
        'trial_enabled'               => 'boolean',
        'trial_days'                  => 'integer',
        'modules'                     => 'array',
        'landing_features'            => 'array',
    ];

    public function tenants(): HasMany { return $this->hasMany(Tenant::class); }

    /**
     * Does this plan grant the given module?
     *
     * NULL `modules` means the row predates the entitlement list — treat it as
     * granting everything rather than silently locking existing tenants out of
     * pages they have always had.
     */
    public function hasModule(string $module): bool
    {
        if ($this->modules === null) {
            return true;
        }

        if (config("plan_modules.$module.always")) {
            return true;
        }

        return in_array($module, $this->modules, true);
    }

    /**
     * Free-trial length in days, or 0 when this plan bills from day one.
     * A trial that is enabled but has no explicit length falls back to the
     * platform default so the field can be left blank.
     */
    public function trialDays(): int
    {
        if (!$this->trial_enabled) {
            return 0;
        }

        return max(0, (int) ($this->trial_days ?: config('app.trial_days', 7)));
    }

    public function hasTrial(): bool
    {
        return $this->trialDays() > 0;
    }

    /**
     * The cheapest sellable plan that grants $module — what the locked-module
     * upgrade prompt offers. Returns null when no active plan includes it, so
     * the caller can fall back to the billing page rather than a dead CTA.
     */
    public static function cheapestWithModule(string $module): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->orderBy('id')
            ->get()
            ->first(fn (self $plan) => $plan->hasModule($module));
    }

    /**
     * Every key the landing-page picker can offer for this plan: the built-in
     * attributes plus one `module:<key>` entry per module the plan grants.
     * A module the plan does not include is never offered, so a card cannot
     * advertise something the tenant would not get.
     */
    public function landingAttributeKeys(): array
    {
        $keys = array_keys(config('plan_landing_attributes', []));

        foreach (array_keys(config('plan_modules', [])) as $module) {
            if ($this->hasModule($module)) {
                $keys[] = 'module:' . $module;
            }
        }

        return $keys;
    }

    /**
     * The curated list actually shown on the landing card, in the order the
     * catalogue defines, with anything stale (a module since removed from the
     * plan) filtered out. NULL means "never curated" — callers fall back to the
     * legacy rendering rather than showing a bare card.
     */
    public function landingAttributes(): ?array
    {
        if ($this->landing_features === null) {
            return null;
        }

        $selected = (array) $this->landing_features;

        return array_values(array_filter(
            $this->landingAttributeKeys(),
            fn (string $key) => in_array($key, $selected, true)
        ));
    }
}
