<?php

namespace Database\Seeders;

use App\Models\AiSettings;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * The three public plans shown on the landing page.
 *
 * Rows are matched by id so existing tenants keep their plan_id: 1 = Starter,
 * 2 = Growth (the middle card the landing page marks "most popular"), 3 = Scale.
 * The landing page orders cards by id, so the ids double as the price ladder.
 *
 * `max_conversations_per_month` is 0 on every plan — the column is NOT NULL, and
 * 0 is the "no cap" value the card renderer skips. Capping conversations would
 * contradict the unlimited-conversations promise printed on every card.
 *
 * Annual prices stay at 0: annual billing is not wired end to end (see the
 * CALC-003 note on the landing view), and a non-zero value here would surface a
 * Monthly/Annual toggle for a cycle checkout cannot sell.
 *
 * `max_instances` is 1 everywhere and no card advertises it: TenantQuota caps
 * every tenant at one WhatsApp instance from a constant, ignoring this column
 * entirely, so a higher number here would be a promise the product breaks.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->plans() as $id => $attributes) {
            $plan = Plan::find($id) ?? new Plan();
            $plan->id = $id;

            $quotaChanged = $plan->exists
                && $plan->ai_message_quota !== $attributes['ai_message_quota'];

            $plan->forceFill($attributes)->save();

            // Mirror the super admin's "overwrite every tenant" behaviour so
            // tenants already on the plan move to its new AI allowance.
            if ($quotaChanged) {
                $tenantIds = Tenant::where('plan_id', $plan->id)->pluck('id');

                if ($tenantIds->isNotEmpty()) {
                    AiSettings::whereIn('tenant_id', $tenantIds)
                        ->update(['monthly_message_quota' => $plan->ai_message_quota]);
                }
            }
        }
    }

    private function plans(): array
    {
        return [
            // ── $19 — one small team, one number, AI answering. No teams
            //    module, and the only plan that opens with a free trial.
            1 => [
                'name'                        => 'Starter',
                'price_monthly'               => 19,
                'price_annual'                => 0,
                'max_users'                   => 3,
                'max_instances'               => 1,
                'max_conversations_per_month' => 0,
                'ai_included'                 => true,
                'ai_message_quota'            => 1000,
                'reservations_enabled'        => false,
                'trial_enabled'               => true,
                'trial_days'                  => 7,
                'is_active'                   => true,
                'modules' => [
                    'whatsapp',
                    'ai_agent',
                    'knowledge_base',
                    'saved_replies',
                ],
                'features'         => ['ai_suggestion', 'knowledge_base'],
                'landing_features' => [
                    'trial',
                    'max_users',
                    'module:ai_agent',
                    'module:knowledge_base',
                ],
            ],

            // ── $39 — the middle card. Adds the live-chat widget, teams and
            //    reports on top of everything Starter answers with.
            2 => [
                'name'                        => 'Growth',
                'price_monthly'               => 39,
                'price_annual'                => 0,
                'max_users'                   => 5,
                'max_instances'               => 1,
                'max_conversations_per_month' => 0,
                'ai_included'                 => true,
                'ai_message_quota'            => 5000,
                'reservations_enabled'        => false,
                'trial_enabled'               => false,
                'trial_days'                  => null,
                'is_active'                   => true,
                'modules' => [
                    'whatsapp',
                    'webchat',
                    'ai_agent',
                    'knowledge_base',
                    'saved_replies',
                    'teams',
                    'reports',
                ],
                'features'         => ['pool_routing', 'ai_suggestion', 'ai_autonomous', 'knowledge_base'],
                'landing_features' => [
                    'max_users',
                    'module:webchat',
                    'module:ai_agent',
                    'module:teams',
                    'module:reports',
                ],
            ],

            // ── $79 — every module the platform has. The card advertises only
            //    the headline differentiators; the rest is on the plan page.
            3 => [
                'name'                        => 'Scale',
                // Temporarily $1 for live-payment testing — restore to 79
                // before launch.
                'price_monthly'               => 1,
                'price_annual'                => 0,
                'max_users'                   => 25,
                'max_instances'               => 1,
                'max_conversations_per_month' => 0,
                'ai_included'                 => true,
                // Not unlimited: AI messages are metered so extra bundles can be
                // sold as an in-app add-on rather than given away with the tier.
                'ai_message_quota'            => 20000,
                'reservations_enabled'        => true,
                'trial_enabled'               => false,
                'trial_days'                  => null,
                'is_active'                   => true,
                'modules' => array_keys(config('plan_modules', [])),
                'features'         => ['pool_routing', 'ai_suggestion', 'ai_autonomous', 'knowledge_base'],
                'landing_features' => [
                    'max_users',
                    'ai_messages',
                    'module:webchat',
                    'module:reservations',
                    'module:otp_service',
                    'module:api_access',
                ],
            ],
        ];
    }
}
