<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    private function inSameTenant(User $user, Conversation $conversation): bool
    {
        return $user->tenant_id === $conversation->tenant_id;
    }

    private function canAccessTeam(User $user, Conversation $conversation): bool
    {
        if ($conversation->team_id === null) return true;
        return $user->teams->contains('id', $conversation->team_id);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return true;
        if (!$this->inSameTenant($user, $conversation)) return false;
        if ($user->isAdmin()) return true;
        if ($user->isSupervisor()) return $this->canAccessTeam($user, $conversation);
        return $conversation->owner_agent_id === $user->id || ($conversation->state === 'pool' && $this->canAccessTeam($user, $conversation));
    }

    public function claim(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return $conversation->isPool();
        if (!$this->inSameTenant($user, $conversation)) return false;
        if (!$conversation->isPool()) return false;
        if ($user->isAdmin()) return true;
        if ($user->isAgent() || $user->isSupervisor()) {
            return $this->canAccessTeam($user, $conversation);
        }

        return false;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return $conversation->isClaimed();
        if (!$this->inSameTenant($user, $conversation)) return false;
        if (!$conversation->isClaimed()) return false;
        if ($user->isAdmin()) return true;
        if ($user->isSupervisor()) return $this->canAccessTeam($user, $conversation);
        return $conversation->owner_agent_id === $user->id;
    }

    public function release(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return $conversation->isClaimed();
        if (!$this->inSameTenant($user, $conversation)) return false;
        if (!$conversation->isClaimed()) return false;
        if ($user->isAdmin()) return true;
        return $user->isSupervisor() && $this->canAccessTeam($user, $conversation);
    }

    public function close(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return !$conversation->isClosed();
        return $this->reply($user, $conversation);
    }

    public function reassign(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return $conversation->isClaimed();
        if (!$this->inSameTenant($user, $conversation)) return false;
        if (!$conversation->isClaimed()) return false;
        if ($user->isAdmin()) return true;
        return $user->isSupervisor() && $this->canAccessTeam($user, $conversation);
    }

    public function reopen(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return $conversation->isClosed();
        if (!$this->inSameTenant($user, $conversation)) return false;
        if (!$conversation->isClosed()) return false;
        if ($user->isAdmin()) return true;
        return $user->isSupervisor() && $this->canAccessTeam($user, $conversation);
    }

    public function toggleAi(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) return !$conversation->isClosed();
        if (!$this->inSameTenant($user, $conversation)) return false;
        if ($user->isAdmin()) return !$conversation->isClosed();
        if ($user->isSupervisor()) return !$conversation->isClosed() && $this->canAccessTeam($user, $conversation);
        return $conversation->isClaimed() && $conversation->owner_agent_id === $user->id;
    }
}
