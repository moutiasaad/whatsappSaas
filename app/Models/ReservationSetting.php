<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationSetting extends Model
{
    protected $fillable = [
        'tenant_id', 'instance_id', 'service_name',
        'trigger_keywords', 'welcome_message', 'select_date_message',
        'select_slot_message', 'ask_name_message', 'ask_notes_message',
        'confirmation_message', 'cancellation_message', 'no_slots_message',
        'collect_notes', 'is_active',
    ];

    protected $casts = [
        'trigger_keywords' => 'array',
        'collect_notes'    => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function instance(): BelongsTo { return $this->belongsTo(WhatsAppInstance::class, 'instance_id'); }

    public function matchesTrigger(string $text): bool
    {
        $keywords = $this->trigger_keywords ?: [];
        $normalized = mb_strtolower(trim($text));
        foreach ($keywords as $kw) {
            if (str_contains($normalized, mb_strtolower(trim($kw)))) {
                return true;
            }
        }
        return false;
    }

    public static function forTenant(int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)->first();
    }
}
