<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Jobs\SendOutgoingMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('view', $conversation);

        $perPage = min((int) $request->get('per_page', 40), 100);

        $messages = $conversation->messages()
            ->when($request->before, fn($q) => $q->where('id', '<', $request->before))
            ->latest('id')
            ->limit($perPage + 1)
            ->get();

        $hasMore  = $messages->count() > $perPage;
        $messages = $messages->take($perPage)->reverse()->values();

        return response()->json([
            'data'        => $messages,
            'next_cursor' => $hasMore ? $messages->first()?->id : null,
        ]);
    }

    public function store(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('reply', $conversation);
        $request->validate([
            'body'      => 'required_without:media_url|nullable|string|max:4096',
            'media_url' => 'nullable|url',
            'type'      => 'in:text,image,video,audio,document',
        ]);

        $conversation->update(['unread_count' => 0]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id'       => $conversation->tenant_id,
            'direction'       => 'out',
            'author_type'     => 'agent',
            'author_id'       => $request->user()->id,
            'type'            => $request->type ?? 'text',
            'body'            => $request->body,
            'media_url'       => $request->media_url,
            'status'          => 'pending',
        ]);

        SendOutgoingMessage::dispatch($message);
        broadcast(new MessageReceived($message))->toOthers();

        return response()->json($message, 201);
    }

    public function storeNote(Conversation $conversation, Request $request): JsonResponse
    {
        $this->authorize('view', $conversation);
        $request->validate(['body' => 'required|string|max:2000']);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id'       => $conversation->tenant_id,
            'direction'       => 'out',
            'author_type'     => 'system',
            'author_id'       => $request->user()->id,
            'type'            => 'text',
            'body'            => $request->body,
            'status'          => 'sent',
            'ai_metadata'     => ['is_note' => true],
        ]);

        return response()->json($message, 201);
    }
}
