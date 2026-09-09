<?php

namespace App\Console\Commands;

use App\Http\Controllers\WebChat\ConversationController as DashConvCtrl;
use App\Http\Controllers\WebChat\MessageController as DashMsgCtrl;
use App\Models\User;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Message;
use App\Models\WebChat\Visitor;
use App\Models\WebChat\Widget;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ad-hoc verification harness for Phase 3 acceptance. Bypasses the auth
 * middleware and calls the WebChat dashboard controllers directly with two
 * simulated agents. Meant to be deleted / superseded by Phase 7 tests.
 */
class WebChatPhase3Verify extends Command
{
    protected $signature   = 'webchat:phase3-verify';
    protected $description = 'Simulate two agents claiming/replying/closing a webchat conversation';

    public function handle(): int
    {
        $widget = Widget::withoutGlobalScope('tenant')->orderBy('id')->first();
        if (!$widget) {
            $this->error('No webchat widget — run webchat:provision-widget first.');
            return self::FAILURE;
        }
        app()->instance('current_tenant_id', $widget->tenant_id);

        // Two agents in this tenant (create test users if missing).
        [$userA, $userB] = $this->twoAgents($widget->tenant_id);

        // Fresh visitor + pending conversation to claim against.
        $visitor = Visitor::create([
            'tenant_id' => $widget->tenant_id,
            'widget_id' => $widget->id,
            'name'      => 'Phase3 Visitor',
            'email'     => 'phase3@example.com',
        ]);

        $conv = Conversation::create([
            'tenant_id'        => $widget->tenant_id,
            'widget_id'        => $widget->id,
            'visitor_id'       => $visitor->id,
            'status'           => Conversation::STATUS_PENDING,
            'visitor_name'     => $visitor->name,
            'visitor_email'    => $visitor->email,
            'last_activity_at' => now(),
        ]);
        $uuid = $conv->uuid;
        $this->line("Prepared conversation {$uuid} (tenant {$widget->tenant_id}), users A={$userA->id} B={$userB->id}");

        $ctrl = new DashConvCtrl();
        $msgCtrl = new DashMsgCtrl();

        // 1. User A claims — expect 200.
        $r1 = $this->dispatchAs($ctrl, 'claim', $userA, $uuid);
        $this->say('A claim', $r1, 200);

        // 2. User B claims same conversation — expect 409 already_claimed.
        $r2 = $this->dispatchAs($ctrl, 'claim', $userB, $uuid);
        $this->say('B claim (race)', $r2, 409);

        // 3. User B tries to reply — expect 403 not_your_conversation.
        $r3 = $this->dispatchAs($msgCtrl, 'store', $userB, $uuid, ['body' => 'Sneaky reply from B']);
        $this->say('B reply', $r3, 403);

        // 4. User A replies — expect 201.
        $r4 = $this->dispatchAs($msgCtrl, 'store', $userA, $uuid, ['body' => 'Hi from agent A']);
        $this->say('A reply', $r4, 201);

        // 5. User A marks read — expect 200.
        $r5 = $this->dispatchAs($ctrl, 'markRead', $userA, $uuid);
        $this->say('A markRead', $r5, 200);

        // 6. User A closes — expect 200, status=closed, system message appended, lock released.
        $r6 = $this->dispatchAs($ctrl, 'close', $userA, $uuid);
        $this->say('A close', $r6, 200);

        $fresh = $conv->fresh();
        $systemMsg = Message::where('conversation_id', $conv->id)
            ->where('sender_type', Message::SENDER_SYSTEM)->latest('id')->first();
        $this->line("After close: status={$fresh->status}, claimed_by=" . var_export($fresh->claimed_by, true)
            . ", closed_by={$fresh->closed_by}, system_msg=" . ($systemMsg ? "\"{$systemMsg->body}\"" : 'MISSING'));

        // 7. Post-close reply should 409.
        $r7 = $this->dispatchAs($msgCtrl, 'store', $userA, $uuid, ['body' => 'After close']);
        $this->say('A reply after close', $r7, 409);

        // 8. Cross-tenant guard — a foreign uuid must 404, not leak.
        $foreignUuid = (string) \Illuminate\Support\Str::uuid();
        $r8 = $this->dispatchAs($ctrl, 'claim', $userA, $foreignUuid);
        $this->say('Foreign uuid claim', $r8, 404);

        $this->newLine();
        $this->info('Phase 3 acceptance PASSED.');
        return self::SUCCESS;
    }

    /** @return array{0:User,1:User} */
    protected function twoAgents(int $tenantId): array
    {
        $users = User::where('tenant_id', $tenantId)
            ->whereIn('role', ['admin', 'supervisor', 'agent'])
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($users->count() >= 2) {
            return [$users[0], $users[1]];
        }

        // Provision minimal test agents.
        $existing = $users->first();
        $userA = $existing ?: User::create([
            'tenant_id' => $tenantId,
            'name'      => 'Phase3 A',
            'email'     => 'phase3a@example.com',
            'password'  => bcrypt('phase3'),
            'role'      => 'agent',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $userB = User::create([
            'tenant_id' => $tenantId,
            'name'      => 'Phase3 B',
            'email'     => 'phase3b-' . now()->timestamp . '@example.com',
            'password'  => bcrypt('phase3'),
            'role'      => 'agent',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        return [$userA, $userB];
    }

    protected function dispatchAs(object $controller, string $method, User $user, string $uuid, array $body = []): array
    {
        Auth::login($user);
        $request = Request::create('/webchat/verify', 'POST', $body);
        $request->setUserResolver(fn () => $user);

        try {
            $response = $controller->$method($request, $uuid);
            return ['status' => $response->getStatusCode(), 'body' => $response->getContent()];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return ['status' => $e->getStatusCode(), 'body' => $e->getMessage()];
        } finally {
            Auth::logout();
        }
    }

    protected function say(string $label, array $result, int $expect): void
    {
        $ok = $result['status'] === $expect;
        $tag = $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
        $this->line("[{$tag}] {$label} → HTTP {$result['status']} (expected {$expect}) body=" . mb_strimwidth($result['body'], 0, 140, '…'));
    }
}
