<?php

namespace App\Services\Messenger;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * GraphApiClient
 *
 * Thin HTTP wrapper around Meta's Graph API for Messenger. Kept
 * deliberately dumb — no persistence, no model access, no business
 * rules. All it does is speak HTTP + parse Meta's response shape into
 * a normalised `MessengerApiResult` array.
 *
 * The Graph API version comes from config/services.php (env-driven so
 * a Meta minor-version bump is a one-line change). See docs at
 * https://developers.facebook.com/docs/messenger-platform/reference/send-api
 *
 * Every call includes `appsecret_proof` — an HMAC-SHA256 of the token
 * signed with the app secret. Meta accepts calls without it, but
 * enabling it means a leaked token cannot be used by any process that
 * doesn't also know the app secret. Cheap defense-in-depth.
 */
class GraphApiClient
{
    private string $version;
    private string $appSecret;

    public function __construct()
    {
        $this->version   = (string) config('services.meta.graph_version', 'v21.0');
        $this->appSecret = (string) config('services.meta.app_secret', '');
    }

    /**
     * POST /me/messages — send a text message.
     *
     * Returns: ['ok' => bool, 'mid' => ?string, 'error' => ?array,
     *           'status' => int, 'body' => array]
     *
     * On Meta error the shape is:
     *   ['ok' => false, 'error' => ['code' => int, 'message' => string,
     *    'type' => string, 'subcode' => ?int], ...]
     *
     * Common error codes worth naming:
     *   190 = token invalid/expired (subcode 460 = user changed password)
     *   200 = missing permission (e.g. pages_manage_metadata)
     *   10  = message outside 24h window without MESSAGE_TAG
     *   4   = app rate limit hit
     */
    public function sendText(string $pageAccessToken, string $psid, string $text, string $messagingType = 'RESPONSE'): array
    {
        $response = $this->post($pageAccessToken, '/me/messages', [
            'recipient'      => ['id' => $psid],
            'messaging_type' => $messagingType,
            'message'        => ['text' => $text],
        ]);

        return $this->normaliseSend($response);
    }

    /**
     * GET /{psid} — fetch visitor profile (first_name, last_name, profile_pic).
     *
     * profile_pic URLs expire — download or refresh periodically.
     * Returns: ['ok' => bool, 'profile' => ?array, 'error' => ?array]
     */
    public function getUserProfile(string $pageAccessToken, string $psid): array
    {
        $response = $this->get($pageAccessToken, "/{$psid}", [
            'fields' => 'first_name,last_name,profile_pic',
        ]);

        if (! $response->successful()) {
            return [
                'ok'      => false,
                'profile' => null,
                'error'   => $this->extractError($response),
                'status'  => $response->status(),
            ];
        }

        return [
            'ok'      => true,
            'profile' => $response->json(),
            'error'   => null,
            'status'  => $response->status(),
        ];
    }

    /**
     * POST /{page_id}/subscribed_apps — subscribe the app to a Page's
     * webhook events. This is the call Meta's dashboard UI does NOT
     * always run for you (Phase 3 launch trap 2026-09-17). Called by
     * Phase 5's tenant connect flow after minting the Page token.
     */
    public function subscribePage(string $pageAccessToken, string $pageId, array $fields): array
    {
        $response = Http::asForm()->post(
            $this->url("/{$pageId}/subscribed_apps"),
            [
                'access_token'      => $pageAccessToken,
                'appsecret_proof'   => $this->appsecretProof($pageAccessToken),
                'subscribed_fields' => implode(',', $fields),
            ]
        );

        if (! $response->successful() || ! ($response->json('success') === true)) {
            return [
                'ok'     => false,
                'error'  => $this->extractError($response),
                'status' => $response->status(),
            ];
        }

        return ['ok' => true, 'status' => 200];
    }

    // ─── Internals ────────────────────────────────────────────────────

    private function post(string $token, string $path, array $body): Response
    {
        return Http::withQueryParameters([
            'access_token'    => $token,
            'appsecret_proof' => $this->appsecretProof($token),
        ])->post($this->url($path), $body);
    }

    private function get(string $token, string $path, array $query = []): Response
    {
        return Http::get($this->url($path), array_merge($query, [
            'access_token'    => $token,
            'appsecret_proof' => $this->appsecretProof($token),
        ]));
    }

    private function url(string $path): string
    {
        return "https://graph.facebook.com/{$this->version}" . (str_starts_with($path, '/') ? $path : "/{$path}");
    }

    /**
     * appsecret_proof = HMAC-SHA256(token) keyed by app_secret. Meta's
     * fallback protection against a leaked token being used from an
     * unrelated app. Empty string when app_secret isn't configured
     * (Meta then accepts the call without the proof).
     */
    private function appsecretProof(string $token): string
    {
        if ($this->appSecret === '') {
            return '';
        }
        return hash_hmac('sha256', $token, $this->appSecret);
    }

    private function normaliseSend(Response $response): array
    {
        if (! $response->successful()) {
            return [
                'ok'     => false,
                'mid'    => null,
                'error'  => $this->extractError($response),
                'status' => $response->status(),
                'body'   => $response->json() ?: [],
            ];
        }

        $mid = $response->json('message_id');
        if (! is_string($mid) || $mid === '') {
            // Meta returned 200 but no message_id — treat as failure so
            // the caller doesn't confidently persist a broken outbound.
            Log::channel('messenger')->warning('Graph API sendText: 200 but no message_id', [
                'body' => $response->json(),
            ]);
            return [
                'ok'     => false,
                'mid'    => null,
                'error'  => ['code' => 0, 'message' => 'Missing message_id in response', 'type' => 'ClientError'],
                'status' => 200,
                'body'   => $response->json() ?: [],
            ];
        }

        return [
            'ok'     => true,
            'mid'    => $mid,
            'error'  => null,
            'status' => 200,
            'body'   => $response->json() ?: [],
        ];
    }

    private function extractError(Response $response): array
    {
        $err = $response->json('error');
        if (! is_array($err)) {
            return [
                'code'    => 0,
                'message' => 'HTTP ' . $response->status(),
                'type'    => 'HttpError',
                'subcode' => null,
            ];
        }
        return [
            'code'    => (int) ($err['code'] ?? 0),
            'message' => (string) ($err['message'] ?? 'Unknown'),
            'type'    => (string) ($err['type'] ?? 'OAuthException'),
            'subcode' => isset($err['error_subcode']) ? (int) $err['error_subcode'] : null,
        ];
    }
}
