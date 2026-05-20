<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    protected $fillable = ['tenant_id', 'instance_id', 'event_type', 'payload', 'processed_at', 'error'];
    protected $casts = [
        'tenant_id'    => 'integer',
        'instance_id'  => 'integer',
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function instance(): BelongsTo { return $this->belongsTo(WhatsAppInstance::class, 'instance_id'); }
}
