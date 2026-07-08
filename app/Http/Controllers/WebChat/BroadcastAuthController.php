<?php

namespace App\Http\Controllers\WebChat;

use App\Http\Controllers\Controller;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Visitor;
use App\Models\WebChat\Widget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Broadcast auth signer for the widget (visitor-side).
 *
 * Agents authorize via the framework's built-in `/broadcasting/auth` route,
 * which reads `routes/channels.php` (session guard). Visitors have no
 * session, so this endpoint validates the visitor bearer token and, if
 * the visitor owns the requested `private-webchat.conversation.{uuid}`
 * channel, returns the Pusher/Reverb private-channel auth signature.
 */
class BroadcastAuthController extends Controller
{
    protected const CHANNEL_PREFIX = 'private-webchat.conversation.';

    public function authenticate(Request $request): JsonResponse
    {
        /** @var Widget|null $widget */
        $widget = $request->attributes->get('webchat_widget');
        /** @var Visitor|null $visitor */
        $visitor = $request->attributes->get('webchat_visitor');

        if (!$widget || !$visitor) {
            abort(401, 'webchat_visitor_token_invalid');
        }

        $socketId    = (string) $request->input('socket_id');
        $channelName = (string) $request->input('channel_name');

        if ($socketId === '' || $channelName === '') {
            abort(422, 'missing_socket_or_channel');
        }

        if (!str_starts_with($channelName, self::CHANNEL_PREFIX)) {
            abort(403, 'channel_not_allowed');
        }

        $uuid = substr($channelName, strlen(self::CHANNEL_PREFIX));

        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->where('tenant_id', $widget->tenant_id)
            ->where('widget_id', $widget->id)
            ->where('visitor_id', $visitor->id)
            ->first();

        if (!$conversation) {
            abort(403, 'not_conversation_owner');
        }

        $appKey    = (string) config('broadcasting.connections.reverb.key');
        $appSecret = (string) config('broadcasting.connections.reverb.secret');

        if ($appKey === '' || $appSecret === '') {
            abort(500, 'reverb_credentials_missing');
        }

        $stringToSign = $socketId . ':' . $channelName;
        $signature    = hash_hmac('sha256', $stringToSign, $appSecret);

        return response()->json([
            'auth' => $appKey . ':' . $signature,
        ]);
    }
}
