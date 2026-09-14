<?php

namespace App\Http\Controllers\WebChat\Public;

use App\Events\WebChat\WebChatConversationReopened;
use App\Events\WebChat\WebChatMessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebChatIncomingMessage;
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

        // If the visitor sends a new message after the ticket was closed, mirror
        // the WhatsApp behavior: silently reopen and let the AI take over again.
        // Human agents can still take the conversation via the pool → claim flow.
        $wasReopened = false;
        if ($conversation->isClosed()) {
            $conversation->status     = Conversation::STATUS_BOT;
            $conversation->closed_at  = null;
            $conversation->closed_by  = null;
            $conversation->claimed_by = null;
            $conversation->claimed_at = null;
            // Keep the ticket title so history stays in the Archive.
            $conversation->save();
            $wasReopened = true;
        }

        $message = DB::transaction(function () use ($conversation, $data) {
            $conversation->last_activity_at = now();
            $conversation->save();

            return Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::SENDER_VISITOR,
                'sender_id'       => null,
                'body'            => $data['body'],
            ]);
        });

        if ($wasReopened) {
            rescue(fn () => event(new WebChatConversationReopened($conversation->fresh())));
        }

        rescue(fn () => event(new WebChatMessageSent($message->fresh(['conversation']))));

        // Hand off to the AI on any non-closed conversation. The job/service
        // decides whether to answer or fall back to `pending`:
        //   - bot:      AI answers, keeps `bot`. On failure, promotes to `pending`.
        //   - pending:  AI answers, promotes back to `bot`. Rescues visitors
        //               stranded in the pool when no human ever claimed.
        //   - assigned: AI only answers if tenant opted-in via
        //               ai_settings.reply_when_claimed.
        if (!$conversation->isClosed()) {
            rescue(fn () => ProcessWebChatIncomingMessage::dispatch($conversation->id, $message->id));
        }

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
                // Only the attachment is exposed, never the whole meta blob —
                // it is the visitor reading this, and meta is ours to use.
                'attachment'  => $m->meta['attachment'] ?? null,
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
