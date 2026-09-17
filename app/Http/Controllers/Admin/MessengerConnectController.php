<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Messenger\Page;
use App\Services\Messenger\GraphApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * MessengerConnectController
 *
 * Server-side OAuth flow that lets a tenant admin connect a Facebook
 * Page without asking Claude to seed a row via tinker. Replaces the
 * manual bootstrap we did on 2026-09-17.
 *
 * Flow:
 *   GET  /tenant-admin/messenger/settings       → list connected + Connect button
 *   GET  /tenant-admin/messenger/oauth/start    → redirect to Facebook
 *   GET  /tenant-admin/messenger/oauth/callback → Facebook returns here with code
 *        - exchange code → short-lived user token
 *        - short-lived → long-lived user token
 *        - list Pages user manages
 *        - if 1 page, connect it directly; if many, show picker
 *   POST /tenant-admin/messenger/oauth/select   → tenant picks Page from picker
 *   POST /tenant-admin/messenger/pages/{id}/disconnect → remove
 *
 * State (CSRF) is a random string in the session; Facebook echoes it
 * back in the callback query. Mismatch → hard reject. TTL keeps the
 * session key from growing unbounded when a tenant abandons the flow.
 */
class MessengerConnectController extends Controller
{
    private const STATE_SESSION_KEY = 'messenger.oauth.state';
    private const PICK_SESSION_KEY  = 'messenger.oauth.pending_pages';

    /** Scopes we request. Missing pages_manage_metadata caused the
     * 2026-09-17 "subscribe returns {success:true} but never delivers"
     * silent trap — always request it. */
    private const REQUIRED_SCOPES = [
        'pages_messaging',
        'pages_manage_metadata',
        'pages_show_list',
        'pages_read_engagement',
    ];

    public function __construct(private GraphApiClient $graph) {}

    // ─── Settings page ───────────────────────────────────────────────

    public function settings(Request $request)
    {
        $pages = Page::withoutGlobalScope('tenant')
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('id')
            ->get();

        $canConnect = (bool) config('services.meta.app_id')
            && (bool) config('services.meta.app_secret');

        return view('admin.messenger.settings', [
            'pages'      => $pages,
            'canConnect' => $canConnect,
        ]);
    }

    // ─── OAuth: start ────────────────────────────────────────────────

