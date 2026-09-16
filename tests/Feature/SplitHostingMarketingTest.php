<?php

namespace Tests\Feature;

use App\Services\Auth\SsoHandoffCode;
use App\Support\Wavadesk;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\SwitchesWavadeskRole;
use Tests\TestCase;

/**
 * The marketing half of the split: wavadesk.com (Server A).
 *
 * Its defining property is that it owns no identity. No test here touches a
 * users table — there isn't one to touch — so every assertion is about what
 * goes over the wire to the core app and where the browser is sent next.
 */
class SplitHostingMarketingTest extends TestCase
{
    use SwitchesWavadeskRole;

    private const CORE = 'https://app.wavadesk.test';

    protected function setUp(): void
    {
        $this->presetWavadeskEnv([
            'WAVADESK_ROLE'          => 'marketing',
            'WAVADESK_CORE_URL'      => self::CORE,
            'WAVADESK_SHARED_SECRET' => self::SHARED_SECRET,
        ]);

        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function tearDown(): void
    {
        $this->clearWavadeskEnv();

        parent::tearDown();
    }

    public function test_the_host_reports_itself_as_marketing(): void
    {
        $this->assertTrue(Wavadesk::isMarketing());
        $this->assertFalse(Wavadesk::isCore());
    }

    public function test_register_forwards_to_the_core_app_and_lands_on_the_marketing_plan_picker(): void
    {
        // The SSO handoff used to happen at register — the browser was bounced
        // straight to app.wavadesk.com's plan picker. Since Session 2 the
        // handoff is deferred until AFTER the plan is picked (free/trial) or
        // AFTER payment captures (paid): the browser stays on wavadesk.com
        // through step 2. The PAT is still stashed server-side for the picker
        // to carry into the /api/v1/plans/choose call.
        Http::fake([
            self::CORE . '/api/v1/auth/register' => Http::response([
                'token'     => 'plain-text-pat',
                'user'      => ['id' => 42, 'email' => 'new@example.com'],
                'tenant'    => ['id' => 7, 'name' => 'Acme'],
                'next_step' => 'plan',
                'plan_hint' => 3,
            ], 201),
        ]);

        $response = $this->post('/register', [
            'company_name' => 'Acme',
            'email'        => 'new@example.com',
            'password'     => 'secret-password',
            'plan_id'      => 3,
        ]);

        // The credentials went to the core app, authenticated by the shared
        // secret — not by CORS, and not by a cookie.
        Http::assertSent(function ($request) {
            return $request->url() === self::CORE . '/api/v1/auth/register'
                && $request->header(Wavadesk::CALLER_HEADER) === [self::SHARED_SECRET]
                && $request['email'] === 'new@example.com';
        });

        // PAT + user + tenant snapshots kept server-side for the picker to
        // authenticate against core.
        $response->assertSessionHas(Wavadesk::SESSION_TOKEN, 'plain-text-pat');
        $response->assertSessionHas(Wavadesk::SESSION_USER);
        $response->assertSessionHas(Wavadesk::SESSION_TENANT);

        // Browser stays on marketing. Plan hint from the pricing card the
        // user clicked rides along in the query to pre-select the card.
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/register/plan', $location);
        $this->assertStringContainsString('plan=3', $location);
        // PAT never in the URL — same rule as when the handoff happened here.
        $this->assertStringNotContainsString('plain-text-pat', $location);
    }

    public function test_a_validation_error_from_the_core_app_lands_on_the_field(): void
    {
        Http::fake([
            self::CORE . '/api/v1/auth/register' => Http::response([
                'message' => 'The email has already been taken.',
                'errors'  => ['email' => ['The email has already been taken.']],
            ], 422),
        ]);

        $response = $this->post('/register', [
            'company_name' => 'Acme',
            'email'        => 'taken@example.com',
            'password'     => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing(Wavadesk::SESSION_TOKEN);
    }

    public function test_an_unreachable_core_app_shows_an_error_rather_than_a_handoff(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $response = $this->post('/register', [
            'company_name' => 'Acme',
            'email'        => 'new@example.com',
            'password'     => 'secret-password',
        ]);

        $response->assertSessionHasErrors('general');
        $response->assertSessionMissing(Wavadesk::SESSION_TOKEN);
    }

    public function test_login_forwards_to_the_core_app_and_hands_the_browser_off(): void
    {
        Http::fake([
            self::CORE . '/api/v1/auth/login' => Http::response([
                'token'     => 'login-pat',
                'user'      => ['id' => 9, 'email' => 'admin@example.com'],
                'tenant'    => ['id' => 1, 'plan_id' => 2],
                'next_step' => 'dashboard',
            ], 200),
        ]);

        $response = $this->post('/login', [
            'email'    => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHas(Wavadesk::SESSION_TOKEN, 'login-pat');

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::CORE . '/auth/sso?', $location);
        $this->assertStringNotContainsString('login-pat', $location);
    }

    public function test_bad_credentials_come_back_as_a_field_error(): void
    {
        Http::fake([
            self::CORE . '/api/v1/auth/login' => Http::response([
                'message' => 'These credentials do not match our records.',
                'errors'  => ['email' => ['These credentials do not match our records.']],
            ], 422),
        ]);

        $response = $this->post('/login', [
            'email'    => 'admin@example.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing(Wavadesk::SESSION_TOKEN);
    }

    public function test_a_disabled_account_keeps_the_core_apps_message(): void
    {
        Http::fake([
            self::CORE . '/api/v1/auth/login' => Http::response(['message' => 'Account disabled.'], 403),
        ]);

        $this->post('/login', [
            'email'    => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertSessionHasErrors(['email' => 'Account disabled.']);
    }

    public function test_logout_revokes_the_token_on_the_core_app(): void
    {
        Http::fake([
            self::CORE . '/api/v1/auth/logout' => Http::response(['message' => 'Logged out.'], 200),
        ]);

        $this->withSession([Wavadesk::SESSION_TOKEN => 'live-pat'])->post('/logout');

        Http::assertSent(fn ($request) => $request->url() === self::CORE . '/api/v1/auth/logout'
            && $request->hasHeader('Authorization', 'Bearer live-pat'));
    }

    public function test_the_handoff_and_auth_api_routes_do_not_exist_on_this_host(): void
    {
        // Both hosts hold the same signing secret. If the marketing host also
        // served /auth/sso it could redeem its own codes into a local session —
        // the precise thing the split is meant to prevent.
        $this->assertFalse(Route::has('auth.sso.redeem'));
        $this->assertFalse(Route::has('api.v1.auth.login'));
        $this->assertFalse(Route::has('api.v1.auth.register'));
    }

    // ── Step 2: the marketing plan picker (Session 2) ───────────────────────

    public function test_login_with_no_plan_yet_lands_on_the_marketing_plan_picker(): void
    {
        // Login returns next_step="plan" for a workspace whose plan_id is
        // still NULL. The old flow bounced the browser through core's
        // /auth/sso only for core to immediately re-redirect to
        // /register/plan; the new flow skips the round trip and keeps the
        // browser on marketing.
        Http::fake([
            self::CORE . '/api/v1/auth/login' => Http::response([
                'token'     => 'live-pat',
                'user'      => ['id' => 42, 'email' => 'admin@example.com'],
                'tenant'    => ['id' => 7, 'name' => 'Acme', 'plan_id' => null],
                'next_step' => 'plan',
            ], 200),
        ]);

        $response = $this->post('/login', [
            'email'    => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/register/plan');
        $response->assertSessionHas(Wavadesk::SESSION_TOKEN, 'live-pat');
    }

    public function test_get_plan_picker_without_a_session_bounces_to_register(): void
    {
        // No session means no register has fired — the picker cannot
        // identify who is choosing. Send them back to sign up rather than
        // 500 on a missing PAT downstream.
        $this->get('/register/plan')->assertRedirect('/register');
    }

    public function test_get_plan_picker_with_a_settled_plan_hands_off_to_core(): void
    {
        // The workspace already has a plan (they came back to the picker
        // via a stale link). Nothing to choose here — hand them off to
        // core signed in via a fresh handoff code, since they were never
        // signed in there this session.
        $response = $this->withSession([
            Wavadesk::SESSION_TOKEN  => 'live-pat',
            Wavadesk::SESSION_USER   => ['id' => 42, 'email' => 'admin@example.com'],
            Wavadesk::SESSION_TENANT => ['id' => 7, 'name' => 'Acme', 'plan_id' => 3],
        ])->get('/register/plan');

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::CORE . '/auth/sso?', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(42, (new SsoHandoffCode(self::SHARED_SECRET))->verify($query['code']));
    }

    public function test_choose_plan_on_trial_grant_hands_off_to_core(): void
    {
        // Core returned next_step=dashboard — a free or trial-eligible plan
        // that has been activated on the spot. Mint an SSO handoff so the
        // browser lands on core signed in, on the panel.
        Http::fake([
            self::CORE . '/api/v1/plans/choose' => Http::response([
                'next_step'  => 'dashboard',
                'tenant'     => ['id' => 7, 'name' => 'Acme', 'plan_id' => 2, 'subscription_status' => 'trial'],
                'home_route' => 'tenant_admin.dashboard',
            ], 200),
        ]);

        $response = $this->withSession([
            Wavadesk::SESSION_TOKEN  => 'live-pat',
            Wavadesk::SESSION_USER   => ['id' => 42, 'email' => 'admin@example.com'],
            Wavadesk::SESSION_TENANT => ['id' => 7, 'name' => 'Acme', 'plan_id' => null],
        ])->post('/register/plan', ['plan_id' => 2]);

        // API call carried the session PAT as a Bearer token.
        Http::assertSent(function ($request) {
            return $request->url() === self::CORE . '/api/v1/plans/choose'
                && $request->hasHeader('Authorization', 'Bearer live-pat')
                && $request->hasHeader(Wavadesk::CALLER_HEADER, self::SHARED_SECRET)
                && $request['plan_id'] === 2;
        });

        // Response's fresh tenant snapshot is written back to session so a
        // back button to /register/plan short-circuits instead of re-POSTing.
        $response->assertSessionHas(Wavadesk::SESSION_TENANT, function (array $tenant) {
            return $tenant['plan_id'] === 2 && $tenant['subscription_status'] === 'trial';
        });

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::CORE . '/auth/sso?', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(42, (new SsoHandoffCode(self::SHARED_SECRET))->verify($query['code']));
        // No plan hint on the dashboard branch — the plan is already granted.
        $this->assertArrayNotHasKey('plan', $query);
    }

    public function test_choose_plan_on_paid_needs_checkout_hands_off_to_core_with_plan_hint(): void
    {
        // Core returned next_step=checkout — paid plan with no trial left.
        // The marketing-side checkout page is Session 2b; for now hand off
        // to core so its existing checkout flow can run there. The plan id
        // rides along as ?plan= so core pre-selects the card.
        Http::fake([
            self::CORE . '/api/v1/plans/choose' => Http::response([
                'next_step' => 'checkout',
                'plan_id'   => 3,
                'tenant'    => ['id' => 7, 'name' => 'Acme', 'plan_id' => null],
            ], 200),
        ]);

        $response = $this->withSession([
            Wavadesk::SESSION_TOKEN  => 'live-pat',
            Wavadesk::SESSION_USER   => ['id' => 42, 'email' => 'admin@example.com'],
            Wavadesk::SESSION_TENANT => ['id' => 7, 'name' => 'Acme', 'plan_id' => null],
        ])->post('/register/plan', ['plan_id' => 3]);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::CORE . '/auth/sso?', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('3', (string) $query['plan']);
        $this->assertSame(42, (new SsoHandoffCode(self::SHARED_SECRET))->verify($query['code']));
    }

    public function test_choose_plan_422_from_core_lands_on_the_plan_id_field(): void
    {
        // Retired plan id — the picker was showing a card core has since
        // dropped. Re-raise as a local ValidationException so the picker
        // can re-render with an inline field error, same shape as the
        // register form's "email taken" handling.
        Http::fake([
            self::CORE . '/api/v1/plans/choose' => Http::response([
                'message' => 'The selected plan is invalid.',
                'errors'  => ['plan_id' => ['The selected plan is invalid.']],
            ], 422),
        ]);

        $this->withSession([
            Wavadesk::SESSION_TOKEN  => 'live-pat',
            Wavadesk::SESSION_USER   => ['id' => 42, 'email' => 'admin@example.com'],
            Wavadesk::SESSION_TENANT => ['id' => 7, 'name' => 'Acme', 'plan_id' => null],
        ])->post('/register/plan', ['plan_id' => 999_999])
          ->assertSessionHasErrors('plan_id');
    }

    public function test_choose_plan_409_treats_it_as_already_settled_and_hands_off(): void
    {
        // Concurrent second-tab pick — core already committed a plan. Not
        // an error worth showing; just send them on to core.
        Http::fake([
            self::CORE . '/api/v1/plans/choose' => Http::response([
                'next_step' => 'dashboard',
                'message'   => 'Plan already chosen.',
            ], 409),
        ]);

        $response = $this->withSession([
            Wavadesk::SESSION_TOKEN  => 'live-pat',
            Wavadesk::SESSION_USER   => ['id' => 42, 'email' => 'admin@example.com'],
            Wavadesk::SESSION_TENANT => ['id' => 7, 'name' => 'Acme', 'plan_id' => null],
        ])->post('/register/plan', ['plan_id' => 2]);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::CORE . '/auth/sso?', $location);
    }

    public function test_choose_plan_without_a_session_bounces_to_register(): void
    {
        // Someone POSTing to the picker without ever having registered.
        // No PAT to carry, no user id to mint a handoff for — send them
        // to /register rather than talk to core with a blank token.
        $this->post('/register/plan', ['plan_id' => 2])
             ->assertRedirect('/register');

        Http::assertNothingSent();
    }
}
