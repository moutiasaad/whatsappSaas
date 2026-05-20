<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant   = Tenant::where('slug', 'demo')->firstOrFail();
        $instance = WhatsAppInstance::where('tenant_id', $tenant->id)->firstOrFail();
        $team     = Team::where('tenant_id', $tenant->id)->firstOrFail();
        $agent1   = User::where('email', 'agent1@demo.com')->firstOrFail();
        $agent2   = User::where('email', 'agent2@demo.com')->firstOrFail();

        // Wipe existing conversations/customers for this tenant (clean re-seed)
        Message::where('tenant_id', $tenant->id)->delete();
        Conversation::where('tenant_id', $tenant->id)->delete();
        Customer::where('tenant_id', $tenant->id)->delete();

        // ─── POOL conversations (unclaimed) ───────────────────────────────────
        $this->make([
            'customer'  => ['name' => 'Sara Malik',    'phone' => '+212600111001'],
            'state'     => 'pool',
            'unread'    => 3,
            'last_at'   => now()->subMinutes(4),
            'messages'  => [
                ['in',  'Hi, I ordered something 3 days ago and haven\'t received any tracking info.', -25],
                ['in',  'My order number is #ORD-4821.', -20],
                ['in',  'Can someone help me please?', -4],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Karim Bensaid', 'phone' => '+212600111002'],
            'state'     => 'pool',
            'unread'    => 2,
            'last_at'   => now()->subMinutes(12),
            'messages'  => [
                ['in',  'Hello! I\'d like to upgrade my subscription plan.', -30],
                ['out', 'Hi Karim! Of course, I can help with that. Which plan are you looking at?', -28, $agent2],
                ['in',  'The Growth plan. How do I do it?', -12],
                ['in',  'Also can I keep my current data?', -11],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Amina Toure',   'phone' => '+221700222003'],
            'state'     => 'pool',
            'unread'    => 1,
            'last_at'   => now()->subMinutes(31),
            'messages'  => [
                ['out', 'Hi Amina! Your invoice #INV-2024-089 is ready. Please find it attached.', -90],
                ['in',  'Thank you! But I think there\'s a mistake on the amount.', -31],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Mohamed El Fassi', 'phone' => '+212600111004'],
            'state'     => 'pool',
            'unread'    => 5,
            'last_at'   => now()->subMinutes(2),
            'messages'  => [
                ['in',  'URGENT!!! My account is locked and I have a presentation in 1 hour.', -8],
                ['in',  'I can\'t log in at all.', -7],
                ['in',  'Hello?', -5],
                ['in',  'Anyone there?', -4],
                ['in',  'PLEASE HELP', -2],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Layla Hassan',  'phone' => '+96650033005'],
            'state'     => 'pool',
            'unread'    => 1,
            'last_at'   => now()->subHours(1),
            'messages'  => [
                ['in',  'Do you offer a student discount?', -60],
            ],
        ], $tenant, $instance, $team);

        // ─── CLAIMED conversations (mine — owned by agent1) ──────────────────
        $this->make([
            'customer'  => ['name' => 'Youssef Idrissi', 'phone' => '+212600111006'],
            'state'     => 'claimed',
            'agent'     => $agent1,
            'unread'    => 0,
            'last_at'   => now()->subMinutes(8),
            'messages'  => [
                ['in',  'Hi, my payment failed but the money was deducted from my account.', -120],
                ['out', 'Hello Youssef! I\'m sorry to hear that. I\'ll look into this right away.', -115, $agent1],
                ['out', 'Could you share the last 4 digits of the card used?', -114, $agent1],
                ['in',  'It ends in 4821.', -110],
                ['out', 'Got it. I can see the failed transaction. Let me raise a refund request for you now.', -10, $agent1],
                ['in',  'Thank you! How long will the refund take?', -8],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Fatima Zohra',  'phone' => '+212600111007'],
            'state'     => 'claimed',
            'agent'     => $agent1,
            'ai_suspended' => true,
            'unread'    => 0,
            'last_at'   => now()->subMinutes(22),
            'messages'  => [
                ['in',  'I need to change the email on my account.', -60],
                ['out', 'Hi Fatima! Sure, I can help you update your email. What\'s the new one?', -55, $agent1],
                ['in',  'New email is fatima.new@gmail.com', -50],
                ['out', 'Perfect. I\'ve updated your email address. You\'ll receive a confirmation shortly.', -22, $agent1],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Carlos Rivera',  'phone' => '+5215500333008'],
            'state'     => 'claimed',
            'agent'     => $agent2,
            'unread'    => 1,
            'last_at'   => now()->subMinutes(3),
            'messages'  => [
                ['in',  'Hola! Quiero cancelar mi suscripción.', -45],
                ['out', 'Hello Carlos! I can help with that. May I ask the reason for cancellation?', -40, $agent2],
                ['in',  'It\'s too expensive for me right now.', -35],
                ['out', 'I understand. Would you be open to a 30% discount for the next 3 months?', -30, $agent2],
                ['in',  'Hmm, that sounds interesting actually. Tell me more.', -3],
            ],
        ], $tenant, $instance, $team);

        // ─── CLOSED conversations ─────────────────────────────────────────────
        $this->make([
            'customer'  => ['name' => 'Nadia Benjelloun', 'phone' => '+212600111009'],
            'state'     => 'closed',
            'agent'     => $agent1,
            'unread'    => 0,
            'last_at'   => now()->subHours(3),
            'closed_at' => now()->subHours(2),
            'messages'  => [
                ['in',  'Hi, how do I export my data?', -210],
                ['out', 'Hello Nadia! You can export your data from Settings → Data Export.', -205, $agent1],
                ['in',  'Found it! Thank you so much.', -200],
                ['out', 'Great! Let me know if you need anything else.', -195, $agent1],
                ['in',  '👍', -190],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Ahmed Qassem',  'phone' => '+97150044010'],
            'state'     => 'closed',
            'agent'     => $agent2,
            'unread'    => 0,
            'last_at'   => now()->subHours(5),
            'closed_at' => now()->subHours(4)->subMinutes(30),
            'messages'  => [
                ['in',  'What are your business hours?', -360],
                ['out', 'We\'re available Mon–Fri, 9 AM to 6 PM (GMT+1). How can I help you today?', -358, $agent2],
                ['in',  'That\'s all I needed, thanks!', -355],
            ],
        ], $tenant, $instance, $team);

        $this->make([
            'customer'  => ['name' => 'Isabelle Dupont', 'phone' => '+33600555011'],
            'state'     => 'closed',
            'agent'     => $agent1,
            'unread'    => 0,
            'last_at'   => now()->subDays(1)->subHours(2),
            'closed_at' => now()->subDays(1)->subHours(1),
            'messages'  => [
                ['in',  'Bonjour, j\'ai un problème avec mon compte.', -1560],
                ['out', 'Bonjour Isabelle! Je suis là pour vous aider. Quel est le problème?', -1555, $agent1],
                ['in',  'Je n\'arrive pas à me connecter depuis ce matin.', -1550],
                ['out', 'Je vois votre compte. Il y avait un blocage temporaire. C\'est réglé maintenant!', -1545, $agent1],
                ['in',  'Super! Ça marche maintenant merci!', -1540],
                ['out', 'Parfait! N\'hésitez pas à nous recontacter.', -1535, $agent1],
            ],
        ], $tenant, $instance, $team);

        $this->command->info('Seeded ' . Conversation::where('tenant_id', $tenant->id)->count() . ' conversations for tenant "' . $tenant->name . '"');
        $this->command->table(
            ['State', 'Customer', 'Messages', 'Agent'],
            Conversation::where('tenant_id', $tenant->id)
                ->with(['customer', 'ownerAgent'])
                ->get()
                ->map(fn($c) => [
                    strtoupper($c->state),
                    $c->customer?->display_name ?? '?',
                    $c->messages()->count(),
                    $c->ownerAgent?->name ?? '—',
                ])
                ->toArray()
        );
    }

    private function make(array $def, $tenant, $instance, $team): Conversation
    {
        $customer = Customer::create([
            'tenant_id'       => $tenant->id,
            'phone_e164'      => $def['customer']['phone'],
            'display_name'    => $def['customer']['name'],
            'first_contact_at'=> now()->subDays(rand(1, 30)),
        ]);

        $conv = Conversation::create([
            'tenant_id'      => $tenant->id,
            'instance_id'    => $instance->id,
            'customer_id'    => $customer->id,
            'team_id'        => $team->id,
            'state'          => $def['state'],
            'owner_agent_id' => isset($def['agent']) ? $def['agent']->id : null,
            'claimed_at'     => isset($def['agent']) ? now()->subHours(rand(1, 5)) : null,
            'closed_at'      => $def['closed_at'] ?? null,
            'ai_suspended'   => $def['ai_suspended'] ?? isset($def['agent']),
            'unread_count'   => $def['unread'],
            'last_message_at'=> $def['last_at'],
        ]);

        foreach ($def['messages'] as $msg) {
            [$dir, $body, $minutesAgo] = $msg;
            $author = $msg[3] ?? null;

            Message::create([
                'conversation_id'     => $conv->id,
                'tenant_id'           => $tenant->id,
                'direction'           => $dir,
                'author_type'         => $dir === 'out' ? 'agent' : 'customer',
                'author_id'           => $dir === 'out' && $author ? $author->id : null,
                'external_message_id' => 'fake_' . Str::random(12),
                'type'                => 'text',
                'body'                => $body,
                'status'              => $dir === 'out' ? 'delivered' : 'read',
                'sent_at'             => now()->addMinutes($minutesAgo),
            ]);
        }

        // Update last_message_preview on the conversation
        $lastMsg = $conv->messages()->latest('sent_at')->first();
        if ($lastMsg) {
            $conv->update([
                'last_message_at'      => $lastMsg->sent_at,
                'last_message_preview' => Str::limit($lastMsg->body, 80),
            ]);
        }

        return $conv;
    }
}
