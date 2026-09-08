<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\WhatsAppInstance;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantQuota
{
    private const INSTANCE_LIMIT     = 1;
    private const DEFAULT_USER_LIMIT = 5;

    public function instanceLimit(User $actor): ?int
    {
        if ($actor->isSuperAdmin()) return null; // null = unlimited

        // One WhatsApp instance per tenant, whatever the plan says. The plan column
        // is no longer configurable or shown, so it is deliberately not read here.
        return self::INSTANCE_LIMIT;
    }

    public function userLimit(User $actor): ?int
    {
        if ($actor->isSuperAdmin()) return null;
        return (int) ($actor->tenant?->plan?->max_users ?? self::DEFAULT_USER_LIMIT);
    }

    public function currentInstanceCount(User $actor): int
    {
        return WhatsAppInstance::where('tenant_id', $actor->tenant_id)->count();
    }

    public function currentUserCount(User $actor): int
    {
        return User::where('tenant_id', $actor->tenant_id)->count();
    }

    public function canCreateInstance(User $actor): bool
    {
        $max = $this->instanceLimit($actor);
        return $max === null || $this->currentInstanceCount($actor) < $max;
    }

    public function canCreateUser(User $actor): bool
    {
        $max = $this->userLimit($actor);
        return $max === null || $this->currentUserCount($actor) < $max;
    }

    public function assertCanCreateInstance(User $actor): void
    {
        if ($this->canCreateInstance($actor)) return;
        throw new HttpException(422, __('ui.controller_messages.instance_limit_reached'));
    }

    public function assertCanCreateUser(User $actor): void
    {
        if ($this->canCreateUser($actor)) return;
        $max = $this->userLimit($actor);
        throw new HttpException(422, __('ui.controller_messages.user_limit_reached', ['count' => $max]));
    }
}
