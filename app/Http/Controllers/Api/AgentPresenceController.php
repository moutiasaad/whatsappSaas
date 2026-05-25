<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AgentPresenceController extends Controller
{
    private const HEARTBEAT_TTL = 90;

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        Cache::put($this->key($user->id), now()->timestamp, self::HEARTBEAT_TTL);

        return response()->json(['ok' => true, 'ttl' => self::HEARTBEAT_TTL]);
    }

    public function online(Request $request): JsonResponse
    {
        $user  = $request->user();
        $now   = now()->timestamp;

        $agents = User::query()
            ->whereIn('role', ['admin', 'supervisor', 'agent'])
            ->where('is_active', true)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'tenant_id']);

        $online = $agents->map(function ($agent) use ($now) {
            $last = Cache::get($this->key($agent->id));
            $isOnline = $last && ($now - (int) $last) <= self::HEARTBEAT_TTL;
            return [
                'id'      => $agent->id,
                'name'    => $agent->name,
                'role'    => $agent->role,
                'online'  => (bool) $isOnline,
                'last_seen' => $last ? Carbon::createFromTimestamp((int) $last)->toIso8601String() : null,
            ];
        })->sortByDesc('online')->values();

        return response()->json([
            'data'         => $online,
            'online_count' => $online->where('online', true)->count(),
        ]);
    }

    private function key(int $userId): string
    {
        return "agent_presence:{$userId}";
    }
}
