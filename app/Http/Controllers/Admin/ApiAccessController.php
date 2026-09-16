<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * ApiAccessController — the standalone "API access" section.
 *
 * Split out of ProfileController (where the api-key card used to live) so
 * that:
 *  - The feature can be gated by the `api_access` plan module — profile is
 *    always available to every user, but API access is a plan feature
 *    that lower tiers don't get.
 *  - The upgrade preview (admin.locked.module) shows up cleanly when the
 *    plan lacks the module, mirroring how Reservations and Live Chat
 *    are surfaced as locked-behind-upgrade in the sidebar.
 *
 * API keys are per-user (not per-tenant): every signed-in user gets their
 * own so revoking one doesn't kill everyone else's integrations. The key
 * format `wvd_<48 random chars>` is the same shape the profile page
 * used to mint; kept the exact prefix so already-deployed integrations
 * that check for it don't break after the move.
 */
class ApiAccessController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        // First visit → mint a key. Same lazy pattern the old profile page
        // used, so an existing user who never opened the profile section
        // (agents, mostly) doesn't hit an empty state on landing here.
        if (! $user->api_key) {
            $user->update(['api_key' => $this->freshKey()]);
            $user->refresh();
        }

        return view('admin.api-access.index', [
            'apiKey' => $user->api_key,
        ]);
    }

    public function regenerate(): JsonResponse
    {
        $user = Auth::user();
        $user->update(['api_key' => $this->freshKey()]);

        return response()->json(['api_key' => $user->fresh()->api_key]);
    }

    /**
     * Cryptographically-random key that's guaranteed unique.
     *
     * `wvd_` prefix is deliberate — it's how the api.key middleware
     * recognises the format and how integrators eyeball their keys in
     * .env files. Do not change without a coordinated rotation.
     */
    private function freshKey(): string
    {
        do {
            $key = 'wvd_' . Str::random(48);
        } while (User::where('api_key', $key)->exists());

        return $key;
    }
}
