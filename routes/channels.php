<?php

use App\Models\Conversation;
use App\Models\WebChat\Conversation as WebChatConversation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

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

// Personal notifications (tenant-scoped).
Broadcast::channel('tenant.{tenant}.user.{userId}', function ($user, $tenant, $userId) {
    return (string) $user->tenant_id === (string) $tenant && (string) $user->id === (string) $userId;
});

// Personal notification channel — tenant-agnostic, works for super_admin (tenant_id = null).
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

// Instance health updates (admins only).
Broadcast::channel('tenant.{tenant}.instances', function ($user, $tenant) {
    return (string) $user->tenant_id === (string) $tenant && ($user->isAdmin() || $user->isSuperAdmin());
});

// ─── Web Live-Chat channels ──────────────────────────────────────────────
// Tenant presence stream for agents (new pending chats, list updates).
// Payload = {id,name} so the inbox can show who is online.
Broadcast::channel('webchat.tenant.{tenantId}', function ($user, $tenantId) {
    if ((string) $user->tenant_id !== (string) $tenantId) return null;
    if (!Gate::forUser($user)->allows('webchat-agent')) return null;
    return ['id' => $user->id, 'name' => $user->name];
});

// Private conversation stream — AGENT-side authorization.
// Visitor authorization for the same channel is handled separately by
// /api/webchat/broadcasting/auth (see BroadcastAuthController).
Broadcast::channel('webchat.conversation.{uuid}', function ($user, $uuid) {
    if (!Gate::forUser($user)->allows('webchat-agent')) return false;
    $conv = WebChatConversation::withoutGlobalScope('tenant')->where('uuid', $uuid)->first();
    if (!$conv) return false;
    return (string) $conv->tenant_id === (string) $user->tenant_id;
});

// Messenger — agent-only private stream. No visitor auth counterpart
// because visitors receive Meta's own delivery via the Send API, not
// through our Reverb sockets.
Broadcast::channel('messenger.conversation.{uuid}', function ($user, $uuid) {
    if (! $user?->hasAnyRole(['admin', 'supervisor', 'agent', 'super_admin'])) return false;
    $conv = \App\Models\Messenger\Conversation::withoutGlobalScope('tenant')->where('uuid', $uuid)->first();
    if (! $conv) return false;
    // Super-admin can watch across tenants; other roles must own the tenant.
    return $user->isSuperAdmin() || (string) $conv->tenant_id === (string) $user->tenant_id;
});
