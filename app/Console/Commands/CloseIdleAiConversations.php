<?php

namespace App\Console\Commands;

use App\Events\WebChat\WebChatConversationClosed;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\PlatformSetting;
use App\Models\WebChat\Conversation as WebChatConversation;
use App\Models\WebChat\Message as WebChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes conversations the AI is holding that the customer has gone quiet on.
 *
 * Scope is deliberately narrow. A thread is only eligible when the bot is the
 * one talking and no human is involved — an agent's thread going quiet is that
 * agent's business, and a thread waiting in the human queue is unanswered work,
 * not an abandoned chat. Escalated threads are skipped for the same reason:
 * the customer asked for a person and is still waiting for one.
 *
 * Idleness is measured from the customer's own last message, not from
 * last_message_at / last_activity_at, which both also move when the AI replies
 * or an agent claims — measuring those would restart the clock on the bot's own
 * chatter and the thread would never close.
 *
 * The window is set by the super admin (platform setting
 * `ai_idle_close_minutes`) and each tenant may override it from their own
 * settings page. 0 turns the sweep off — platform-wide when the platform
 * setting is 0, or for one tenant when only that tenant's override is 0.
 *
 * Because windows now differ per tenant, the SQL cutoff is the WIDEST window in
 * play and each row is then re-checked against its own tenant's window. Using
 * the platform value alone would silently ignore any tenant who chose longer.
 */
class CloseIdleAiConversations extends Command
{
    protected $signature   = 'conversations:close-idle-ai {--dry-run : List what would close without closing it}';
    protected $description = 'Close AI-handled WhatsApp and Live Chat conversations idle beyond the configured window';

    public const SETTING_KEY = 'ai_idle_close_minutes';
    public const DEFAULT_MINUTES = 15;

    /** tenant_id => effective idle window in minutes (0 = off for that tenant). */
    private array $windows = [];

    public function handle(): int
    {
        $this->windows = \App\Models\Tenant::all()
            ->mapWithKeys(fn ($t) => [$t->id => $t->autoCloseMinutes()])
            ->all();

        $widest = $this->windows ? max($this->windows) : 0;

        if ($widest <= 0) {
            $this->info('Every idle-close window is 0 — disabled, nothing to do.');
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($widest);
        $dry    = (bool) $this->option('dry-run');

        $wa = $this->closeWhatsApp($cutoff, $widest, $dry);
        $wc = $this->closeWebChat($cutoff, $widest, $dry);

        $verb = $dry ? 'Would close' : 'Closed';
        $this->info("{$verb} {$wa} WhatsApp and {$wc} Live Chat conversation(s) past their tenant's idle window (widest {$widest}m).");

        return self::SUCCESS;
    }

    /**
     * Is this row actually past ITS tenant's window?
     *
     * The SQL pass used the widest window, so a tenant on a shorter one is
     * correctly included and a tenant with the sweep switched off has to be
     * dropped here.
     */
    private function isIdleForTenant(?int $tenantId, $lastInboundAt): bool
    {
        $minutes = $this->windows[$tenantId] ?? 0;

        if ($minutes <= 0 || !$lastInboundAt) {
            return false;
        }

        return \Carbon\Carbon::parse($lastInboundAt)->lte(now()->subMinutes($minutes));
    }

    private function windowFor(?int $tenantId): int
    {
        return $this->windows[$tenantId] ?? 0;
    }

    private function closeWhatsApp(\Carbon\Carbon $cutoff, int $minutes, bool $dry): int
    {
        $rows = Conversation::withoutGlobalScope('tenant')
            ->where('state', 'pool')
            ->where('ai_suspended', false)
            ->whereNull('escalated_at')
            // The AI has actually spoken here, so this is a bot conversation
            // rather than a thread nobody has picked up yet.
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.author_type', 'ai'))
            // The customer has said something at some point...
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.direction', 'in'))
            // ...but nothing since the cutoff.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.direction', 'in')
                ->where('messages.created_at', '>=', $cutoff))
            // Carried so each row can be re-checked against its own tenant's
            // window, which may be longer than the one the cutoff above used.
            ->addSelect(['last_inbound_at' => \App\Models\Message::select('created_at')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.direction', 'in')
                ->latest('created_at')
                ->limit(1)])
            ->orderBy('id')
            ->get()
            ->filter(fn ($c) => $this->isIdleForTenant($c->tenant_id, $c->last_inbound_at))
            ->values();

        foreach ($rows as $c) {
            $minutes = $this->windowFor($c->tenant_id);

            if ($dry) {
                $this->line("  [dry] whatsapp #{$c->id} (tenant {$c->tenant_id}, {$minutes}m)");
                continue;
            }

            $c->update(['state' => 'closed', 'closed_at' => now()]);

            // actor_id stays null: this is the system closing the thread, not a
            // person, and the timeline renders that as an automatic action.
            rescue(fn () => ConversationEvent::create([
                'conversation_id' => $c->id,
                'type'            => 'closed',
                'actor_id'        => null,
                'payload'         => ['reason' => 'ai_idle_timeout', 'idle_minutes' => $minutes],
                'created_at'      => now(),
            ]));

            Log::channel('whatsapp')->info('AI idle timeout — conversation closed', [
                'conversation_id' => $c->id,
                'tenant_id'       => $c->tenant_id,
                'idle_minutes'    => $minutes,
            ]);
        }

        return $rows->count();
    }

    private function closeWebChat(\Carbon\Carbon $cutoff, int $minutes, bool $dry): int
    {
        $rows = WebChatConversation::withoutGlobalScope('tenant')
            // 'bot' is precisely "the AI is handling this and no human is on it".
            ->where('status', WebChatConversation::STATUS_BOT)
            ->whereNull('escalated_at')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('webchat_messages')
                ->whereColumn('webchat_messages.conversation_id', 'webchat_conversations.id')
                ->where('webchat_messages.sender_type', WebChatMessage::SENDER_VISITOR))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('webchat_messages')
                ->whereColumn('webchat_messages.conversation_id', 'webchat_conversations.id')
                ->where('webchat_messages.sender_type', WebChatMessage::SENDER_VISITOR)
                ->where('webchat_messages.created_at', '>=', $cutoff))
            // Same per-tenant re-check as the WhatsApp sweep above.
            ->addSelect(['last_inbound_at' => WebChatMessage::select('created_at')
                ->whereColumn('webchat_messages.conversation_id', 'webchat_conversations.id')
                ->where('webchat_messages.sender_type', WebChatMessage::SENDER_VISITOR)
                ->latest('created_at')
                ->limit(1)])
            ->orderBy('id')
            ->get()
            ->filter(fn ($c) => $this->isIdleForTenant($c->tenant_id, $c->last_inbound_at))
            ->values();

        foreach ($rows as $c) {
            $minutes = $this->windowFor($c->tenant_id);

            if ($dry) {
                $this->line("  [dry] webchat {$c->uuid} (tenant {$c->tenant_id}, {$minutes}m)");
                continue;
            }

            $c->status           = WebChatConversation::STATUS_CLOSED;
            $c->closed_at        = now();
            $c->last_activity_at = now();
            $c->save();

            rescue(fn () => event(new WebChatConversationClosed($c->fresh())));

            Log::channel('webchat')->info('AI idle timeout — conversation closed', [
                'conversation_id' => $c->id,
                'tenant_id'       => $c->tenant_id,
                'idle_minutes'    => $minutes,
            ]);
        }

        return $rows->count();
    }
}
