<?php

namespace App\Services\WhatsApp;

use App\Jobs\RestoreGatewayWebhook;
use App\Jobs\MuteGatewayConnectionEvents;
use App\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PROC-019 — absorbs the gateway's unbounded reconnect loop.
 *
 * The gateway retries a closed Baileys socket immediately (no backoff, no
 * attempt cap) and treats only status 401 as terminal. A stale session is
 * refused with 405, so the socket flips close -> connecting -> close forever,
 * firing a connection.update webhook every lap. Each lap used to cost a
 * webhook_events insert, a queued job and a Reverb broadcast.
 *
 * The guard trips on that pattern and, from then on, drops the instance's
 * connection noise at the webhook edge for the price of one already-loaded
 * model check. Where the reason code says the session is permanently refused it
 * also asks the gateway to stop posting, which ends the loop's only outbound
 * effect.
 *
 * Nothing here deletes an instance or disturbs a pairing. Every action it takes
 * is reversible, and reversed automatically on re-pair or recovery.
 */
class ConnectionFlapGuard
{
    /**
     * Events that carry connection state rather than user traffic.
     *
     * qrcode.updated is deliberately absent: it is how a fresh QR reaches the
     * dashboard, so absorbing it would leave a guarded instance permanently
     * unpairable — the guard would block the one action that fixes it.
     */
    private const CONNECTION_EVENTS = [
        'connection.update', 'connectionupdated',
        'status.instance', 'statusinstance',
    ];

    /** States that mean the socket went down. */
    private const CLOSE_STATES = ['close', 'closed', 'disconnected', 'refused'];

    /**
     * Should this webhook be dropped without persisting or broadcasting?
     *
     * Returning true means the caller acknowledges the event and does nothing
     * else with it.
     */
    public function absorbs(WhatsAppInstance $instance, string $eventType, array $payload): bool
    {
        if (!config('whatsapp.flap_guard.enabled', true)) {
            return false;
        }

        if (!in_array($eventType, self::CONNECTION_EVENTS, true)) {
            return false;
        }

        // A live instance is never guarded. Its drops are real events that the
        // dashboard needs, and it must never become a suspension candidate.
        if ($instance->status === 'connected') {
            return false;
        }

        // Already tripped: swallow the whole connection stream. This is the hot
        // path during an incident and costs nothing beyond the instance lookup
        // the controller has already done.
        if ($this->isTripped($instance)) {
            return true;
        }

        // A deliberate pairing attempt is in flight. Pairing legitimately emits
        // close frames while the phone has yet to scan, so tripping here would
        // fight the very action that resolves the fault.
        if (Cache::get($this->pairingKey($instance))) {
            return false;
        }

        $state  = $this->extractState($payload);
        $reason = $this->extractReason($payload);

        // Only a close advances the counters. 'connecting' repeats legitimately
        // while a QR is on screen, so counting it would punish normal pairing.
        if (!in_array($state, self::CLOSE_STATES, true)) {
            return false;
        }

        $permanent = $reason !== null
            && in_array($reason, (array) config('whatsapp.flap_guard.permanent_reasons', []), true);

        $counters = $this->bump($instance, $permanent);

        $tripped = $permanent
            ? $counters['permanent'] >= (int) config('whatsapp.flap_guard.permanent_threshold', 2)
            : $counters['closes']    >= (int) config('whatsapp.flap_guard.close_threshold', 30);

        if (!$tripped) {
            return false;
        }

        $this->trip($instance, $reason, $permanent, $counters);

        return true;
    }

    public function isTripped(WhatsAppInstance $instance): bool
    {
        return (bool) data_get($instance->settings, 'connection_guard.tripped_at');
    }

    /**
     * Open a window in which the guard stands down for this instance, so a
     * human-initiated pairing can run to completion without being cut short.
     */
    public function beginPairing(WhatsAppInstance $instance): void
    {
        Cache::put(
            $this->pairingKey($instance),
            true,
            (int) config('whatsapp.flap_guard.pairing_grace_seconds', 300),
        );
    }

    private function pairingKey(WhatsAppInstance $instance): string
    {
        return "wa-flap-pairing:{$instance->id}";
    }

    /**
     * The gateway says this instance is healthy again, so undo everything the
     * guard did to it. Lets a socket that recovers on its own — or one an
     * operator fixes at the gateway — come back without manual intervention.
     */
    public function recover(WhatsAppInstance $instance): void
    {
        if (!$this->isTripped($instance)) {
            return;
        }

        $muted = (bool) data_get($instance->settings, 'connection_guard.connection_events_muted');

        $this->reset($instance);

        if ($muted) {
            RestoreGatewayWebhook::dispatch($instance->id)->onQueue('whatsapp');
        }

        Log::channel('whatsapp')->info('Flap guard recovered', [
            'instance_id'      => $instance->id,
            'webhook_restored' => $muted,
        ]);
    }

    /**
     * Clear the guard. Called when a human deliberately re-pairs the instance —
     * that is the only action that can actually resolve a dead session.
     */
    public function reset(WhatsAppInstance $instance): void
    {
        Cache::forget($this->cacheKey($instance));

        if (!$this->isTripped($instance)) {
            return;
        }

        $settings = $instance->settings ?? [];
        unset($settings['connection_guard']);

        $instance->update(['settings' => $settings]);

        Log::channel('whatsapp')->info('Flap guard reset', ['instance_id' => $instance->id]);
    }

