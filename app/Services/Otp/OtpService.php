<?php

namespace App\Services\Otp;

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

        // UI-003: never log the plaintext OTP. A misconfigured APP_DEBUG=true in
        // production used to expose every code in real time to anyone with log
        // read access. The code lives on OtpCode.plaintext_code below anyway,
        // scoped to the tenant, if operators genuinely need to inspect it.

        try {
            $gateway = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );
            $gateway->sendText($instance->gateway_instance_id, $identifier, $body);
        } catch (\Throwable $e) {
            return $this->fail('gateway_error', 'Failed to deliver OTP: ' . $e->getMessage(), 502);
        }

        OtpCode::updateOrCreate(
            ['tenant_id' => $tenant->id, 'identifier' => $identifier],
            [
                'code'         => $code,
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
