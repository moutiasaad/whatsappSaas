<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsAppInstance;

class DashboardController extends Controller
{
    public function index()
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        // Super admins with no tenant get a bare platform overview
        if (!$tenant) {
            return $this->superAdminDashboard();
        }

        $stats = [
            'total_conversations' => Conversation::count(),
            'conversations_today' => Conversation::whereDate('created_at', today())->count(),
            'pool_count'          => Conversation::pool()->count(),
            'agents_online'       => User::where('tenant_id', $tenant->id)->where('role', 'agent')->where('is_active', true)->count(),
            'total_agents'        => User::where('tenant_id', $tenant->id)->where('role', 'agent')->count(),
            'messages_today'      => Message::whereDate('created_at', today())->count(),
            'messages_growth'     => $this->messagesGrowth(),
        ];

        $activeConversations = Conversation::with(['customer', 'instance', 'ownerAgent'])
            ->whereIn('state', ['pool', 'claimed'])
            ->orderByDesc('last_message_at')
            ->limit(8)
            ->get();

        $instances = WhatsAppInstance::orderBy('name')->get();

        $teamLoad = Team::withCount([
            'conversations as active_count' => fn ($q) => $q->whereIn('state', ['pool', 'claimed']),
        ])->get()->map(function ($t) {
            $t->capacity = max(1, $t->users()->where('role', 'agent')->count() * 5);
            return $t;
        });

        $recentEvents = ConversationEvent::with('actor')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $aiSettings = $tenant->aiSettings;

        return view('admin.dashboard.index', compact(
            'stats', 'activeConversations', 'instances', 'teamLoad', 'recentEvents', 'aiSettings'
        ));
    }

    private function superAdminDashboard()
    {
        $stats = [
            'total_conversations' => 0,
            'conversations_today' => 0,
            'pool_count'          => 0,
            'agents_online'       => 0,
            'total_agents'        => 0,
            'messages_today'      => 0,
            'messages_growth'     => 0,
        ];

        return view('admin.dashboard.index', [
            'stats'               => $stats,
            'activeConversations' => collect(),
            'instances'           => collect(),
            'teamLoad'            => collect(),
            'recentEvents'        => collect(),
            'aiSettings'          => null,
        ]);
    }

    private function messagesGrowth(): int
    {
        $today     = Message::whereDate('created_at', today())->count();
        $yesterday = Message::whereDate('created_at', today()->subDay())->count();
        if ($yesterday === 0) return 0;
        return (int) round((($today - $yesterday) / $yesterday) * 100);
    }
}
