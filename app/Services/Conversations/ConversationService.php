<?php

namespace App\Services\Conversations;

use App\Events\ConversationClaimed;
use App\Events\ConversationClosed;
use App\Events\ConversationReleased;
use App\Events\ConversationReopened;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function findOrCreateForIncoming(WhatsAppInstance $instance, string $customerPhone, string $customerName = ''): Conversation
    {
        $customer = Customer::withoutGlobalScope('tenant')->firstOrCreate(
            ['tenant_id' => $instance->tenant_id, 'phone_e164' => $customerPhone],
            ['display_name' => $customerName ?: null, 'first_contact_at' => now()]
        );

        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('instance_id', $instance->id)
            ->where('customer_id', $customer->id)
            ->whereIn('state', ['pool', 'claimed'])
            ->first();

        if (!$conversation) {
            $conversation = Conversation::withoutGlobalScope('tenant')->create([
                'tenant_id'       => $instance->tenant_id,
                'instance_id'     => $instance->id,
                'customer_id'     => $customer->id,
                'team_id'         => $instance->team_id,
                'state'           => 'pool',
                'last_message_at' => now(),
            ]);
            $this->logEvent($conversation, 'reopened', null, ['reason' => 'new_conversation']);
        }

        return $conversation;
    }

    public function reopenIfClosed(Conversation $conversation): bool
    {
        if (!$conversation->isClosed()) return false;

        $conversation->update([
            'state'          => 'pool',
            'owner_agent_id' => null,
            'claimed_at'     => null,
            'ai_suspended'   => false,
            'unread_count'   => 0,
        ]);

        $this->logEvent($conversation, 'reopened', null, ['reason' => 'new_inbound_message']);
        broadcast(new ConversationReopened($conversation->fresh()))->toOthers();

        return true;
    }

    public function claim(Conversation $conversation, User $agent): bool
    {
        // CRITICAL: single conditional UPDATE — no read-then-write
        $affected = DB::table('conversations')
            ->where('id', $conversation->id)
            ->where('state', 'pool')
            ->update([
                'state'          => 'claimed',
                'owner_agent_id' => $agent->id,
                'claimed_at'     => now(),
                'ai_suspended'   => true,
                'unread_count'   => 0,
                'updated_at'     => now(),
            ]);

        if ($affected === 0) return false;

        $fresh = $conversation->fresh();
        $this->logEvent($fresh, 'claimed', $agent->id);
        broadcast(new ConversationClaimed($fresh, $agent));

        return true;
    }

    public function release(Conversation $conversation, User $actor): void
    {
        $conversation->update([
            'state'          => 'pool',
            'owner_agent_id' => null,
            'claimed_at'     => null,
            'ai_suspended'   => false,
        ]);

        $this->logEvent($conversation->fresh(), 'released', $actor->id);
        broadcast(new ConversationReleased($conversation->fresh(), $actor));
    }

    public function close(Conversation $conversation, User $actor): void
    {
        $conversation->update([
            'state'      => 'closed',
            'closed_at'  => now(),
        ]);

        $this->logEvent($conversation->fresh(), 'closed', $actor->id);
        broadcast(new ConversationClosed($conversation->fresh(), $actor));
    }

    public function reassign(Conversation $conversation, User $newAgent, User $actor): void
    {
        $oldAgentId = $conversation->owner_agent_id;

        $conversation->update([
            'owner_agent_id' => $newAgent->id,
            'claimed_at'     => now(),
            'ai_suspended'   => true,
        ]);

        $this->logEvent($conversation->fresh(), 'reassigned', $actor->id, [
            'from_agent_id' => $oldAgentId,
            'to_agent_id'   => $newAgent->id,
        ]);

        broadcast(new ConversationClaimed($conversation->fresh(), $newAgent));
    }

    public function reopen(Conversation $conversation, User $actor): void
    {
        if (!$conversation->isClosed()) {
            return;
        }

        $conversation->update([
            'state'          => 'pool',
            'owner_agent_id' => null,
            'claimed_at'     => null,
            'closed_at'      => null,
            'ai_suspended'   => false,
        ]);

        $this->logEvent($conversation->fresh(), 'reopened', $actor->id, ['reason' => 'manual_reopen']);
        broadcast(new ConversationReopened($conversation->fresh()));
    }

    public function toggleAi(Conversation $conversation, User $actor, bool $suspended): void
    {
        $conversation->update([
            'ai_suspended' => $suspended,
        ]);

        $this->logEvent($conversation->fresh(), $suspended ? 'ai_suspended' : 'ai_resumed', $actor->id);
    }

    private function logEvent(Conversation $conversation, string $type, ?int $actorId = null, array $payload = []): void
    {
        ConversationEvent::create([
            'conversation_id' => $conversation->id,
            'type'            => $type,
            'actor_id'        => $actorId,
            'payload'         => $payload,
            'created_at'      => now(),
        ]);
    }
}
