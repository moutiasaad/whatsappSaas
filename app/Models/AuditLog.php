<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'target_type', 'target_id',
        'payload', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'tenant_id'   => 'integer',
        'user_id'     => 'integer',
        'target_id'   => 'integer',
        'payload'     => 'array',
        'created_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function record(string $action, mixed $target = null, array $payload = []): void
    {
        $user       = auth()->user();
        $targetType = null;
        $targetId   = null;

        if ($target instanceof Model) {
            $targetType = class_basename($target);
            $targetId   = $target->getKey();
        }

        static::create([
            'tenant_id'   => $user?->tenant_id,
            'user_id'     => $user?->id,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'payload'     => $payload,
            'ip'          => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'created_at'  => now(),
        ]);
    }
}
