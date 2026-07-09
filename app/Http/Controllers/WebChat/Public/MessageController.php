<?php

namespace App\Http\Controllers\WebChat\Public;

use App\Events\WebChat\WebChatConversationRequested;
use App\Events\WebChat\WebChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Message;
use App\Models\WebChat\Visitor;
use App\Models\WebChat\Widget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(Request $request, string $key, string $uuid): JsonResponse
    {
        /** @var Widget $widget */
        $widget = $request->attributes->get('webchat_widget');
        /** @var Visitor $visitor */
        $visitor = $request->attributes->get('webchat_visitor');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . (int) config('webchat.message_max_length', 4000)],
        ]);

        $conversation = $this->findConversationOr404($widget, $visitor, $uuid);

        if ($conversation->isClosed()) {
            return response()->json(['error' => 'conversation_closed'], 409);
        }

        $justPromoted = false;

        $message = DB::transaction(function () use ($conversation, $data, &$justPromoted) {
            if ($conversation->isBot()) {
                $conversation->status = Conversation::STATUS_PENDING;
                $justPromoted = true;
            }

            $conversation->last_activity_at = now();
            $conversation->save();

            return Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::SENDER_VISITOR,
                'sender_id'       => null,
                'body'            => $data['body'],
            ]);
        });

        if ($justPromoted) {
            rescue(fn () => event(new WebChatConversationRequested($conversation->fresh())));
        }

        rescue(fn () => event(new WebChatMessageSent($message->fresh(['conversation']))));

        return response()->json([
            'message' => [
                'id'              => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_type'     => $message->sender_type,
                'body'            => $message->body,
                'created_at'      => $message->created_at?->toISOString(),
            ],
            'conversation' => [
                'uuid'   => $conversation->uuid,
                'status' => $conversation->status,
            ],
        ], 201);
    }

    public function index(Request $request, string $key, string $uuid): JsonResponse
    {
        /** @var Widget $widget */
        $widget = $request->attributes->get('webchat_widget');
        /** @var Visitor $visitor */
        $visitor = $request->attributes->get('webchat_visitor');

        $after = (int) $request->query('after', 0);

        $conversation = $this->findConversationOr404($widget, $visitor, $uuid);

        $messages = Message::where('conversation_id', $conversation->id)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (Message $m) => [
                'id'          => $m->id,
                'sender_type' => $m->sender_type,
                'sender_id'   => $m->sender_id,
                'body'        => $m->body,
                'created_at'  => $m->created_at?->toISOString(),
            ]);

        return response()->json([
            'conversation' => [
                'uuid'   => $conversation->uuid,
                'status' => $conversation->status,
            ],
            'messages' => $messages,
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
