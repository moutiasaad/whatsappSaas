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
            // An attachment can travel on its own, so the body is only required
            // when there is nothing else to send.
            'body'       => ['required_without:media_url', 'nullable', 'string', 'max:' . (int) config('webchat.message_max_length', 4000)],
            'media_url'  => ['nullable', 'url', 'max:2000'],
            'type'       => ['nullable', 'in:image,video,audio,document'],
            'file_name'  => ['nullable', 'string', 'max:255'],
            'media_path' => ['nullable', 'string', 'max:500'],
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

        // The url is rendered inside the visitor's page, so only accept files
        // this app is hosting — never an arbitrary address an agent could paste.
        $attachment = null;
        if (!empty($data['media_url'])) {
            abort_unless($this->isOwnMedia($data['media_url']), 422, 'media_not_hosted_here');

            $attachment = [
                'url'  => $data['media_url'],
                'type' => $data['type'] ?? 'document',
                'name' => $data['file_name'] ?? null,
                'path' => $data['media_path'] ?? null,
            ];
        }

        $message = DB::transaction(function () use ($conversation, $data, $user, $attachment) {
            $m = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::SENDER_AGENT,
                'sender_id'       => $user->id,
                'body'            => $data['body'] ?? '',
                'meta'            => $attachment ? ['attachment' => $attachment] : null,
            ]);

            $conversation->last_activity_at = now();
            $conversation->save();

            return $m;
        });

        rescue(fn () => event(new WebChatMessageSent($message->fresh(['conversation']))));

        return response()->json([
            'message' => [
                'id'              => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_type'     => $message->sender_type,
                'sender_id'       => $message->sender_id,
                'body'            => $message->body,
                'attachment'      => $attachment,
                'created_at'      => $message->created_at?->toISOString(),
            ],
        ], 201);
    }

    /** Is this url served by us? Guards what gets rendered on a visitor's page. */
    private function isOwnMedia(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null
            && strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) === 0;
    }
}