    public function start(Request $request): RedirectResponse
    {
        $appId = (string) config('services.meta.app_id');
        if ($appId === '') {
            return back()->with('error', 'Meta App ID is not configured on this box.');
        }

        // CSRF: single-use random state, echoed back by Facebook in the callback.
        $state = Str::random(40);
        Session::put(self::STATE_SESSION_KEY, [
            'value'      => $state,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $redirect = route('tenant_admin.messenger.oauth.callback');

        $url = 'https://www.facebook.com/' . config('services.meta.graph_version', 'v21.0') . '/dialog/oauth?' . http_build_query([
            'client_id'     => $appId,
            'redirect_uri'  => $redirect,
            'state'         => $state,
            'scope'         => implode(',', self::REQUIRED_SCOPES),
            'response_type' => 'code',
            // Force Facebook to re-prompt for scope selection every time —
            // otherwise a tenant who granted only pages_messaging the first
            // time would silently skip the fix flow.
            'auth_type'     => 'rerequest',
        ]);

        return redirect()->away($url);
    }

    // ─── OAuth: callback ─────────────────────────────────────────────

    public function callback(Request $request)
    {
        // Verify state (single-use).
        $stashed = Session::pull(self::STATE_SESSION_KEY);
        $ok = is_array($stashed)
            && ($stashed['value'] ?? null) === (string) $request->query('state', '')
            && ($stashed['expires_at'] ?? 0) >= now()->timestamp;

        if (! $ok) {
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Invalid or expired OAuth state. Please try again.');
        }

        // Facebook returns ?error=... when the user cancels or denies.
        if ($request->filled('error')) {
            $reason = $request->query('error_description') ?: $request->query('error');
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Facebook returned an error: ' . $reason);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Facebook did not return an authorisation code.');
        }

        $redirectUri = route('tenant_admin.messenger.oauth.callback');

        // 1. code → short-lived user token
        $short = $this->graph->exchangeCodeForUserToken($code, $redirectUri);
        if (! $short['ok']) {
            Log::channel('messenger')->warning('Connect: code exchange failed', ['error' => $short['error']]);
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Could not exchange the authorisation code. ' . ($short['error']['message'] ?? ''));
        }

        // 2. short → long-lived user token
        $long = $this->graph->getLongLivedUserToken($short['token']);
        if (! $long['ok']) {
            Log::channel('messenger')->warning('Connect: long-lived exchange failed', ['error' => $long['error']]);
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Could not get a long-lived token. ' . ($long['error']['message'] ?? ''));
        }

        // 3. List Pages the user manages
        $pages = $this->graph->listPages($long['token']);
        if (! $pages['ok']) {
            Log::channel('messenger')->warning('Connect: /me/accounts failed', ['error' => $pages['error']]);
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Could not list your Pages. ' . ($pages['error']['message'] ?? ''));
        }

        if (empty($pages['pages'])) {
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'You don\'t manage any Facebook Pages on that account. Create one on facebook.com then retry.');
        }

        // If exactly one Page, skip the picker and connect directly.
        if (count($pages['pages']) === 1) {
            return $this->connectPage($request, $pages['pages'][0]);
        }

        // Multiple Pages: stash and show picker. We don't stash the token
        // itself — the tenant re-authenticates if the picker times out
        // (safer than parking a long-lived token in a session).
        Session::put(self::PICK_SESSION_KEY, [
            'pages'      => array_map(fn ($p) => [
                'id'           => (string) $p['id'],
                'name'         => (string) ($p['name'] ?? ''),
                'access_token' => (string) ($p['access_token'] ?? ''),
                'category'     => (string) ($p['category'] ?? ''),
            ], $pages['pages']),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return view('admin.messenger.select-page', ['pages' => $pages['pages']]);
    }

    // ─── OAuth: user picked a Page from the picker ───────────────────

    public function select(Request $request)
    {
        $data = $request->validate(['page_id' => 'required|string|max:64']);

        $stashed = Session::pull(self::PICK_SESSION_KEY);
        if (! is_array($stashed) || ($stashed['expires_at'] ?? 0) < now()->timestamp) {
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Page selection expired. Please connect again.');
        }

        $picked = collect($stashed['pages'])->firstWhere('id', $data['page_id']);
        if (! $picked) {
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'That Page is no longer in the pending list.');
        }

        return $this->connectPage($request, $picked);
    }

    // ─── OAuth: disconnect a page ────────────────────────────────────

    public function disconnect(Request $request, int $pageId): RedirectResponse
    {
        $page = Page::withoutGlobalScope('tenant')
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('id', $pageId)
            ->first();

        if (! $page) {
            return back()->with('error', 'Page not found.');
        }

        // Best-effort remote unsubscribe, then hard-delete locally.
        // If the remote call fails (token expired), we still clean the
        // DB row — otherwise a stale row keeps blocking reconnection.
        try {
            $this->graph->subscribePage($page->access_token, $page->page_id, []); // empty fields = still subscribed but 0 fields, benign
            // Real unsubscribe is DELETE /{page_id}/subscribed_apps; call it via a low-level HTTP request:
            \Illuminate\Support\Facades\Http::delete(
                "https://graph.facebook.com/" . config('services.meta.graph_version', 'v21.0') . "/{$page->page_id}/subscribed_apps",
                ['access_token' => $page->access_token]
            );
        } catch (\Throwable $e) {
            Log::channel('messenger')->info('Disconnect: remote unsubscribe threw — proceeding with local delete', [
                'page_id' => $page->page_id,
                'error'   => $e->getMessage(),
            ]);
        }

        $page->delete();

        return redirect()->route('tenant_admin.messenger.settings')
            ->with('success', 'Page disconnected.');
    }

    // ─── Shared: create the row + subscribe webhook ──────────────────

    private function connectPage(Request $request, array $pageData): RedirectResponse
    {
        $pageId      = (string) $pageData['id'];
        $pageName    = (string) ($pageData['name'] ?? 'Facebook Page');
        $accessToken = trim((string) ($pageData['access_token'] ?? ''));

        if ($accessToken === '') {
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', 'Facebook did not return a Page access token. The account may lack pages_manage_metadata permission.');
        }

        // Subscribe the app to the Page's webhook events. The Meta dashboard
        // does NOT do this automatically — see feedback_messenger_scopes.md.
        $sub = $this->graph->subscribePage($accessToken, $pageId, [
            'messages',
            'messaging_postbacks',
            'message_deliveries',
            'message_reads',
            'message_echoes',
        ]);

        if (! $sub['ok']) {
            $err = $sub['error']['message'] ?? 'unknown';
            Log::channel('messenger')->warning('Connect: subscribe_apps failed', ['page_id' => $pageId, 'error' => $sub['error']]);
            return redirect()->route('tenant_admin.messenger.settings')
                ->with('error', "Could not subscribe the app to that Page: {$err}. Grant pages_manage_metadata and retry.");
        }

        Page::withoutGlobalScope('tenant')->updateOrCreate(
            ['page_id' => $pageId],
            [
                'tenant_id'         => $request->user()->tenant_id,
                'page_name'         => $pageName,
                'access_token'      => $accessToken,
                'enabled'           => true,
                'subscribed_at'     => now(),
                'disconnected_at'   => null,
                'disconnect_reason' => null,
                'meta'              => [
                    'category'    => (string) ($pageData['category'] ?? ''),
                    'connected_by'=> $request->user()->id,
                ],
            ]
        );

        return redirect()->route('tenant_admin.messenger.settings')
            ->with('success', "Connected {$pageName}. Messenger is now live.");
    }
}
