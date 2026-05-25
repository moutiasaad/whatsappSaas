<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\SendOutgoingMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'type'       => 'nullable|in:text,image,video,audio,document',
            'file_name'  => 'nullable|string|max:255',
            'media_path' => 'nullable|string|max:500',
        ]);

        $conversation->update(['unread_count' => 0]);

        $aiMeta = null;
        if ($request->file_name || $request->media_path) {
            $aiMeta = array_filter([
                'file_name'  => $request->file_name,
                'media_path' => $request->media_path,
            ]);
        }

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
            'ai_metadata'     => $aiMeta,
        ]);

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => $message->body ?: ($message->media_url ? ucfirst((string) $message->type) : null),
        ]);

        SendOutgoingMessage::dispatch($message);

        return response()->json($message->fresh(), 201);
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

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => '[Note] ' . $message->body,
        ]);

        try {
            broadcast(new MessageSent($message->fresh()))->toOthers();
        } catch (\Throwable) {}

        return response()->json($message, 201);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:jpeg,jpg,png,gif,webp,mp4,mov,avi,mp3,ogg,aac,pdf,doc,docx,xls,xlsx,ppt,pptx,zip',
        ]);

        $file      = $request->file('file');
        $mimeType  = $file->getMimeType();
        $origName  = $file->getClientOriginalName();
        $path      = $file->store('media', 'public');
        $url       = Storage::disk('public')->url($path);

        $waType = match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default                               => 'document',
        };

        return response()->json([
            'url'        => $url,
            'type'       => $waType,
            'file_name'  => $origName,
            'mime_type'  => $mimeType,
            'media_path' => $path,
        ]);
    }
}
