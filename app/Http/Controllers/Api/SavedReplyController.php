<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedReplyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->ensureDefaults($user);

        $replies = SavedReply::query()
            ->visibleTo($user)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'scope', 'owner_user_id', 'title', 'shortcut', 'body', 'sort_order']);

        return response()->json(['data' => $replies]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'title'      => 'required|string|max:120',
            'shortcut'   => 'nullable|string|max:32',
            'body'       => 'required|string|max:4000',
            'scope'      => ['nullable', Rule::in(['tenant', 'personal'])],
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $scope = $data['scope'] ?? 'personal';
        if ($scope === 'tenant' && !($user->isAdmin() || $user->isSuperAdmin() || $user->isSupervisor())) {
            $scope = 'personal';
        }

        $reply = SavedReply::create([
            'tenant_id'     => $user->tenant_id,
            'owner_user_id' => $scope === 'personal' ? $user->id : null,
            'scope'         => $scope,
            'title'         => $data['title'],
            'shortcut'      => $data['shortcut'] ?? null,
            'body'          => $data['body'],
            'sort_order'    => $data['sort_order'] ?? 0,
        ]);

        return response()->json($reply, 201);
    }

    public function update(Request $request, SavedReply $savedReply): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWrite($user, $savedReply);

        $data = $request->validate([
            'title'      => 'sometimes|required|string|max:120',
            'shortcut'   => 'nullable|string|max:32',
            'body'       => 'sometimes|required|string|max:4000',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $savedReply->fill($data)->save();

        return response()->json($savedReply);
    }

    public function destroy(Request $request, SavedReply $savedReply): JsonResponse
    {
        $this->authorizeWrite($request->user(), $savedReply);
        $savedReply->delete();
        return response()->json(['ok' => true]);
    }

    private function authorizeWrite($user, SavedReply $reply): void
    {
        if ($reply->tenant_id !== $user->tenant_id) {
            abort(403);
        }
        if ($reply->scope === 'personal' && $reply->owner_user_id !== $user->id) {
            abort(403);
        }
        if ($reply->scope === 'tenant' && !($user->isAdmin() || $user->isSuperAdmin() || $user->isSupervisor())) {
            abort(403);
        }
    }

    private function ensureDefaults($user): void
    {
        if (!$user->tenant_id) {
            return;
        }

        $exists = SavedReply::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('scope', 'tenant')
            ->exists();

        if ($exists) {
            return;
        }

        $defaults = [
            ['Greeting',  '/hi',     'Hello! Thanks for reaching out. How can I help you today?', 10],
            ['Hold on',   '/wait',   'Thanks for waiting. I am checking this now and will update you shortly.', 20],
            ['Resolved',  '/done',   'This issue is now resolved. Please confirm on your side.', 30],
            ['Follow up', '/follow', 'Just following up to make sure everything is working as expected. Let me know if you need anything else.', 40],
            ['Closing',   '/close',  'Glad we could help! I am closing this conversation — feel free to reach out anytime.', 50],
        ];

        foreach ($defaults as [$title, $shortcut, $body, $order]) {
            SavedReply::create([
                'tenant_id'     => $user->tenant_id,
                'owner_user_id' => null,
                'scope'         => 'tenant',
                'title'         => $title,
                'shortcut'      => $shortcut,
                'body'          => $body,
                'sort_order'    => $order,
            ]);
        }
    }
}
