<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Wavadesk;
use Illuminate\Support\Facades\Route;
use Tests\Support\SwitchesWavadeskRole;
use Tests\TestCase;

/**
 * Core (app.wavadesk.com) must stop serving its inherited copies of the public
 * pages once the split is on.
 *
 * The bug this covers: signing out of the app landed the user on
 * app.wavadesk.com/login — the monolith's own form, still live, still able to
 * authenticate. A second front door the split does not know about.
 *
 * Routes are re-registered as closures where a test needs the NON-delegated
 * path, because the real controllers render views backed by the database and
 * this file is about routing, not about what the pages contain. The delegated
 * cases need no such help: the middleware answers before the controller runs,
 * which is itself part of what makes the handoff cheap.
 */
class SplitHostingCoreRoutingTest extends TestCase
{
    use SwitchesWavadeskRole;

    private const MARKETING = 'https://wavadesk.com';
    private const CORE_HOST = 'http://app.wavadesk.com';

    protected function setUp(): void
    {
        $this->presetWavadeskEnv(['WAVADESK_ROLE' => 'core']);

        parent::setUp();

        config(['wavadesk.marketing_origin' => self::MARKETING]);
    }

    protected function tearDown(): void
    {
        $this->clearWavadeskEnv();

        parent::tearDown();
    }

    public function test_landing_is_handed_to_the_marketing_site(): void
    {
        $this->get(self::CORE_HOST . '/')->assertRedirect(self::MARKETING . '/');
    }

    public function test_login_is_handed_to_the_marketing_site(): void
    {
        $this->get(self::CORE_HOST . '/login')->assertRedirect(self::MARKETING . '/login');
    }

    public function test_register_is_handed_to_the_marketing_site(): void
    {
        $this->get(self::CORE_HOST . '/register')->assertRedirect(self::MARKETING . '/register');
    }

    /** A plan or locale carried on the URL has to survive the hop. */
    public function test_query_string_survives_the_handoff(): void
    {
        $this->get(self::CORE_HOST . '/register?plan=pro&lang=fr')
            ->assertRedirect(self::MARKETING . '/register?plan=pro&lang=fr');
    }

    /** The handoff list is exact: everything else is still core's to serve. */
    public function test_an_application_path_stays_on_core(): void
    {
        Route::middleware('web')->get('/panel-probe', fn () => response('core'));

        $this->get(self::CORE_HOST . '/panel-probe')->assertOk()->assertSee('core');
    }

    /**
     * The staff doors were never part of the marketing flow — sending them to
     * wavadesk.com would strand every agent and supervisor on a form that
     * cannot see their workspace.
     */
    public function test_the_staff_login_stays_on_core(): void
    {
        Route::middleware('web')->get('/agent/login', fn () => response('agent door'));

        $this->get(self::CORE_HOST . '/agent/login')->assertOk()->assertSee('agent door');
    }

    /** Bouncing someone who is already using the app off it would be absurd. */
    public function test_a_signed_in_user_is_never_handed_off(): void
    {
        Route::middleware('web')->get('/login', fn () => response('local form'));

        $this->actingAs(User::make(['email' => 'someone@example.com']))
            ->get(self::CORE_HOST . '/login')
            ->assertOk()
            ->assertSee('local form');
    }

    /**
     * Unset origin is the monolith's own state, and every dev box, replica and
     * un-split install is in it. Delegating there would break all of them.
     */
    public function test_nothing_is_handed_off_when_no_origin_is_configured(): void
    {
        config(['wavadesk.marketing_origin' => '']);

        Route::middleware('web')->get('/login', fn () => response('local form'));

        $this->get(self::CORE_HOST . '/login')->assertOk()->assertSee('local form');

        $this->assertFalse(Wavadesk::delegatesToMarketing('app.wavadesk.com'));
    }

    /**
     * The rollback case. A marketing host put back on `core` still carries
     * WAVADESK_MARKETING_ORIGIN=https://wavadesk.com in its .env — and it *is*
     * wavadesk.com, so a naive rule would redirect /login to itself forever.
     */
    public function test_a_host_never_hands_off_to_itself(): void
    {
        Route::middleware('web')->get('/login', fn () => response('local form'));

        $this->get('http://wavadesk.com/login')->assertOk()->assertSee('local form');

        $this->assertFalse(Wavadesk::delegatesToMarketing('wavadesk.com'));
        $this->assertTrue(Wavadesk::delegatesToMarketing('app.wavadesk.com'));
    }

    /** Host comparison is a hostname match, not a string compare on the URL. */
    public function test_the_self_check_ignores_case(): void
    {
        $this->assertFalse(Wavadesk::delegatesToMarketing('WavaDesk.com'));
    }
}
