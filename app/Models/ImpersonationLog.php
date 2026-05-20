<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'impersonator_user_id', 'impersonated_user_id', 'tenant_id',
        'started_at', 'ended_at', 'ip_address',
    ];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function impersonator(): BelongsTo { return $this->belongsTo(User::class, 'impersonator_user_id'); }
    public function impersonated(): BelongsTo { return $this->belongsTo(User::class, 'impersonated_user_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
