<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebChat\Conversation;
use App\Models\WebChat\Message;
use App\Models\WebChat\Widget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lifecycle tests for the web-chat module. Built manually rather than via
 * `RefreshDatabase` because one of the pre-existing WhatsApp migrations uses
 * raw `ALTER TABLE ... MODIFY` (MySQL-only) — the WhatsApp domain is off-limits
 * to this module, so we spin up only the tables these tests need on an
 * in-memory SQLite database.
 */
class WebChatLifecycleTest extends TestCase
{
    protected Tenant $tenant;
    protected Widget $widget;
    protected string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();

        $this->tenant = Tenant::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'subscription_status' => 'active',
            'is_active' => true,
        ]);
        $this->widget = Widget::withoutGlobalScope('tenant')->create([
            'tenant_id'       => $this->tenant->id,
            'name'            => 'Live Chat',
            'enabled'         => true,
            'welcome_message' => 'Hi',
            'suggestions'     => ['Pricing'],
            'theme_color'     => '#2563eb',
            'position'        => 'right',
            'allowed_domains' => [],
        ]);
        $this->key = $this->widget->public_key;

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_public_visitor_bootstraps_starts_conversation_and_sends_message(): void
    {
        $session = $this->postJson("/api/webchat/{$this->key}/session", ['name' => 'Alice']);
        $session->assertOk()->assertJsonStructure(['visitor_token', 'widget', 'reverb', 'runtime']);
        $visitorToken = $session->json('visitor_token');
        $this->assertNotEmpty($visitorToken);

        $conv = $this->withHeader('Authorization', "Bearer $visitorToken")
            ->postJson("/api/webchat/{$this->key}/conversations", []);
        $conv->assertStatus(201)->assertJsonPath('status', Conversation::STATUS_BOT);
        $uuid = $conv->json('uuid');

        // First visitor message auto-promotes bot → pending
        $msg = $this->withHeader('Authorization', "Bearer $visitorToken")
            ->postJson("/api/webchat/{$this->key}/conversations/{$uuid}/messages", ['body' => 'Hi there']);
        $msg->assertStatus(201)
            ->assertJsonPath('message.sender_type', Message::SENDER_VISITOR)
            ->assertJsonPath('conversation.status', Conversation::STATUS_PENDING);

        // Polling endpoint returns the message we just sent
        $poll = $this->withHeader('Authorization', "Bearer $visitorToken")
            ->getJson("/api/webchat/{$this->key}/conversations/{$uuid}/messages?after=0");
        $poll->assertOk()->assertJsonCount(1, 'messages');
    }

    public function test_double_claim_returns_409(): void
    {
        [$uuid, ] = $this->seedPendingConversation();

        $agent1 = $this->createAdmin('agent-one@example.com');
        $agent2 = $this->createAdmin('agent-two@example.com');

        $first = $this->actingAs($agent1)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/claim");
        $first->assertOk()->assertJsonPath('conversation.claimed_by', $agent1->id);

        $second = $this->actingAs($agent2)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/claim");
        $second->assertStatus(409)->assertJsonPath('error', 'already_claimed');
    }

    public function test_agent_can_reply_but_only_the_claimer(): void
    {
        [$uuid, ] = $this->seedPendingConversation();
        $claimer  = $this->createAdmin('claimer@example.com');
        $stranger = $this->createAdmin('stranger@example.com');

        $this->actingAs($claimer)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/claim")
            ->assertOk();

        $this->actingAs($stranger)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/messages", ['body' => 'not mine'])
            ->assertStatus(403);

        $reply = $this->actingAs($claimer)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/messages", ['body' => 'hi visitor']);
        $reply->assertStatus(201)
            ->assertJsonPath('message.sender_type', Message::SENDER_AGENT)
            ->assertJsonPath('message.sender_id', $claimer->id);
    }

    public function test_close_locks_conversation_and_prevents_replies(): void
    {
        [$uuid, ] = $this->seedPendingConversation();
        $claimer = $this->createAdmin('closer@example.com');

        $this->actingAs($claimer)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/claim")
            ->assertOk();

        $this->actingAs($claimer)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/close")
            ->assertOk()
            ->assertJsonPath('conversation.status', Conversation::STATUS_CLOSED);

        // Closed check runs before the ownership check — 409 wins over 403.
        $this->actingAs($claimer)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/messages", ['body' => 'ping'])
            ->assertStatus(409);
    }

    public function test_cross_tenant_conversation_lookup_returns_404(): void
    {
        [$uuid, ] = $this->seedPendingConversation();

        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other',
            'subscription_status' => 'active', 'is_active' => true,
        ]);
        $otherAdmin = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Admin',
            'email' => 'other@example.com',
            'password' => 'x',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($otherAdmin)
            ->postJson("/tenant-admin/webchat/conversations/{$uuid}/claim")
            ->assertStatus(404);
    }

    public function test_release_stale_command_releases_idle_claims(): void
    {
        [$uuid, $conv] = $this->seedPendingConversation();
        $agent = $this->createAdmin('sleepy@example.com');

        $conv->status           = Conversation::STATUS_ASSIGNED;
        $conv->claimed_by       = $agent->id;
        $conv->claimed_at       = now()->subHour();
        $conv->last_activity_at = now()->subHour();
        $conv->save();

        [, $freshConv] = $this->seedPendingConversation();
        $freshConv->status           = Conversation::STATUS_ASSIGNED;
        $freshConv->claimed_by       = $agent->id;
        $freshConv->claimed_at       = now()->subMinute();
        $freshConv->last_activity_at = now()->subMinute();
        $freshConv->save();

        config(['webchat.release_stale_after_minutes' => 15]);
        Artisan::call('webchat:release-stale');

        $reloadedStale = Conversation::withoutGlobalScope('tenant')->where('uuid', $uuid)->first();
        $this->assertSame(Conversation::STATUS_PENDING, $reloadedStale->status);
        $this->assertNull($reloadedStale->claimed_by);

        $reloadedFresh = Conversation::withoutGlobalScope('tenant')->where('uuid', $freshConv->uuid)->first();
        $this->assertSame(Conversation::STATUS_ASSIGNED, $reloadedFresh->status);
        $this->assertSame($agent->id, $reloadedFresh->claimed_by);
    }

    protected function createAdmin(string $email): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Admin',
            'email'     => $email,
            'password'  => 'x',
            'role'      => 'admin',
            'is_active' => true,
        ]);
    }

    /** @return array{0: string, 1: Conversation} */
    protected function seedPendingConversation(): array
    {
        $session = $this->postJson("/api/webchat/{$this->key}/session");
        $token   = $session->json('visitor_token');
        $create  = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/webchat/{$this->key}/conversations", []);
        $uuid = $create->json('uuid');
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/webchat/{$this->key}/conversations/{$uuid}/messages", ['body' => 'hi']);
        $conv = Conversation::withoutGlobalScope('tenant')->where('uuid', $uuid)->firstOrFail();
        return [$uuid, $conv];
    }

    /**
     * Manually create the minimum schema — tenants, users, and the four
     * webchat_* tables. Avoids the broken WhatsApp migrations on SQLite.
     */
    protected function buildSchema(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('subscription_status')->default('trial');
            $t->timestamp('subscription_starts_at')->nullable();
            $t->timestamp('subscription_ends_at')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->string('stripe_id')->nullable();
            $t->json('settings')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('remember_token', 100)->nullable();
            $t->string('role')->default('agent');
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_login_at')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('api_key')->nullable()->unique();
            $t->json('sidebar_permissions')->nullable();
            $t->timestamps();
        });

        Schema::create('webchat_widgets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->unique();
            $t->string('public_key', 64)->unique();
            $t->string('name', 120);
            $t->boolean('enabled')->default(true);
            $t->text('welcome_message');
            $t->json('suggestions')->nullable();
            $t->boolean('pre_chat_ask_email')->default(false);
            $t->text('offline_message')->nullable();
            $t->string('theme_color', 16)->default('#2563eb');
            $t->string('position', 8)->default('right');
            $t->string('launcher_text', 120)->nullable();
            $t->json('allowed_domains')->nullable();
            $t->timestamps();
        });

        Schema::create('webchat_visitors', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('widget_id')->index();
            $t->string('token', 64)->unique();
            $t->string('name', 120)->nullable();
            $t->string('email', 190)->nullable();
            $t->json('attributes')->nullable();
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        Schema::create('webchat_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('uuid', 64)->unique();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('widget_id')->index();
            $t->unsignedBigInteger('visitor_id')->index();
            $t->string('status', 16)->default('bot');
            $t->unsignedBigInteger('claimed_by')->nullable()->index();
            $t->timestamp('claimed_at')->nullable();
            $t->unsignedBigInteger('closed_by')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->string('visitor_name', 120)->nullable();
            $t->string('visitor_email', 190)->nullable();
            $t->text('page_url')->nullable();
            $t->text('referrer')->nullable();
            $t->text('user_agent')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });

        Schema::create('webchat_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('conversation_id')->index();
            $t->string('sender_type', 16);
            $t->unsignedBigInteger('sender_id')->nullable()->index();
            $t->text('body');
            $t->json('meta')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
    }
}