    /**
     * Record the trip, then decide — separately and conservatively — whether the
     * looping socket may also be dropped at the gateway.
     */
    private function trip(WhatsAppInstance $instance, ?int $reason, bool $permanent, array $counters): void
    {
        $refusal = $permanent
            ? $this->muteRefusal($instance)
            : 'transient flap — absorbed only';

        $settings = $instance->settings ?? [];
        $settings['connection_guard'] = [
            'tripped_at'  => now()->toIso8601String(),
            'reason_code' => $reason,
            'permanent'   => $permanent,
            'closes'      => $counters['closes'],
            'note'        => $permanent
                ? 'Gateway session rejected by WhatsApp — the phone must scan a new QR code.'
                : 'Gateway socket flapping — reconnect loop suppressed.',
        ];

        $updates = ['settings' => $settings];

        // 'error' surfaces in the dashboard as needing attention. Protected
        // statuses keep whatever they have.
        if (!in_array($instance->status, $this->protectedStatuses(), true)) {
            $updates['status'] = 'error';
        }

        $instance->update($updates);
        Cache::forget($this->cacheKey($instance));

        Log::channel('whatsapp')->warning('Flap guard tripped', [
            'instance_id' => $instance->id,
            'instance'    => $instance->name,
            'reason_code' => $reason,
            'permanent'   => $permanent,
            'closes'      => $counters['closes'],
            'mute'        => $refusal ?? 'dispatched',
        ]);

        if ($refusal === null) {
            MuteGatewayConnectionEvents::dispatch($instance->id)->onQueue('whatsapp');
        }

        broadcast(new \App\Events\InstanceStatusChanged($instance->fresh()));
    }

    /**
     * Why this instance's connection events must not be muted, or null if they
     * may be.
     *
     * Muting is reversible, keeps the webhook enabled and leaves message
     * delivery alone, but it still hides genuine state changes from the
     * dashboard, so each gate here is a hard stop rather than a heuristic.
     */
    public function muteRefusal(WhatsAppInstance $instance): ?string
    {
        if (!config('whatsapp.flap_guard.mute.enabled', true)) {
            return 'disabled by config';
        }

        if (in_array($instance->status, $this->protectedStatuses(), true)) {
            return "protected status '{$instance->status}'";
        }

        if (blank($instance->gateway_instance_id)) {
            return 'no gateway instance';
        }

        if (!$instance->hasGatewayCredentials()) {
            return 'no gateway credentials';
        }

        // The decisive gate: an instance that recently carried a message is in
        // use, whatever its connection state claims.
        //
        // This reads the conversations table rather than the instance's own
        // last_message_at column, which nothing in the message pipeline writes —
        // trusting it would make this gate silently always-pass.
        $idleMinutes = (int) config('whatsapp.flap_guard.mute.idle_minutes', 1440);
        $cutoff      = now()->subMinutes($idleMinutes);

        $recentlyActive = DB::table('conversations')
            ->where('instance_id', $instance->id)
            ->where('last_message_at', '>', $cutoff)
            ->exists();

        if ($recentlyActive) {
            return "carried a conversation message within {$idleMinutes}m";
        }

        if ($instance->last_message_at && $instance->last_message_at->gt($cutoff)) {
            return "carried a message within {$idleMinutes}m";
        }

        return null;
    }

    /** @return array{closes:int,permanent:int} */
    private function bump(WhatsAppInstance $instance, bool $permanent): array
    {
        $key      = $this->cacheKey($instance);
        $counters = Cache::get($key, ['closes' => 0, 'permanent' => 0]);

        $counters['closes']++;
        // Consecutive, so a non-permanent close resets the permanent streak.
        $counters['permanent'] = $permanent ? $counters['permanent'] + 1 : 0;

        Cache::put($key, $counters, (int) config('whatsapp.flap_guard.window_seconds', 300));

        return $counters;
    }

    private function protectedStatuses(): array
    {
        return (array) config('whatsapp.flap_guard.mute.protected_statuses', ['connected', 'banned']);
    }

    private function cacheKey(WhatsAppInstance $instance): string
    {
        return "wa-flap-guard:{$instance->id}";
    }

    private function extractState(array $payload): string
    {
        if (data_get($payload, 'data.qrcode') !== null) {
            return 'connecting';
        }

        return strtolower(trim((string) (
            data_get($payload, 'data.state')
            ?? data_get($payload, 'state')
            ?? data_get($payload, 'data.connectionStatus')
            ?? data_get($payload, 'connectionStatus')
            ?? data_get($payload, 'data.status')
            ?? data_get($payload, 'status')
            ?? ''
        )));
    }

    private function extractReason(array $payload): ?int
    {
        $reason = data_get($payload, 'data.statusReason')
            ?? data_get($payload, 'statusReason')
            ?? data_get($payload, 'data.reason')
            ?? data_get($payload, 'reason');

        return is_numeric($reason) ? (int) $reason : null;
    }
}
