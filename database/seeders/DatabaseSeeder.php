<?php

namespace Database\Seeders;

use App\Models\AiSettings;
use App\Models\Plan;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Plans
        $plan = Plan::firstOrCreate(['name' => 'Starter'], [
            'price_monthly'               => 29,
            'price_annual'                => 290,
            'max_users'                   => 5,
            'max_instances'               => 2,
            'max_conversations_per_month' => 1000,
            'ai_included'                 => true,
            'ai_token_quota'              => 100000,
            'features'                    => ['pool_routing', 'ai_suggestion'],
            'is_active'                   => true,
        ]);

        Plan::firstOrCreate(['name' => 'Growth'], [
            'price_monthly'               => 79,
            'price_annual'                => 790,
            'max_users'                   => 20,
            'max_instances'               => 5,
            'max_conversations_per_month' => 5000,
            'ai_included'                 => true,
            'ai_token_quota'              => 500000,
            'features'                    => ['pool_routing', 'ai_suggestion', 'ai_autonomous', 'knowledge_base'],
            'is_active'                   => true,
        ]);

        // Demo Tenant
        $tenant = Tenant::firstOrCreate(['slug' => 'demo'], [
            'name'                => 'Demo Company',
            'plan_id'             => $plan->id,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
            'is_active'           => true,
        ]);

        // Super Admin (platform owner)
        User::firstOrCreate(['email' => 'super@platform.com'], [
            'name'      => 'Super Admin',
            'password'  => Hash::make('password'),
            'role'      => 'super_admin',
            'is_active' => true,
        ]);

        // Tenant Admin
        $admin = User::updateOrCreate(['email' => 'admin@demo.com'], [
            'name'      => 'Demo Admin',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        // Agents
        $agent1 = User::updateOrCreate(['email' => 'agent1@demo.com'], [
            'name'      => 'Alice Agent',
            'password'  => Hash::make('password'),
            'role'      => 'agent',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $agent2 = User::updateOrCreate(['email' => 'agent2@demo.com'], [
            'name'      => 'Bob Agent',
            'password'  => Hash::make('password'),
            'role'      => 'agent',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $supervisor = User::updateOrCreate(['email' => 'supervisor@demo.com'], [
            'name'      => 'Sam Supervisor',
            'password'  => Hash::make('password'),
            'role'      => 'supervisor',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        // Team
        $team = Team::firstOrCreate(
            ['name' => 'Support', 'tenant_id' => $tenant->id],
            ['is_active' => true]
        );
        $team->users()->syncWithoutDetaching([$agent1->id, $agent2->id, $supervisor->id]);

        // WhatsApp Instance
        WhatsAppInstance::firstOrCreate(
            ['name' => 'Main Support Line', 'tenant_id' => $tenant->id],
            [
                'gateway'      => 'evolution_api',
                'gateway_url'  => 'https://evolution-api.example.com',
                'gateway_api_key' => 'demo-api-key',
                'status'       => 'disconnected',
            ]
        );

        // AI Settings
        AiSettings::firstOrCreate(['tenant_id' => $tenant->id], [
            'mode'                => 'suggestion',
            'system_prompt'       => 'You are a helpful customer support assistant for Demo Company. Be concise, friendly, and professional.',
            'escalation_keywords' => ['human', 'agent', 'supervisor', 'manager', 'urgent'],
            'monthly_token_quota' => 100000,
            'tokens_used_this_period' => 0,
            'quota_reset_at'      => now()->startOfMonth()->addMonth(),
        ]);

        $this->command->info('Seeded successfully!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin',  'super@platform.com', 'password'],
                ['Admin',        'admin@demo.com',      'password'],
                ['Supervisor',   'supervisor@demo.com', 'password'],
                ['Agent',        'agent1@demo.com',     'password'],
                ['Agent',        'agent2@demo.com',     'password'],
            ]
        );
    }
}
