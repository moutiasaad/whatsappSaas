<?php

namespace App\Console\Commands;

use App\Events\WebChat\WebChatConversationReleased;
use App\Models\WebChat\Conversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WebChatReleaseStale extends Command
{
    protected $signature   = 'webchat:release-stale';
    protected $description = 'Release web-chat conversations left claimed but idle beyond the configured threshold';

    public function handle(): int
    {
        $minutes = (int) config('webchat.release_stale_after_minutes', 15);
        if ($minutes <= 0) {
            $this->info('Release threshold is 0 — nothing to do.');
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($minutes);

        // Walk stale rows one at a time so the released event fires per row
        // (dashboards need one message per released conversation, not one
        // aggregate). Bypass the tenant global scope on purpose — this is a
        // system-level sweep across every tenant.
        $rows = Conversation::withoutGlobalScope('tenant')
            ->where('status', Conversation::STATUS_ASSIGNED)
            ->whereNotNull('claimed_by')
            ->where('last_activity_at', '<', $cutoff)
            ->orderBy('id')
            ->get();

        $released = 0;

        foreach ($rows as $conversation) {
            $conversation->claimed_by       = null;
            $conversation->claimed_at       = null;
            $conversation->status           = Conversation::STATUS_PENDING;
            $conversation->last_activity_at = now();
            $conversation->save();

            event(new WebChatConversationReleased($conversation));

            $released++;

            Log::info('webchat: stale claim released', [
                'conversation_uuid' => $conversation->uuid,
                'tenant_id'         => $conversation->tenant_id,
                'idle_minutes'      => $minutes,
            ]);
        }

        $this->info("Released {$released} stale web-chat conversation(s) (idle > {$minutes}m).");

        return self::SUCCESS;
    }
}
