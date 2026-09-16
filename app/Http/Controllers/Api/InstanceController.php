<?php

namespace App\Http\Controllers\Api;

use App\Events\InstanceStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstanceController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId  = auth()->user()->tenant_id;
        $instances = WhatsAppInstance::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->makeHidden(['gateway_api_key', 'webhook_token']);

        return response()->json([
            'data'  => $instances,
            'stats' => [
                'connected'    => $instances->where('status', 'connected')->count(),
                'connecting'   => $instances->whereIn('status', ['connecting', 'qr_pending'])->count(),
                'disconnected' => $instances->whereIn('status', ['disconnected', 'error', 'banned'])->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user     = auth()->user();
        $tenantId = $user->tenant_id;

        app(\App\Services\Billing\TenantQuota::class)->assertCanCreateInstance($user);

        $request->validate([
            'name'    => 'required|string|max:100',
            'team_id' => 'nullable|integer|exists:teams,id',
        ]);

        $instance = WhatsAppInstance::create([
            'tenant_id'      => $tenantId,
            'team_id'        => $request->team_id,
            'name'           => $request->name,
            'status'         => 'disconnected',
            'webhook_token'  => Str::random(64),
            'gateway_url'    => config('services.whatsapp.default_url'),
            'gateway_api_key'=> config('services.whatsapp.default_api_key'),
        ]);

        return response()->json($instance->makeHidden(['gateway_api_key', 'webhook_token']), 201);
    }

    public function show(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        return response()->json($instance->makeHidden(['gateway_api_key', 'webhook_token']));
    }

    public function connect(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        // PROC-019: a deliberate re-pair is the only thing that can clear a
        // session WhatsApp has refused, so it clears the flap guard and opens a
        // grace window the guard stands down for while the phone scans.
        $guard = app(\App\Services\WhatsApp\ConnectionFlapGuard::class);
        $refusedSession = $guard->isTripped($instance)
            && (bool) data_get($instance->settings, 'connection_guard.permanent');
        $guard->reset($instance);
        $guard->beginPairing($instance);
        $instance->refresh();

        try {
            $gateway = $this->gateway($instance);

            // Return cached QR if still valid
            if ($instance->qr_code && in_array($instance->status, ['connecting', 'qr_pending'], true)) {
                return response()->json([
                    'qr_code' => $instance->qr_code,
                    'status'  => $instance->status,
                ]);
            }

            // Ensure a gateway instance exists for this record. The gateway name is
            // deterministic, so after a disconnect the instance may still exist on the
            // gateway — createInstance then returns 400 "Instance already exists", which
            // we treat as success and reuse the existing instance to fetch a fresh QR.
            $createResult = null;
            if (!$instance->gateway_instance_id) {
                $gatewayName  = 'wa-' . $instance->tenant_id . '-' . $instance->id;
                $createResult = $this->createOrReuseGatewayInstance($gateway, $gatewayName);
                $instance->update([
                    'gateway_instance_id' => $createResult['name']
                        ?? $createResult['instance']['instanceId']
                        ?? $createResult['instanceName']
                        ?? $gatewayName,
                ]);
                $instance->refresh();
            }

            // A session WhatsApp has permanently refused will never emit a QR:
            // the gateway keeps trying to resume the stored credentials instead
            // of pairing. Logging out clears those credentials while leaving the
            // instance itself in place, so this connect pairs from scratch.
            if ($refusedSession && $instance->gateway_instance_id) {
                try {
                    $gateway->logout($instance->gateway_instance_id);
                    \Illuminate\Support\Facades\Log::channel('whatsapp')->info('Cleared refused session before re-pair', [
                        'instance_id' => $instance->id,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::channel('whatsapp')->warning('Session clear failed', [
                        'instance_id' => $instance->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $this->ensureWebhookRegistered($gateway, $instance);

            $qr = $gateway->getQrCode($instance->gateway_instance_id)
                ?? $this->qrFromCreateResult($createResult);

            if ($qr === null) {
                // Maybe it's already connected — check before assuming it's stuck
                $gatewayStatus = $gateway->getStatus($instance->gateway_instance_id);

                if ($gatewayStatus === 'connected') {
                    $instance->update(['status' => 'connected', 'qr_code' => null, 'last_status_at' => now()]);
                    return response()->json(['status' => 'connected', 'qr_code' => null]);
                }

                // Recycle only an instance we did NOT just create. The gateway
                // never returns the QR inline, so before this guard the very
                // first connect always landed here and tore down the instance it
                // had created moments earlier — killing the pairing session that
                // was about to emit qrcode.updated. The instance then flapped
                // close(405)/connecting and no QR was ever produced. A freshly
                // created instance is left alone to finish pairing.
                if ($createResult === null) {
                    $oldGatewayId = $instance->gateway_instance_id;
                    try {
                        $gateway->deleteInstance($oldGatewayId);
                    } catch (\Throwable) {}

                    sleep(1);

                    $result = $this->createOrReuseGatewayInstance($gateway, $oldGatewayId);
                    $newGatewayId = $result['name']
                        ?? $result['instance']['instanceId']
                        ?? $result['instanceName']
                        ?? $oldGatewayId;

                    $instance->update([
                        'gateway_instance_id' => $newGatewayId,
                        'status'              => 'disconnected',
                        'qr_code'             => null,
                    ]);
                    $instance->refresh();

                    $this->ensureWebhookRegistered($gateway, $instance);

                    $qr = $gateway->getQrCode($newGatewayId)
                        ?? $this->qrFromCreateResult($result);
                }
            }

            // Never clobber a QR the qrcode.updated webhook has already stored —
            // it normally wins the race against this response.
            $updates = ['status' => 'connecting', 'last_status_at' => now()];
            if ($qr !== null) {
                $updates['qr_code'] = $qr;
            }
            $instance->update($updates);

            return response()->json([
                'qr_code' => $qr ?? $instance->fresh()->qr_code,
                'status'  => 'connecting',
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        try {
            $gateway     = $this->gateway($instance);
            $details     = $gateway->fetchInstance($instance->gateway_instance_id);
            $status      = $gateway->statusFromPayload($details);
            $phoneNumber = $this->extractPhoneNumber($details);

            if ($status === 'connected') {
                $this->ensureWebhookRegistered($gateway, $instance);
            }

            $previousPhone = $instance->phone_number;

            $instance->update(array_filter([
                'status'         => $status,
                'phone_number'   => $phoneNumber ?: $instance->phone_number,
                'last_status_at' => now(),
            ], fn($v) => $v !== null));

            // Trial-abuse detection: whenever we learn (or re-learn) which
            // WhatsApp number this instance is bound to, check for another
            // tenant using the same number. If found, materialise a
            // TenantLink row so the super-admin's linked-accounts view
            // shows the two side by side. Only runs when phone_number is
            // actually populated AND has just been (re)learned — quiet
            // no-op on every subsequent status poll.
            if ($phoneNumber && $phoneNumber !== $previousPhone) {
                $this->recordTenantLinksForPhone($instance->tenant_id, (int) $instance->id, $phoneNumber);
            }

            broadcast(new InstanceStatusChanged($instance->fresh()));

            return response()->json([
                'status'   => $status,
                'qr_code'  => $instance->qr_code,
                'instance' => $instance->fresh()->makeHidden(['gateway_api_key', 'webhook_token']),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function disconnect(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        try {
            $this->gateway($instance)->logout($instance->gateway_instance_id);
        } catch (\Exception) {
            // Gateway may already be unreachable — continue with local update
        }

        // The gateway's "logout" actually deletes the instance (DELETE /instance/delete),
        // so the stored gateway_instance_id now points at a non-existent instance.
        // Clear it so the next connect() takes the clean "create a fresh instance" path
        // and returns a new QR, instead of trying to fetch a QR for a dead instance.
        $instance->update([
            'status'              => 'disconnected',
            'qr_code'             => null,
            'gateway_instance_id' => null,
        ]);

        broadcast(new InstanceStatusChanged($instance->fresh()));

        return response()->json(['message' => __('ui.controller_messages.instance_disconnected')]);
    }

    public function destroy(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorizeInstance($instance);

        // Same trial-guard as the web destroy path — the API surface exists so
        // programmatic clients (dashboard AJAX, integrations) can hit it, and
        // both need to enforce the same rule. See InstanceWebController::destroy
        // for the rationale.
        if (! auth()->user()->isSuperAdmin() && $instance->tenant?->isOnTrial()) {
            return response()->json([
                'message' => __('ui.controller_messages.instance_delete_blocked_trial'),
            ], 403);
        }

        if ($instance->gateway_instance_id) {
            try {
                $this->gateway($instance)->deleteInstance($instance->gateway_instance_id);
            } catch (\Exception) {
                // Continue even if gateway deletion fails
            }
        }

        $instance->delete();

        return response()->json(['message' => __('ui.controller_messages.instance_deleted', ['name' => $instance->name])]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function authorizeInstance(WhatsAppInstance $instance): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless(
            (int) $instance->tenant_id === (int) $user->tenant_id,
            403,
            'This instance does not belong to your tenant.'
        );
    }

    /**
     * Create a gateway instance, tolerating the "already exists" case.
     *
     * The gateway name is deterministic per local instance, so a disconnect that
     * doesn't actually remove the instance on the gateway leaves it in place. Calling
     * createInstance again then returns 400 "Instance already exists" — we swallow that
     * and return an empty result so the caller keeps the deterministic name and fetches
     * a fresh QR from the existing instance.
     */
    private function createOrReuseGatewayInstance(EvolutionApiClient $gateway, string $name): array
    {
        try {
            return $gateway->createInstance($name);
        } catch (\Throwable $e) {
            // Different gateway versions phrase the name-clash error differently:
            // iStoreBox now returns "This name ... is already in use", older
            // Evolution builds returned "Instance already exists". Both mean
            // "reuse the existing one".
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'already exists')
                || str_contains($msg, 'already in use')
                || str_contains($msg, 'already registered')) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Extract a normalized QR data-URI from a createInstance response, if the
     * gateway returned one inline (iStoreBox/Evolution include qrcode.base64 on create).
     */
    private function qrFromCreateResult(?array $result): ?string
    {
        if (!is_array($result)) {
            return null;
        }

        $code = data_get($result, 'qrcode.base64')
            ?? data_get($result, 'qrcode.code')
            ?? data_get($result, 'qr.base64')
            ?? data_get($result, 'base64');

        if (!is_string($code) || trim($code) === '') {
            return null;
        }

        if (str_starts_with($code, 'data:image/')) {
            return $code;
        }

        return 'data:image/png;base64,' . preg_replace('/\s+/', '', $code);
    }

    private function gateway(WhatsAppInstance $instance): EvolutionApiClient
    {
        return new EvolutionApiClient(
            $instance->effectiveGatewayUrl(),
            $instance->effectiveGatewayApiKey()
        );
    }

    private function ensureWebhookRegistered(EvolutionApiClient $gateway, WhatsAppInstance $instance): void
    {
        $url = $this->webhookUrl($instance);

        if ($instance->webhook_enabled && $instance->webhook_url === $url) {
            return;
        }

        try {
            // PROC-018: pass the instance's HMAC secret so the gateway signs
            // its posts. The admin UI path already did this; the API path did
            // not, so instances configured through /api/instance/... never got
            // the verifier armed for them.
            $result = $gateway->setWebhook(
                $instance->gateway_instance_id,
                $url,
                $this->defaultWebhookEvents(),
                $instance->webhook_secret,
            );
            \Illuminate\Support\Facades\Log::channel('whatsapp')->info('Webhook registered', [
                'instance_id' => $instance->id,
                'url'         => $url,
                'response'    => $result,
            ]);
            $instance->update([
                'webhook_enabled'  => true,
                'webhook_url'      => $url,
                'webhook_last_set' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('whatsapp')->error('Webhook registration failed', [
                'instance_id' => $instance->id,
                'error'       => $e->getMessage(),
            ]);
            report($e);
        }
    }

    private function defaultWebhookEvents(): array
    {
        return [
            'qrcodeUpdated'              => true,
            'messagesUpsert'             => true,
            'messagesUpdated'            => true,
            'sendMessage'                => true,
            'contactsUpsert'             => true,
            'contactsUpdated'            => true,
            'chatsUpsert'                => true,
            'chatsUpdated'               => true,
            'presenceUpdated'            => true,
            'connectionUpdated'          => true,
            'statusInstance'             => true,
            'messagesSet'                => false,
            'contactsSet'                => true,
            'chatsSet'                   => false,
            'chatsDeleted'               => true,
            'groupsUpsert'               => true,
            'groupsUpdated'              => true,
            'groupsParticipantsUpdated'  => true,
            'refreshToken'               => true,
        ];
    }

    private function webhookUrl(WhatsAppInstance $instance): string
    {
        $base = rtrim((string) config('services.whatsapp.webhook_base_url', config('app.url')), '/');

        return "{$base}/api/webhooks/whatsapp/{$instance->webhook_token}";
    }

    private function extractPhoneNumber(array $details): ?string
    {
        $candidate = data_get($details, 'ownerJid')
            ?? data_get($details, 'instance.ownerJid')
            ?? data_get($details, 'number')
            ?? data_get($details, 'instance.number')
            ?? data_get($details, 'me.jid')
            ?? data_get($details, 'instance.me.jid')
            ?? data_get($details, 'me.id')
            ?? data_get($details, 'instance.me.id');

        if (!is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = preg_replace('/[^0-9@]/', '', $candidate);
        if (!$candidate) {
            return null;
        }

        return str_contains($candidate, '@')
            ? preg_replace('/@.*$/', '', $candidate)
            : $candidate;
    }

    /**
     * Trial-abuse detector: for every OTHER tenant that has ever bound an
     * instance to this phone number, materialise a TenantLink row so the
     * super-admin sees the two tenants connected under "Linked accounts".
     *
     * Wrapped in rescue() because a failure here must NOT break the
     * status endpoint the whole instances UI polls. Detection is a nice-
     * to-have on the hot path; the migration's backfill will catch any
     * link we drop.
     */
    private function recordTenantLinksForPhone(int $tenantId, int $instanceId, string $phoneNumber): void
    {
        rescue(function () use ($tenantId, $instanceId, $phoneNumber) {
            $otherTenantIds = WhatsAppInstance::query()
                ->where('phone_number', $phoneNumber)
                ->where('tenant_id', '!=', $tenantId)
                ->pluck('tenant_id')
                ->unique()
                ->values()
                ->all();

            foreach ($otherTenantIds as $otherId) {
                \App\Models\TenantLink::link(
                    $tenantId,
                    (int) $otherId,
                    \App\Models\TenantLink::REASON_SHARED_WHATSAPP_INSTANCE,
                    ['phone_numbers' => [$phoneNumber]],
                );
            }
        }, null, false); // report:false — logged only, no visible error
    }
}
