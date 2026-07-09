<?php

namespace App\Http\Controllers\WebChat\Public;

use App\Http\Controllers\Controller;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Visitor;
use App\Models\WebChat\Widget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Widget $widget */
        $widget = $request->attributes->get('webchat_widget');

        $data = $request->validate([
            'visitor_token' => ['nullable', 'string', 'max:64'],
            'name'          => ['nullable', 'string', 'max:120'],
            'email'         => ['nullable', 'email', 'max:190'],
            'page_url'      => ['nullable', 'string', 'max:2048'],
            'referrer'      => ['nullable', 'string', 'max:2048'],
            'attributes'    => ['nullable', 'array'],
        ]);

        $visitor = null;

        if (!empty($data['visitor_token'])) {
            $visitor = Visitor::withoutGlobalScope('tenant')
                ->where('token', $data['visitor_token'])
                ->where('tenant_id', $widget->tenant_id)
                ->where('widget_id', $widget->id)
                ->first();
        }

        if (!$visitor) {
            $visitor = new Visitor();
            $visitor->tenant_id = $widget->tenant_id;
            $visitor->widget_id = $widget->id;
        }

        if (!empty($data['name']))       $visitor->name = $data['name'];
        if (!empty($data['email']))      $visitor->email = $data['email'];
        if (!empty($data['attributes'])) $visitor->attributes = $data['attributes'];

        $visitor->last_seen_at = now();
        $visitor->save();

        $activeConversation = Conversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $widget->tenant_id)
            ->where('visitor_id', $visitor->id)
            ->where('status', '!=', Conversation::STATUS_CLOSED)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'visitor_token' => $visitor->token,
            'widget' => [
                'name'               => $widget->name,
                'header_subtitle'    => $widget->header_subtitle,
                'welcome_message'    => $widget->welcome_message,
                'suggestions'        => $widget->suggestions ?? [],
                'offline_message'    => $widget->offline_message,
                'theme_color'        => $widget->theme_color,
                'position'           => $widget->position,
                'launcher_text'      => $widget->launcher_text,
                'launcher_icon'      => $widget->launcher_icon ?: 'chat',
                'bubble_style'       => $widget->bubble_style ?: 'soft',
                'show_branding'      => (bool) $widget->show_branding,
                'pre_chat_ask_email' => (bool) $widget->pre_chat_ask_email,
            ],
            // Reverb credentials the widget needs to open a WebSocket. Values
            // fall back to safe defaults so the widget still boots even when
            // BROADCAST_CONNECTION is not `reverb` — it just runs on HTTP
            // polling only (see widget.js).
            'reverb' => [
                'key'    => (string) (config('broadcasting.connections.reverb.key') ?: ''),
                'host'   => (string) (config('broadcasting.connections.reverb.options.host') ?: $request->getHost()),
                'port'   => (int)    (config('broadcasting.connections.reverb.options.port') ?: 443),
                'scheme' => (string) (config('broadcasting.connections.reverb.options.scheme') ?: 'https'),
            ],
            'active_conversation' => $activeConversation ? [
                'uuid'   => $activeConversation->uuid,
                'status' => $activeConversation->status,
            ] : null,
            'runtime' => [
                'poll_interval_ms'   => (int) config('webchat.widget_poll_interval_ms', 4000),
                'message_max_length' => (int) config('webchat.message_max_length', 4000),
            ],
        ]);
    }
}
