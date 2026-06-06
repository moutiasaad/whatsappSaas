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
