<?php

namespace App\Services\Otp;

use App\Events\InstanceStatusChanged;
use App\Models\OtpCode;
use App\Models\Tenant;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    /**
     * Send a WhatsApp OTP to the given phone number for the tenant.
     * Returns an array describing the outcome.
     */
    public function send(Tenant $tenant, string $phone): array
    {
        $settings = $this->settings($tenant);

        if (!$settings['enabled']) {
            return $this->fail('otp_disabled', 'OTP service is disabled for this workspace.', 403);
        }

        $identifier = $this->normalize($phone);
        if ($identifier === '') {
            return $this->fail('invalid_phone', 'Phone number is not valid.', 422);
        }

        $instance = $this->connectedInstance($tenant);
        if (!$instance) {
            return $this->fail('no_instance', 'No connected WhatsApp instance for this workspace.', 422);
        }

        $existing = OtpCode::where('tenant_id', $tenant->id)
            ->where('identifier', $identifier)
            ->first();

        if ($existing && $existing->last_sent_at) {
            $wait = self::RESEND_COOLDOWN_SECONDS - $existing->last_sent_at->diffInSeconds(now());
            if ($wait > 0) {
                return $this->fail('cooldown', "Please wait {$wait}s before requesting a new code.", 429, [
                    'retry_after' => $wait,
                ]);
            }
        }

        $code = $this->generateCode((int) $settings['code_length']);
        $ttl  = (int) $settings['ttl_minutes'];
        $body = $this->renderTemplate($settings['template'], $code, $ttl);

        // UI-002/UI-003: no plaintext logging, and no plaintext row column.
        // The code exists only in memory on this request and as a bcrypt hash
        // in code_hash — nobody with DB or log read access can recover it.

        $gateway = new EvolutionApiClient(
            $instance->effectiveGatewayUrl(),
            $instance->effectiveGatewayApiKey()
        );

        try {
            $gateway->sendText($instance->gateway_instance_id, $identifier, $body);
        } catch (\Throwable $e) {
            return $this->handleSendFailure($tenant, $instance, $gateway, $e);
        }

        OtpCode::updateOrCreate(
            ['tenant_id' => $tenant->id, 'identifier' => $identifier],
            [
                'code_hash'    => Hash::make($code),
                'attempts'     => 0,
                'resend_count' => $existing ? $existing->resend_count + 1 : 0,
                'expires_at'   => now()->addMinutes($ttl),
                'last_sent_at' => now(),
                'verified_at'  => null,
            ]
        );

        return [
            'ok'         => true,
            'identifier' => $identifier,
            'expires_in' => $ttl * 60,
        ];
    }

    /**
     * Turn a failed gateway send into an actionable answer.
     *
     * The gateway answers HTTP 500 with an empty body for every send it cannot
     * complete, so the exception alone cannot say why. Re-probing the instance
     * separates the two cases the caller has to word differently: our own
     * WhatsApp socket is down (nothing the end user can do), or the socket is
     * fine and WhatsApp refused this particular recipient (most often a number
     * that is not on WhatsApp).
     */
    private function handleSendFailure(
        Tenant $tenant,
        WhatsAppInstance $instance,
        EvolutionApiClient $gateway,
        \Throwable $e
    ): array {
        $liveStatus = null;

        try {
            $liveStatus = $gateway->probeStatus($instance->gateway_instance_id);
        } catch (\Throwable) {
            // Leave $liveStatus null — treated as "could not determine".
        }

        Log::warning('OTP send failed', [
            'tenant_id'   => $tenant->id,
            'instance_id' => $instance->id,
            'gateway_id'  => $instance->gateway_instance_id,
            'live_status' => $liveStatus,
            'error'       => $e->getMessage(),
        ]);

        if ($liveStatus !== null && $liveStatus !== 'connected') {
            // The dashboard is showing this instance as connected but its
            // WhatsApp socket has dropped. Correct the record so the workspace
            // owner sees the real state and knows to re-scan the QR.
            if ($instance->status !== $liveStatus) {
                $instance->update(['status' => $liveStatus, 'last_status_at' => now()]);

                try {
                    broadcast(new InstanceStatusChanged($instance->fresh()));
                } catch (\Throwable) {
                    // The corrected row is what matters here; a dead broadcast
                    // connection must not turn this into a 500 for the caller.
                }
            }

            return $this->fail(
                'instance_offline',
                'The workspace WhatsApp connection is offline. Reconnect the instance and try again.',
                503
            );
        }

        return $this->fail(
            'gateway_error',
            'WhatsApp rejected delivery to this number. Check that it is an active WhatsApp account.',
            502
        );
    }

    /**
     * Verify a phone/code pair. Consumes the code on success.
     */
    public function verify(Tenant $tenant, string $phone, string $code): array
    {
        $identifier = $this->normalize($phone);
        if ($identifier === '') {
            return $this->fail('invalid_phone', 'Phone number is not valid.', 422);
        }

        $record = OtpCode::where('tenant_id', $tenant->id)
            ->where('identifier', $identifier)
            ->first();

        if (!$record) {
            return $this->fail('not_found', 'No OTP was requested for this number.', 404);
        }

        if ($record->verified_at) {
            return $this->fail('already_used', 'This code has already been used.', 410);
        }

        if ($record->expires_at->isPast()) {
            return $this->fail('expired', 'The OTP has expired.', 410);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            return $this->fail('too_many_attempts', 'Too many failed attempts. Request a new code.', 429);
        }

        if (!Hash::check((string) $code, $record->code_hash)) {
            $record->increment('attempts');
            return $this->fail('invalid_code', 'Incorrect code.', 401, [
                'attempts_left' => max(0, self::MAX_ATTEMPTS - $record->attempts),
            ]);
        }

        $record->update(['verified_at' => now()]);

        return [
            'ok'         => true,
            'identifier' => $identifier,
            'verified_at'=> $record->verified_at->toIso8601String(),
        ];
    }

    /**
     * Resolve per-tenant OTP settings, merging defaults.
     * Stored under tenants.settings['otp'].
     */
    public function settings(Tenant $tenant): array
    {
        $all = $tenant->settings ?? [];
        $otp = is_array($all['otp'] ?? null) ? $all['otp'] : [];

        return array_merge([
            'enabled'     => false,
            'code_length' => 6,
            'ttl_minutes' => 10,
            'template'    => 'Your verification code is {code}. It expires in {ttl} minutes.',
        ], $otp);
    }

    public function saveSettings(Tenant $tenant, array $settings): void
    {
        $all = $tenant->settings ?? [];
        $all['otp'] = array_merge($this->settings($tenant), $settings);
        $tenant->settings = $all;
        $tenant->save();
    }

    private function connectedInstance(Tenant $tenant): ?WhatsAppInstance
    {
        return WhatsAppInstance::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'connected')
            ->orderBy('id')
            ->first();
    }

    private function generateCode(int $length): string
    {
        $length = max(4, min(8, $length));
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_repeat('9', $length);
        return (string) random_int($min, $max);
    }

    private function renderTemplate(string $template, string $code, int $ttl): string
    {
        return strtr($template, [
            '{code}' => $code,
            '{ttl}'  => (string) $ttl,
        ]);
    }

    private function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function fail(string $error, string $message, int $status, array $extra = []): array
    {
        return array_merge([
            'ok'      => false,
            'error'   => $error,
            'message' => $message,
            'status'  => $status,
        ], $extra);
    }
}
