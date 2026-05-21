<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WhatsAppInstance extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'tenant_id', 'name', 'gateway', 'gateway_instance_id', 'webhook_token',
        'webhook_secret', 'phone_number', 'status', 'last_status_at', 'last_message_at',
        'qr_code', 'gateway_url', 'gateway_api_key', 'team_id', 'settings',
    ];

    protected $casts = [
        'tenant_id'      => 'integer',
        'team_id'        => 'integer',
        'settings'       => 'array',
        'last_status_at' => 'datetime',
        'last_message_at'=> 'datetime',
    ];

    protected $hidden = ['webhook_secret', 'gateway_api_key'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->webhook_token ??= Str::random(64));
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class, 'instance_id'); }
    public function webhookEvents(): HasMany { return $this->hasMany(WebhookEvent::class, 'instance_id'); }

    public function isConnected(): bool { return $this->status === 'connected'; }

    public function effectiveGatewayUrl(): ?string
    {
        return $this->gateway_url ?: config('services.whatsapp.default_url');
    }

    public function effectiveGatewayApiKey(): ?string
    {
        return $this->gateway_api_key ?: config('services.whatsapp.default_api_key');
    }

    public function hasGatewayCredentials(): bool
    {
        return filled($this->effectiveGatewayUrl()) && filled($this->effectiveGatewayApiKey());
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'connected'   => ($this->last_message_at?->diffInHours(now()) ?? 0) > 1 ? 'yellow' : 'green',
            'connecting'  => 'yellow',
            default       => 'red',
        };
    }
}
