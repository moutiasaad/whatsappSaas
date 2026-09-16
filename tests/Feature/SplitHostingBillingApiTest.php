<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PayPalService;
use App\Services\PayPalStandardService;
use App\Services\StripeService;
use App\Support\Wavadesk;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Stripe\Checkout\Session as StripeSession;
use Tests\Support\SwitchesWavadeskRole;
use Tests\TestCase;

/**
 * The v1 billing/checkout API on the core app.
 *
 * Same shape as SplitHostingPlansApiTest: manual schema build (RefreshDatabase
 * can't run the WhatsApp raw-SQL migration under SQLite), shared-secret gate
 * + user PAT. Stripe and PayPal SDK calls are stubbed via app()->instance();
 * the point of the test is that the controller wires them correctly, not that
 * the SDKs themselves work.
 */
class SplitHostingBillingApiTest extends TestCase
{
    use SwitchesWavadeskRole;

    private const ENDPOINT_CHECKOUT = '/api/v1/billing/checkout';

    protected function setUp(): void
    {
        $this->presetWavadeskEnv([
            'WAVADESK_ROLE'          => 'core',
            'WAVADESK_SHARED_SECRET' => self::SHARED_SECRET,
        ]);

        parent::setUp();

        $this->buildSchema();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Reset PayPal mode selection so each test picks its own branch.
        // client_id set → REST; unset + std configured → Standard; neither → 502.
        config()->set('services.paypal.client_id', null);
        config()->set('services.paypal.currency', 'USD');
    }

    protected function tearDown(): void
    {
        $this->clearWavadeskEnv();
        Mockery::close();

        parent::tearDown();
    }

    /** @return array<string, string> */
    private function callerHeader(string $secret = self::SHARED_SECRET): array
    {
        return [Wavadesk::CALLER_HEADER => $secret];
    }

    private function makePaidPlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name'          => 'Pro',
            'price_monthly' => 19.00,
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

    public function test_checkout_is_invisible_without_the_shared_secret(): void
    {
        // 404, not 401, or the guard's uniformity promise leaks:
        // an attacker who tries this URL without the header could otherwise
        // learn that the checkout endpoint exists as an authenticated one.
        $plan = $this->makePaidPlan();

        $this->postJson(self::ENDPOINT_CHECKOUT, [
            'plan_id'  => $plan->id,
            'provider' => 'stripe',
        ])->assertNotFound();
    }

