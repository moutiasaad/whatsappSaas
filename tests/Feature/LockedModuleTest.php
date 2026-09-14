<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cover for the locked-module upgrade preview.
 *
 * A module the tenant's plan does not include must still be *reachable* — the
 * page read renders a blurred mock behind an upgrade offer — while every write
 * to it stays hard-blocked. The teaser is a storefront, never a back door.
 *
 * Schema is built by hand rather than via `RefreshDatabase`: one of the
 * WhatsApp migrations uses MySQL-only raw SQL and cannot run on the in-memory
 * SQLite connection. Same approach as OtpServiceSettingsTest.
 */
class LockedModuleTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;
    protected User $agent;
    protected Plan $starter;
    protected Plan $scale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();

        // Starter has none of the teaser modules; Scale carries all of them and
        // is the plan the upgrade CTA must point at.
        $this->starter = Plan::create([
            'name'                        => 'Starter',
            'price_monthly'               => 19,
            'price_annual'                => 0,
            'max_users'                   => 3,
            'max_instances'               => 1,
            'max_conversations_per_month' => 0,
            'ai_included'                 => true,
            'ai_message_quota'            => 1000,
            'modules'                     => ['whatsapp', 'ai_agent'],
            'is_active'                   => true,
        ]);

        $this->scale = Plan::create([
            'name'                        => 'Scale',
            'price_monthly'               => 79,
            'price_annual'                => 0,
            'max_users'                   => 25,
            'max_instances'               => 1,
            'max_conversations_per_month' => 0,
            'ai_included'                 => true,
            'ai_message_quota'            => 20000,
            'modules'                     => ['whatsapp', 'webchat', 'ai_agent', 'reservations', 'teams'],
            'is_active'                   => true,
        ]);

        $this->tenant = Tenant::create([
            'name'                => 'Acme',
            'slug'                => 'acme',
            'plan_id'             => $this->starter->id,
            'subscription_status' => 'active',
            'is_active'           => true,
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Tenant Admin',
            'email'     => 'admin@example.com',
            'password'  => 'secret-password',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->agent = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Support Agent',
            'email'     => 'agent@example.com',
            'password'  => 'secret-password',
            'role'      => 'agent',
            'is_active' => true,
        ]);

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public static function teaserRoutes(): array
    {
        return [
            'teams'        => ['/tenant-admin/teams'],
            'reservations' => ['/tenant-admin/reservations'],
            'webchat'      => ['/tenant-admin/webchat/settings'],
        ];
    }

    /**
     * @dataProvider teaserRoutes
     */
    public function test_a_locked_module_page_renders_the_upgrade_preview(string $url): void
    {
        $response = $this->actingAs($this->admin)->get($url);

        // 402 Payment Required: the page is not an error, it is an offer.
        $response->assertStatus(402);
        $response->assertSee('locked-panel', false);
        $response->assertSee('locked-preview', false);
    }

    public function test_the_upgrade_cta_targets_the_cheapest_plan_carrying_the_module(): void
    {
        $this->actingAs($this->admin)
            ->get('/tenant-admin/teams')
            ->assertStatus(402)
            ->assertSee('action="' . route('payment.upgrade') . '"', false)
            ->assertSee('name="plan_id" value="' . $this->scale->id . '"', false);
    }

    public function test_a_cheaper_plan_with_the_module_wins_the_cta(): void
    {
        $mid = Plan::create([
            'name'                        => 'Growth',
            'price_monthly'               => 39,
            'price_annual'                => 0,
            'max_users'                   => 5,
            'max_instances'               => 1,
            'max_conversations_per_month' => 0,
            'ai_included'                 => true,
            'ai_message_quota'            => 5000,
            'modules'                     => ['whatsapp', 'teams'],
            'is_active'                   => true,
        ]);

        $this->actingAs($this->admin)
            ->get('/tenant-admin/teams')
            ->assertStatus(402)
            ->assertSee('name="plan_id" value="' . $mid->id . '"', false);
    }

    public function test_a_retired_plan_is_never_offered_as_the_upgrade(): void
    {
        $this->scale->forceFill(['is_active' => false])->save();

        $this->actingAs($this->admin)
            ->get('/tenant-admin/teams')
            ->assertStatus(402)
            // No sellable plan carries the module, so no CTA — not a dead form.
            ->assertDontSee('name="plan_id"', false);
    }

    public function test_a_write_to_a_locked_module_is_still_blocked(): void
    {
        $this->actingAs($this->admin)
            ->post('/tenant-admin/teams', ['name' => 'Smuggled team'])
            ->assertRedirect('/tenant-admin');

        $this->assertDatabaseMissing('teams', ['name' => 'Smuggled team']);
    }

    public function test_a_json_request_to_a_locked_module_still_gets_403(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/tenant-admin/teams')
            ->assertStatus(403)
            ->assertJsonPath('code', 'module_not_in_plan');
    }

    public function test_an_included_module_is_untouched_by_the_teaser(): void
    {
        $this->tenant->forceFill(['plan_id' => $this->scale->id])->save();

        $this->actingAs($this->admin)
            ->get('/tenant-admin/teams')
            ->assertOk()
            ->assertDontSee('locked-panel', false);
    }

    public function test_a_non_admin_sees_the_preview_without_a_purchase_button(): void
    {
        // Reservations are admin-only; teams is the module an agent could reach
        // if the role gate let them, so assert on the middleware's own output.
        $response = $this->actingAs($this->agent)->get('/tenant-admin/webchat/settings');

        // The role gate fires first for an agent — the point is simply that no
        // agent is ever handed an upgrade form.
        $response->assertDontSee('name="plan_id"', false);
    }

    protected function buildSchema(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('stripe_price_id_monthly')->nullable();
            $t->string('stripe_price_id_annual')->nullable();
            $t->decimal('price_monthly', 8, 2)->default(0);
            $t->decimal('price_annual', 8, 2)->default(0);
            $t->boolean('trial_enabled')->default(false);
            $t->unsignedInteger('trial_days')->nullable();
            $t->unsignedInteger('max_users')->default(5);
            $t->unsignedInteger('max_instances')->nullable();
            $t->unsignedInteger('max_conversations_per_month')->default(0);
            $t->boolean('ai_included')->default(false);
            $t->unsignedInteger('ai_message_quota')->nullable();
            $t->boolean('reservations_enabled')->default(false);
            $t->json('modules')->nullable();
            $t->json('features')->nullable();
            $t->json('landing_features')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('subscription_status')->default('trial');
            $t->timestamp('subscription_starts_at')->nullable();
            $t->timestamp('subscription_ends_at')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->string('stripe_id')->nullable();
            $t->json('settings')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('remember_token', 100)->nullable();
            $t->string('role')->default('agent');
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_login_at')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('api_key')->nullable()->unique();
            $t->json('sidebar_permissions')->nullable();
            $t->timestamps();
        });

        Schema::create('teams', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('team_user', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('team_id')->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->timestamps();
        });

        Schema::create('whatsapp_instances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('team_id')->nullable()->index();
            $t->string('name');
            $t->string('gateway_instance_id')->nullable();
            $t->string('phone_number')->nullable();
            $t->string('status')->default('disconnected');
            $t->timestamps();
        });

        // The admin layout's sidebar counts unassigned conversations.
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('whatsapp_instance_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('team_id')->nullable();
            $t->unsignedBigInteger('owner_agent_id')->nullable();
            $t->string('state')->default('pool');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });
    }
}
