<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-plan free trial + module entitlements + a manual pick of which
     * attributes the landing page advertises.
     *
     * Existing rows are backfilled so nothing changes behaviourally on deploy:
     *  - trial: every plan inherits the old global TRIAL_DAYS, which is what
     *    RegisterController hardcoded before.
     *  - modules: everything the plan already implied is turned on, so no live
     *    tenant loses a page the moment the gate starts being enforced.
     *  - landing_features stays null, which the landing page reads as
     *    "no manual pick yet" and renders the way it always has.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('trial_enabled')->default(false)->after('price_annual');
            $table->unsignedSmallInteger('trial_days')->nullable()->after('trial_enabled');
            $table->json('modules')->nullable()->after('reservations_enabled');
            $table->json('landing_features')->nullable()->after('features');
        });

        $defaultTrialDays = (int) config('app.trial_days', 7);
        $catalogue        = array_keys(config('plan_modules', []));

        Plan::query()->get()->each(function (Plan $plan) use ($defaultTrialDays, $catalogue) {
            $modules = array_values(array_filter($catalogue, function (string $key) use ($plan) {
                // The two entitlements that already existed keep their meaning;
                // everything else was unconditionally available before, so it
                // stays available.
                return match ($key) {
                    'reservations' => (bool) $plan->reservations_enabled,
                    'ai_agent'     => (bool) $plan->ai_included,
                    default        => true,
                };
            }));

            $plan->forceFill([
                'trial_enabled' => true,
                'trial_days'    => $defaultTrialDays,
                'modules'       => $modules,
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['trial_enabled', 'trial_days', 'modules', 'landing_features']);
        });
    }
};
