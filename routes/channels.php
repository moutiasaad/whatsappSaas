<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

// Pool stream - agents/supervisors per team, admins can observe all team pools.
Broadcast::channel('tenant.{tenant}.team.{team}.pool', function ($user, $tenant, $team) {
    if ((string) $user->tenant_id !== (string) $tenant) return false;
    if ($user->isAdmin() || $user->isSuperAdmin()) return true;
    return $user->teams->contains('id', $team);
});

// Tenant-wide pool stream for admins.
Broadcast::channel('tenant.{tenant}.pool', function ($user, $tenant) {
    return (string) $user->tenant_id === (string) $tenant && ($user->isAdmin() || $user->isSuperAdmin());
});

// Live conversation stream.
Broadcast::channel('tenant.{tenant}.conversation.{conv}', function ($user, $tenant, $conv) {
    if ((string) $user->tenant_id !== (string) $tenant) return false;
    if ($user->isAdmin() || $user->isSuperAdmin()) return true;

    $conversation = Conversation::withoutGlobalScope('tenant')->find($conv);
    if (!$conversation) return false;

    if ($user->isSupervisor()) {
        return $user->teams->contains('id', $conversation->team_id);
    }

    return $conversation->owner_agent_id === $user->id;
});

// Personal notifications.
Broadcast::channel('tenant.{tenant}.user.{userId}', function ($user, $tenant, $userId) {
    return (string) $user->tenant_id === (string) $tenant && (string) $user->id === (string) $userId;
});

// Instance health updates (admins only).
Broadcast::channel('tenant.{tenant}.instances', function ($user, $tenant) {
    return (string) $user->tenant_id === (string) $tenant && ($user->isAdmin() || $user->isSuperAdmin());
});
