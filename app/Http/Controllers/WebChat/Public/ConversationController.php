<?php

namespace App\Http\Controllers\WebChat\Public;

use App\Events\WebChat\WebChatConversationRequested;
use App\Http\Controllers\Controller;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Visitor;
use App\Models\WebChat\Widget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Widget $widget */
        $widget = $request->attributes->get('webchat_widget');
        /** @var Visitor $visitor */
        $visitor = $request->attributes->get('webchat_visitor');

        $data = $request->validate([
            'page_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ]);

        $conversation = Conversation::create([
            'tenant_id'        => $widget->tenant_id,
            'widget_id'        => $widget->id,
            'visitor_id'       => $visitor->id,
            'status'           => Conversation::STATUS_BOT,
            'visitor_name'     => $visitor->name,
            'visitor_email'    => $visitor->email,
            'page_url'         => $data['page_url'] ?? null,
            'referrer'         => $data['referrer'] ?? null,
            'user_agent'       => substr((string) $request->userAgent(), 0, 1024),
            'ip'               => $request->ip(),
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'uuid'   => $conversation->uuid,
            'status' => $conversation->status,
        ], 201);
    }

    public function requestAgent(Request $request, string $key, string $uuid): JsonResponse
    {
        /** @var Widget $widget */
        $widget = $request->attributes->get('webchat_widget');
        /** @var Visitor $visitor */
        $visitor = $request->attributes->get('webchat_visitor');

        $conversation = $this->findConversationOr404($widget, $visitor, $uuid);

        if ($conversation->isClosed()) {
            return response()->json(['error' => 'conversation_closed'], 409);
        }

        if ($conversation->isBot() || $conversation->isPending()) {
            $conversation->status           = Conversation::STATUS_PENDING;
            $conversation->last_activity_at = now();
            $conversation->save();

            event(new WebChatConversationRequested($conversation));
        }

        return response()->json([
            'status'          => $conversation->status,
            'uuid'            => $conversation->uuid,
            'queue_position'  => null,
            'estimated_wait'  => null,
        ]);
    }

    protected function findConversationOr404(Widget $widget, Visitor $visitor, string $uuid): Conversation
    {
        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->where('tenant_id', $widget->tenant_id)
            ->where('widget_id', $widget->id)
            ->where('visitor_id', $visitor->id)
            ->first();

        if (!$conversation) {
            abort(404, 'webchat_conversation_not_found');
        }

        return $conversation;
    }
}
