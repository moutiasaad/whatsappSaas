<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    public function index(Request $request)
    {
        $notifications = AppNotification::forUser($request->user()->id)
            ->latest()
            ->limit(20)
            ->get(['id', 'type', 'title', 'body', 'read_at', 'created_at']);

        return response()->json(['data' => $notifications]);
    }

    public function unreadCount(Request $request)
    {
        $count = AppNotification::forUser($request->user()->id)->unread()->count();
        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, int $id)
    {
        $notification = AppNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        AppNotification::forUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $query = \App\Models\AppNotification::with('sender:id,name')
            ->when(!$user->isSuperAdmin(), fn($q) => $q->where('tenant_id', $user->tenant_id))
            ->whereNotNull('sender_id')
            ->latest();

        $paginated = $query->paginate(15);

        $paginated->getCollection()->transform(function ($n) {
            return [
                'id'          => $n->id,
                'title'       => $n->title,
                'body_preview'=> \Illuminate\Support\Str::limit($n->body, 80),
                'type'        => $n->type,
                'type_label'  => ucfirst($n->type),
                'sender_name' => $n->sender?->name ?? 'System',
                'date'        => $n->created_at->diffForHumans(),
                'created_at'  => $n->created_at->toISOString(),
            ];
        });

        return response()->json($paginated);
    }

    public function tenantUsers(Request $request, int $tenantId)
    {
        if (!$request->user()->isSuperAdmin()) {
            abort(403);
        }

        $users = \App\Models\User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json(['data' => $users]);
    }
}
