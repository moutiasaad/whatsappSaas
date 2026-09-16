<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Wavadesk;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SwitchesWavadeskRole;
use Tests\TestCase;

/**
 * The v1 plans API on the core app.
 *
 * Two endpoints (`GET /api/v1/plans`, `POST /api/v1/plans/choose`) let
 * `wavadesk.com` render the plan picker and commit a pick without touching
 * this database directly. Same shared-secret guard the auth API sits behind;
 * `choose` also carries the user's PAT.
 *
 * Schema built by hand — RefreshDatabase fails on one of the WhatsApp
 * migrations under SQLite. Same shape as SplitHostingCoreTest.
 */
class SplitHostingPlansApiTest extends TestCase
{
    use SwitchesWavadeskRole;

    private const ENDPOINT_INDEX  = '/api/v1/plans';
    private const ENDPOINT_CHOOSE = '/api/v1/plans/choose';

    protected function setUp(): void
    {
        $this->presetWavadeskEnv([
            'WAVADESK_ROLE'          => 'core',
            'WAVADESK_SHARED_SECRET' => self::SHARED_SECRET,
        ]);

        parent::setUp();

        $this->buildSchema();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function tearDown(): void
    {
        $this->clearWavadeskEnv();

        parent::tearDown();
    }

    /** @return array<string, string> */
    private function callerHeader(string $secret = self::SHARED_SECRET): array
    {
        return [Wavadesk::CALLER_HEADER => $secret];
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name'          => 'Starter',
            'price_monthly' => 0.00,
            'price_annual'  => 0.00,
            'trial_enabled' => false,
            'trial_days'    => null,
            'is_active'     => true,
        ], $overrides));
    }

    private function makeUser(array $overrides = []): User
    {
        $tenant = Tenant::create([
            'name'                => 'Acme',
            'slug'                => 'acme-' . uniqid(),
            'plan_id'             => null,
            'subscription_status' => 'trial',
            'trialed_plan_ids'    => [],
            'is_active'           => true,
        ]);

        return User::create(array_merge([
            'tenant_id' => $tenant->id,
            'name'      => 'Tenant Admin',
            'email'     => 'admin@example.com',
            'password'  => 'secret-password',
            'role'      => 'admin',
            'is_active' => true,
        ], $overrides));
    }

    // ── The caller guard ─────────────────────────────────────────────────────

    public function test_index_is_invisible_without_the_shared_secret(): void
    {
        // 404, not 401 — same reasoning as the auth API: the endpoint cannot
        // be probed for existence, and a guessed secret cannot be told from
        // a wrong URL.
        $this->makePlan();

        $this->getJson(self::ENDPOINT_INDEX)->assertNotFound();
    }

    public function test_choose_is_invisible_without_the_shared_secret(): void
    {
        $plan = $this->makePlan();

        $this->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => $plan->id])
            ->assertNotFound();
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_index_returns_only_active_plans(): void
    {
        $active   = $this->makePlan(['name' => 'Starter']);
        $retired  = $this->makePlan(['name' => 'Legacy', 'is_active' => false]);

        $response = $this->withHeaders($this->callerHeader())
            ->getJson(self::ENDPOINT_INDEX);

        $response->assertOk()
            ->assertJsonStructure(['plans' => [['id', 'name', 'price_monthly', 'has_trial', 'is_free']]])
            ->assertJsonCount(1, 'plans')
            ->assertJsonPath('plans.0.id', $active->id)
            // A retired plan whose id lives on a stale marketing page must
            // not be able to reintroduce itself to the picker.
            ->assertJsonMissing(['id' => $retired->id]);
    }

    public function test_index_reports_the_effective_trial_days_from_the_plan(): void
    {
        // A trial that is enabled but has no explicit length falls back to
        // config('app.trial_days'). The API must return the same effective
        // number the web picker uses, not the raw column.
        $trialPlan = $this->makePlan([
            'name'          => 'Pro',
            'price_monthly' => 19.00,
            'trial_enabled' => true,
            'trial_days'    => null,
        ]);

        config()->set('app.trial_days', 14);

        $response = $this->withHeaders($this->callerHeader())
            ->getJson(self::ENDPOINT_INDEX);

        $response->assertOk()
            ->assertJsonPath('plans.0.id', $trialPlan->id)
            ->assertJsonPath('plans.0.trial_days', 14)
            ->assertJsonPath('plans.0.has_trial', true)
            ->assertJsonPath('plans.0.is_free', false);
    }

    // ── Choose: free / trial branch ─────────────────────────────────────────

    public function test_choose_activates_a_free_plan_immediately(): void
    {
        $plan = $this->makePlan(['price_monthly' => 0.00, 'trial_enabled' => false]);
        $user = $this->makeUser();

        Sanctum::actingAs($user);

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => $plan->id]);

        $response->assertOk()
            ->assertJsonPath('next_step', 'dashboard')
            ->assertJsonPath('tenant.plan_id', $plan->id)
            // A $0 plan with no trial is `active`, not `trial` — otherwise
            // ExpireSubscriptions would suspend it on the wrong side.
            ->assertJsonPath('tenant.subscription_status', 'active');

        $this->assertDatabaseHas('tenants', [
            'id'                  => $user->tenant_id,
            'plan_id'             => $plan->id,
            'subscription_status' => 'active',
        ]);
    }

    public function test_choose_starts_a_trial_for_a_paid_plan_with_an_unused_trial(): void
    {
        $plan = $this->makePlan([
            'name'          => 'Pro',
            'price_monthly' => 19.00,
            'trial_enabled' => true,
            'trial_days'    => 7,
        ]);
        $user = $this->makeUser();

        Sanctum::actingAs($user);

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => $plan->id]);

        $response->assertOk()
            ->assertJsonPath('next_step', 'dashboard')
            ->assertJsonPath('tenant.plan_id', $plan->id)
            ->assertJsonPath('tenant.subscription_status', 'trial');

        $this->assertNotNull($user->tenant->fresh()->trial_ends_at);
    }

    public function test_choose_sends_a_paid_plan_with_no_trial_left_to_checkout(): void
    {
        $plan = $this->makePlan([
            'name'          => 'Pro',
            'price_monthly' => 19.00,
            'trial_enabled' => false,
        ]);
        $user = $this->makeUser();

        // Tenant has already trialed this plan → no free trial left.
        $user->tenant->update(['trialed_plan_ids' => [$plan->id]]);

        Sanctum::actingAs($user);

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => $plan->id]);

        $response->assertOk()
            ->assertJsonPath('next_step', 'checkout')
            ->assertJsonPath('plan_id', $plan->id);

        // plan_id must NOT be written on the paid branch — that happens in
        // the capture path, so an abandoned checkout cannot leave a paid
        // plan attached for free.
        $this->assertDatabaseHas('tenants', [
            'id'      => $user->tenant_id,
            'plan_id' => null,
        ]);
    }

    // ── Choose: guard rails ─────────────────────────────────────────────────

    public function test_choose_requires_authentication(): void
    {
        $plan = $this->makePlan();

        // Caller secret present, but no user PAT — Sanctum should refuse.
        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => $plan->id])
            ->assertUnauthorized();
    }

    public function test_choose_rejects_an_unknown_plan(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => 999_999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_choose_rejects_a_retired_plan(): void
    {
        // A pricing-page card kept alive after the plan was retired must not
        // be re-clickable — the exists rule scopes to is_active=true.
        $retired = $this->makePlan(['name' => 'Legacy', 'is_active' => false]);
        $user    = $this->makeUser();
        Sanctum::actingAs($user);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => $retired->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_choose_refuses_when_a_plan_is_already_settled(): void
    {
        $first  = $this->makePlan(['name' => 'Starter']);
        $second = $this->makePlan(['name' => 'Pro', 'price_monthly' => 19.00, 'trial_enabled' => true]);

        $user = $this->makeUser();
        $user->tenant->update(['plan_id' => $first->id]);

        Sanctum::actingAs($user);

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHOOSE, ['plan_id' => $second->id]);

        $response->assertStatus(409)
            ->assertJsonPath('next_step', 'dashboard');

        // The already-settled plan_id must not be overwritten.
        $this->assertDatabaseHas('tenants', [
            'id'      => $user->tenant_id,
            'plan_id' => $first->id,
        ]);
    }

    // ── Schema ──────────────────────────────────────────────────────────────

    protected function buildSchema(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('price_monthly', 8, 2)->default(0);
            $t->decimal('price_annual', 8, 2)->default(0);
            $t->boolean('trial_enabled')->default(false);
            $t->unsignedInteger('trial_days')->nullable();
            $t->json('modules')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('subscription_status')->default('trial');
            $t->timestamp('subscription_ends_at')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->json('trialed_plan_ids')->nullable();
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
            $t->string('api_key')->nullable()->unique();
            $t->json('sidebar_permissions')->nullable();
            $t->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        // choose() writes to audit_logs via AuditLog::record on the free/trial
        // branch. Without this table those tests fatal with "no such table".
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action');
            $t->string('target_type')->nullable();
            $t->unsignedBigInteger('target_id')->nullable();
            $t->json('payload')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 1024)->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }
}