    public function test_checkout_requires_authentication(): void
    {
        $plan = $this->makePaidPlan();

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'stripe',
            ])
            ->assertUnauthorized();
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_checkout_rejects_an_unknown_plan(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => 999_999,
                'provider' => 'stripe',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_checkout_rejects_a_retired_plan(): void
    {
        $retired = $this->makePaidPlan(['is_active' => false]);
        $user    = $this->makeUser();
        Sanctum::actingAs($user);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $retired->id,
                'provider' => 'stripe',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_checkout_rejects_an_unknown_provider(): void
    {
        $plan = $this->makePaidPlan();
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'crypto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('provider');
    }

    public function test_checkout_refuses_a_free_plan_with_a_field_error(): void
    {
        // Free plans have no checkout to initiate — the caller should have
        // gone through /api/v1/plans/choose. Return 422 with a field error
        // rather than silently redirecting so the marketing app can render
        // it inline instead of white-screening.
        $free = $this->makePaidPlan(['price_monthly' => 0.00, 'name' => 'Free']);
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $free->id,
                'provider' => 'stripe',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    // ── Stripe branch ───────────────────────────────────────────────────────

    public function test_checkout_stripe_returns_the_hosted_url_and_writes_a_pending_payment(): void
    {
        $plan = $this->makePaidPlan(['price_monthly' => 19.00]);
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // StripeService::createCheckoutSession is typed to return
        // Stripe\Checkout\Session; a plain stdClass fake is rejected by the
        // return-type check before the controller ever sees it. constructFrom
        // is the SDK's own helper for hydrating a Session from an array.
        $fakeSession = StripeSession::constructFrom([
            'id'  => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($fakeSession);
        $this->app->instance(StripeService::class, $stripe);

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'stripe',
            ]);

        $response->assertOk()
            ->assertJsonPath('provider', 'stripe')
            ->assertJsonPath('redirect_url', 'https://checkout.stripe.com/pay/cs_test_123')
            ->assertJsonPath('currency', 'USD')
            // JSON drops the trailing zero on a whole-number float, so
            // 19.00 lands as 19 in the response body. Strict-compare that.
            ->assertJsonPath('amount', 19);

        // A pending row keyed by the session id — the Stripe webhook path
        // looks it up by that to fulfil the plan later.
        $this->assertDatabaseHas('tenant_payments', [
            'tenant_id'         => $user->tenant_id,
            'plan_id'           => $plan->id,
            'payment_method'    => 'stripe',
            'stripe_session_id' => 'cs_test_123',
            'status'            => 'pending',
        ]);
    }

    public function test_checkout_stripe_returns_502_on_gateway_failure(): void
    {
        $plan = $this->makePaidPlan();
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->andThrow(new \RuntimeException('Stripe: connection refused'));
        $this->app->instance(StripeService::class, $stripe);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'stripe',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Payment initiation failed.')
            // Raw exception text must NOT surface to the caller — same
            // UI-003 principle as PaymentController.
            ->assertJsonMissing(['error' => 'Stripe: connection refused']);

        // No pending row should be left behind when the gateway failed
        // before we got a session id to key it on.
        $this->assertDatabaseCount('tenant_payments', 0);
    }

    // ── PayPal REST branch ──────────────────────────────────────────────────

    public function test_checkout_paypal_rest_returns_approve_url_when_client_id_is_configured(): void
    {
        config()->set('services.paypal.client_id', 'test-client-id');
        config()->set('services.paypal.currency', 'EUR');

        $plan = $this->makePaidPlan(['price_monthly' => 29.00]);
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $fakeOrder = ['id' => 'ORDER-42', 'status' => 'CREATED'];

        $paypal = Mockery::mock(PayPalService::class);
        $paypal->shouldReceive('createOrder')->once()->andReturn($fakeOrder);
        $paypal->shouldReceive('extractApproveUrl')
            ->once()
            ->with($fakeOrder)
            ->andReturn('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-42');
        $this->app->instance(PayPalService::class, $paypal);

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'paypal',
            ]);

        $response->assertOk()
            ->assertJsonPath('provider', 'paypal')
            ->assertJsonPath('redirect_url', 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-42')
            ->assertJsonPath('currency', 'EUR');

        $this->assertDatabaseHas('tenant_payments', [
            'tenant_id'       => $user->tenant_id,
            'plan_id'         => $plan->id,
            'payment_method'  => 'paypal',
            'paypal_order_id' => 'ORDER-42',
            'status'          => 'pending',
        ]);
    }

    public function test_checkout_paypal_rest_502s_when_extract_returns_null(): void
    {
        // A well-formed order response with no approve link is a real-world
        // PayPal shape (an order stuck in a state that produces no HATEOAS
        // approve link). Fail explicitly instead of redirecting to null.
        config()->set('services.paypal.client_id', 'test-client-id');

        $plan = $this->makePaidPlan();
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $paypal = Mockery::mock(PayPalService::class);
        $paypal->shouldReceive('createOrder')->once()->andReturn(['id' => 'X']);
        $paypal->shouldReceive('extractApproveUrl')->once()->andReturn(null);
        $this->app->instance(PayPalService::class, $paypal);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'paypal',
            ])
            ->assertStatus(502);
    }

    // ── PayPal Standard fallback ────────────────────────────────────────────

    public function test_checkout_paypal_standard_returns_form_params_when_no_rest_client_id(): void
    {
        // No client_id → REST is off. Standard is picked when configured.
        // Standard mode is a form POST, not a straight 302, so the API returns
        // the URL + the parameter map for the caller to render an auto-submit
        // form on its own domain.
        config()->set('services.paypal.client_id', null);

        $plan = $this->makePaidPlan(['price_monthly' => 19.00, 'name' => 'Pro']);
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $std = Mockery::mock(PayPalStandardService::class);
        $std->shouldReceive('isConfigured')->once()->andReturn(true);
        $std->shouldReceive('buildCheckoutParams')->once()->andReturn([
            'business'   => 'merchant@example.com',
            'amount'     => '19.00',
            'invoice'    => 'tenant_1_1234',
        ]);
        $std->shouldReceive('getCheckoutUrl')->once()->andReturn('https://www.paypal.com/cgi-bin/webscr');
        $this->app->instance(PayPalStandardService::class, $std);

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'paypal',
            ]);

        $response->assertOk()
            ->assertJsonPath('provider', 'paypal')
            ->assertJsonPath('method', 'POST')
            ->assertJsonPath('redirect_url', 'https://www.paypal.com/cgi-bin/webscr')
            ->assertJsonStructure(['redirect_form' => ['business', 'amount', 'invoice']]);

        $this->assertDatabaseHas('tenant_payments', [
            'tenant_id'      => $user->tenant_id,
            'plan_id'        => $plan->id,
            'payment_method' => 'paypal',
            'status'         => 'pending',
        ]);
    }

    public function test_checkout_paypal_502s_when_no_provider_mode_is_configured(): void
    {
        // Neither REST client_id nor Standard configured — the server has no
        // way to bill through PayPal. Fail explicitly rather than trying and
        // hitting a null pointer downstream.
        config()->set('services.paypal.client_id', null);

        $plan = $this->makePaidPlan();
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $std = Mockery::mock(PayPalStandardService::class);
        $std->shouldReceive('isConfigured')->once()->andReturn(false);
        $this->app->instance(PayPalStandardService::class, $std);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_CHECKOUT, [
                'plan_id'  => $plan->id,
                'provider' => 'paypal',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'PayPal is not configured on this server.');

        // No half-created payment row when we bailed before ever touching
        // the gateway.
        $this->assertDatabaseCount('tenant_payments', 0);
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

        // Superset of every column TenantPayment::create writes to — same
        // set the layered production migrations end up with.
        Schema::create('tenant_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('kind')->default('subscription');
            $t->decimal('amount', 10, 3)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->string('payment_method')->nullable();
            $t->json('metadata')->nullable();
            $t->string('stripe_session_id')->nullable();
            $t->string('stripe_checkout_url', 600)->nullable();
            $t->string('paypal_order_id')->nullable();
            $t->string('paypal_capture_id')->nullable();
            $t->string('status')->default('pending');
            $t->json('gateway_response')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }
}
