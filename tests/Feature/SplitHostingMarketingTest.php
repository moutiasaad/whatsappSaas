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

    public function test_register_forwards_to_the_core_app_and_hands_the_browser_off(): void
    {
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

        // The PAT is kept server-side. It must never be in the redirect.
        $response->assertSessionHas(Wavadesk::SESSION_TOKEN, 'plain-text-pat');

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::CORE . '/auth/sso?', $location);
        $this->assertStringNotContainsString('plain-text-pat', $location);

        // The code in the URL is the only thing that crossed the boundary, and
        // it verifies against the same secret the core app holds.
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('3', (string) $query['plan']);
        $this->assertSame(42, (new SsoHandoffCode(self::SHARED_SECRET))->verify($query['code']));
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
}
