<?php

namespace App\Http\Controllers\WebChat;

use App\Events\WebChat\WebChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $user     = $request->user();
        $tenantId = $user->tenant_id;

        if (!$tenantId) {
            abort(404, 'webchat_conversation_not_found');
        }

        // Tenant-hardened lookup — resolveRouteBinding strips the global scope,
        // so a guessed uuid must not reach across tenants.
        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$conversation) {
            abort(404, 'webchat_conversation_not_found');
        }

        // Reply guard. Closed check first: after close(), claimed_by is null
        // (lock released), so the closed state is the stronger constraint and
        // deserves the more specific error.
        abort_if($conversation->isClosed(), 409, 'conversation_closed');
        abort_unless($conversation->claimed_by === $user->id, 403, 'not_your_conversation');

        $message = DB::transaction(function () use ($conversation, $data, $user) {
            $m = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::SENDER_AGENT,
                'sender_id'       => $user->id,
                'body'            => $data['body'],
            ]);

            $conversation->last_activity_at = now();
            $conversation->save();

            return $m;
        });

        event(new WebChatMessageSent($message->fresh(['conversation'])));

        return response()->json([
            'message' => [
                'id'              => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_type'     => $message->sender_type,
                'sender_id'       => $message->sender_id,
                'body'            => $message->body,
                'created_at'      => $message->created_at?->toISOString(),
            ],
        ], 201);
    }
}
