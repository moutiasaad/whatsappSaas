<?php

namespace Tests\Feature;

use App\Support\Wavadesk;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\Support\SwitchesWavadeskRole;
use Tests\TestCase;

/**
 * Marketing (wavadesk.com) must stop answering for the application.
 *
 * This direction is the dangerous one. The marketing host carries the whole
 * monolith's routes and its own MySQL, so before this guard a bookmarked
 * /payment/order or a PayPal return URL was served there — against a database
 * that is not the source of truth. It would not error; it would quietly bill
 * and record against the wrong rows.
 *
 * Deny-by-default for that reason: a route added to the app later is delegated
 * to core automatically, and only the public pages listed in the middleware
 * stay here.
 */
class SplitHostingMarketingRoutingTest extends TestCase
{
    use SwitchesWavadeskRole;

    private const CORE = 'https://app.wavadesk.com';
    private const MARKETING_HOST = 'http://wavadesk.com';

    protected function setUp(): void
    {
        $this->presetWavadeskEnv(['WAVADESK_ROLE' => 'marketing']);

        parent::setUp();

        config(['wavadesk.core_url' => self::CORE]);
    }

    protected function tearDown(): void
    {
        $this->clearWavadeskEnv();

        parent::tearDown();
    }

    public function test_the_role_is_marketing(): void
    {
        $this->assertTrue(Wavadesk::isMarketing());
        $this->assertTrue(Wavadesk::delegatesToCore('wavadesk.com'));
    }

    /** Checkout belongs to the host that owns the subscription rows. */
    public function test_payment_goes_to_core(): void
    {
        $this->get(self::MARKETING_HOST . '/payment/order')
            ->assertRedirect(self::CORE . '/payment/order');
    }

    public function test_panels_go_to_core(): void
    {
        $this->get(self::MARKETING_HOST . '/admin/billing')
            ->assertRedirect(self::CORE . '/admin/billing');
    }

    public function test_step_two_of_signup_stays_on_marketing(): void
    {
        // Since Session 2 the plan picker runs on marketing itself and calls
        // core's /api/v1/plans/choose. A visitor with no session PAT (the
        // case here — no register has fired) gets bounced to /register by
        // the controller's own guard, NOT to the core app's copy of the
        // page. That's what "step 2 lives here now" looks like.
        $this->get(self::MARKETING_HOST . '/register/plan')
            ->assertRedirect(self::MARKETING_HOST . '/register');
    }

    /**
     * routes/api.php is loaded under the `web` group, so all 57 of the app's
     * API routes exist on the marketing host too. They read the wrong
     * database; they belong to core.
     */
    public function test_the_api_goes_to_core(): void
    {
        $this->get(self::MARKETING_HOST . '/api/agents/online')
            ->assertRedirect(self::CORE . '/api/agents/online');
    }

    /**
     * A path with no route on this host 404s before any group middleware runs,
     * so it is never redirected. Harmless — nothing is served from the wrong
     * database — but worth pinning down so the gap is a decision, not a
     * surprise. /auth/sso is the real example: core-only by design.
     */
    public function test_an_unrouted_path_404s_rather_than_redirecting(): void
    {
        $this->get(self::MARKETING_HOST . '/auth/sso?code=x')->assertNotFound();
    }

    /**
     * A 302 would turn PayPal's IPN POST into a GET and drop the body. 308 is
     * the only redirect that keeps both. The URLs still want re-pointing at
     * core directly — this is the safety net, not the fix.
     */
    public function test_a_post_keeps_its_method_across_the_handoff(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->post(self::MARKETING_HOST . '/payment/paypal/ipn', ['txn_id' => 'X'])
            ->assertStatus(308)
            ->assertRedirect(self::CORE . '/payment/paypal/ipn');
    }

    public function test_the_landing_page_stays_on_marketing(): void
    {
        Route::middleware('web')->get('/', fn () => response('landing'));

        $this->get(self::MARKETING_HOST . '/')->assertOk()->assertSee('landing');
    }

    public function test_the_login_form_stays_on_marketing(): void
    {
        Route::middleware('web')->get('/login', fn () => response('login form'));

        $this->get(self::MARKETING_HOST . '/login')->assertOk()->assertSee('login form');
    }

    public function test_the_register_form_stays_on_marketing(): void
    {
        Route::middleware('web')->get('/register', fn () => response('register form'));

        $this->get(self::MARKETING_HOST . '/register')->assertOk()->assertSee('register form');
    }

    /** Crawlers must reach these without a cross-domain bounce. */
    public function test_seo_and_legal_pages_stay_on_marketing(): void
    {
        foreach (['/features', '/legal/terms', '/robots.txt'] as $path) {
            Route::middleware('web')->get(ltrim($path, '/'), fn () => response('public'));

            $this->get(self::MARKETING_HOST . $path)
                ->assertOk()
                ->assertSee('public');
        }
    }
}
