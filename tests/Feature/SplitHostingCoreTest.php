<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\SsoHandoffCode;
use App\Support\Wavadesk;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SwitchesWavadeskRole;
use Tests\TestCase;

/**
 * The core half of the split: app.wavadesk.com (Server B).
 *
 * Covers the two things that keep the boundary safe — that the auth API is
 * shut to anyone who cannot present the shared secret, and that a handoff code
 * opens exactly one session and never a second.
 *
 * Schema is built by hand rather than with RefreshDatabase: one of the
 * WhatsApp migrations uses MySQL-only raw SQL and cannot run on the in-memory
 * SQLite connection. Same approach as LockedModuleTest.
 */
class SplitHostingCoreTest extends TestCase
{
    use SwitchesWavadeskRole;

    private const ENDPOINT_LOGIN    = '/api/v1/auth/login';
    private const ENDPOINT_REGISTER = '/api/v1/auth/register';

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

    private function makeUser(array $overrides = []): User
    {
        $tenant = Tenant::create([
            'name'                => 'Acme',
            'slug'                => 'acme-' . uniqid(),
            'plan_id'             => null,
            'subscription_status' => 'trial',
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

    public function test_the_auth_api_is_invisible_without_the_shared_secret(): void
    {
        // 404, not 401: an attacker must not be able to tell the endpoint from
        // a URL that was never routed, or learn that a guessed secret was close.
        $this->postJson(self::ENDPOINT_LOGIN, [
            'email'    => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertNotFound();
    }

    public function test_the_auth_api_rejects_a_wrong_shared_secret(): void
    {
        $this->withHeaders($this->callerHeader(str_repeat('a', 64)))
            ->postJson(self::ENDPOINT_LOGIN, [
                'email'    => 'admin@example.com',
                'password' => 'secret-password',
            ])
            ->assertNotFound();
    }

    // ── Login ────────────────────────────────────────────────────────────────

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        $user = $this->makeUser();

        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_LOGIN, [
                'email'    => 'admin@example.com',
                'password' => 'secret-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('next_step', 'plan')
            ->assertJsonStructure(['token', 'user', 'tenant', 'next_step']);

        $this->assertNotEmpty($response->json('token'));

        // routes/api.php is loaded inside the `web` group, so an Auth::attempt()
        // here would have opened a session for the caller. It hands back a
        // token and nothing else.
        $this->assertGuest();
    }

    public function test_login_rejects_a_wrong_password_with_a_field_error(): void
    {
        $this->makeUser();

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_LOGIN, [
                'email'    => 'admin@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_refuses_a_disabled_account(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_LOGIN, [
                'email'    => 'admin@example.com',
                'password' => 'secret-password',
            ])
            ->assertForbidden();
    }

    public function test_login_sends_a_platform_account_to_the_control_panel(): void
    {
        $this->makeUser(['role' => 'super_admin', 'tenant_id' => null]);

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_LOGIN, [
                'email'    => 'admin@example.com',
                'password' => 'secret-password',
            ])
            ->assertForbidden();
    }

    // ── Register ─────────────────────────────────────────────────────────────

    public function test_register_creates_the_workspace_and_returns_a_token(): void
    {
        $response = $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_REGISTER, [
                'company_name' => 'New Co',
                'email'        => 'new@example.com',
                'password'     => 'secret-password',
            ]);

        $response->assertCreated()
            ->assertJsonPath('next_step', 'plan')
            ->assertJsonPath('tenant.name', 'New Co')
            // plan_id stays NULL: the plan picker grants it, not signup.
            ->assertJsonPath('tenant.plan_id', null);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'admin']);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_register_rejects_an_email_that_already_exists(): void
    {
        $this->makeUser();

        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_REGISTER, [
                'company_name' => 'New Co',
                'email'        => 'admin@example.com',
                'password'     => 'secret-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_an_unknown_plan_hint_does_not_fail_the_signup(): void
    {
        // The marketing site's pricing page can name a plan id this database
        // has since retired. It is a hint nothing acts on, so it must never be
        // the reason a paying customer cannot create an account.
        $this->withHeaders($this->callerHeader())
            ->postJson(self::ENDPOINT_REGISTER, [
                'company_name' => 'New Co',
                'email'        => 'new@example.com',
                'password'     => 'secret-password',
                'plan_id'      => 9999,
            ])
            ->assertCreated()
            ->assertJsonPath('plan_hint', 9999)
            ->assertJsonPath('tenant.plan_id', null);
    }

    // ── The handoff ──────────────────────────────────────────────────────────

    public function test_a_valid_handoff_code_opens_a_session_exactly_once(): void
    {
        $user = $this->makeUser();
        $code = (new SsoHandoffCode(self::SHARED_SECRET))->mint($user->id);

        $this->get('/auth/sso?code=' . urlencode($code))
            ->assertRedirect(route('register.plan'));

        $this->assertAuthenticatedAs($user);

        // Replay: same code, fresh session. The nonce is already spent.
        $this->flushSession();
        $this->app['auth']->guard('web')->logout();

        $this->get('/auth/sso?code=' . urlencode($code))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_code_signed_with_the_wrong_secret_is_rejected(): void
    {
        $user = $this->makeUser();
        $code = (new SsoHandoffCode(str_repeat('z', 64)))->mint($user->id);

        $this->get('/auth/sso?code=' . urlencode($code))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $user = $this->makeUser();
        $code = (new SsoHandoffCode(self::SHARED_SECRET))->mint($user->id, 15);

        // The minimum TTL is 15s; jump past it rather than sleeping.
        $this->travel(20)->seconds();

        $this->get('/auth/sso?code=' . urlencode($code))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_handoff_carries_the_pricing_page_choice_to_the_plan_picker(): void
    {
        $user = $this->makeUser();
        $code = (new SsoHandoffCode(self::SHARED_SECRET))->mint($user->id);

        $this->get('/auth/sso?code=' . urlencode($code) . '&plan=4')
            ->assertRedirect(route('register.plan', ['plan' => 4]));
    }

    // ── Schema ───────────────────────────────────────────────────────────────

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
    }
}
