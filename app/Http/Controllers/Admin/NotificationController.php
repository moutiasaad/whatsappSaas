<?php

namespace App\Http\Controllers\Admin;

use App\Events\NotificationCreated;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $sender = $request->user();

        if ($sender->isSuperAdmin()) {
            $tenants = Tenant::orderBy('name')->get(['id', 'name']);
            $users   = collect();
        } else {
            $tenants = collect();
            $users   = User::where('tenant_id', $sender->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']);
        }

        $history = AppNotification::with('sender:id,name')
            ->when(!$sender->isSuperAdmin(), fn($q) => $q->where('tenant_id', $sender->tenant_id))
            ->when($sender->isSuperAdmin(), fn($q) => $q->whereNull('tenant_id')->orWhereNotNull('tenant_id'))
            ->whereNotNull('sender_id')
            ->latest()
            ->paginate(20);

        $stats = [
            'sent_today' => AppNotification::when(
                !$sender->isSuperAdmin(),
                fn($q) => $q->where('tenant_id', $sender->tenant_id)
            )->whereDate('created_at', today())->count(),

            'sent_total' => AppNotification::when(
                !$sender->isSuperAdmin(),
                fn($q) => $q->where('tenant_id', $sender->tenant_id)
            )->count(),

            'unread_total' => AppNotification::when(
                !$sender->isSuperAdmin(),
                fn($q) => $q->where('tenant_id', $sender->tenant_id)
            )->unread()->count(),
        ];

        return view('admin.notifications.index', compact('tenants', 'users', 'history', 'stats'));
    }

    public function send(Request $request)
    {
        $sender = $request->user();

        $rules = [
            'recipient' => 'required|in:all,specific',
            'title'     => 'required|string|max:255',
            'body'      => 'required|string|max:2000',
            'type'      => 'nullable|in:manual,renewal,system',
        ];

        if ($sender->isSuperAdmin()) {
            $rules['tenant_id'] = 'nullable|exists:tenants,id';
            $rules['user_id']   = 'required_if:recipient,specific|nullable|exists:users,id';
        } else {
            $rules['user_id'] = 'required_if:recipient,specific|nullable|exists:users,id';
        }

        $data = $request->validate($rules);
        $type = $data['type'] ?? 'manual';

        if ($data['recipient'] === 'all') {
            if ($sender->isSuperAdmin() && !empty($data['tenant_id'])) {
                $recipients = User::where('tenant_id', $data['tenant_id'])->get();
            } elseif ($sender->isSuperAdmin()) {
                $recipients = User::whereNotNull('tenant_id')->get();
            } else {
                $recipients = User::where('tenant_id', $sender->tenant_id)->get();
            }

            foreach ($recipients as $recipient) {
                $notif = AppNotification::create([
                    'tenant_id' => $recipient->tenant_id,
                    'user_id'   => $recipient->id,
                    'sender_id' => $sender->id,
                    'type'      => $type,
                    'title'     => $data['title'],
                    'body'      => $data['body'],
                ]);
                broadcast(new NotificationCreated($notif));
            }

            $count     = $recipients->count();
            $message   = __('ui.notifications_page.sent_to_all', ['count' => $count]);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'message' => $message, 'count' => $count]);
            }
            return back()->with('success', $message);
        }

        // Specific user
        $targetUser = User::findOrFail($data['user_id']);

        if (!$sender->isSuperAdmin() && $targetUser->tenant_id !== $sender->tenant_id) {
            abort(403);
        }

        $notif = AppNotification::create([
            'tenant_id' => $targetUser->tenant_id,
            'user_id'   => $targetUser->id,
            'sender_id' => $sender->id,
            'type'      => $type,
            'title'     => $data['title'],
            'body'      => $data['body'],
        ]);
        broadcast(new NotificationCreated($notif));

        $message = __('ui.notifications_page.sent_to_user', ['name' => $targetUser->name]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }
}
