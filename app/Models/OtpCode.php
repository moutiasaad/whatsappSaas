<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpCode extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'identifier', 'code_hash',
        'attempts', 'resend_count',
        'expires_at', 'last_sent_at', 'verified_at',
    ];

    protected $casts = [
        'tenant_id'    => 'integer',
        'attempts'     => 'integer',
        'resend_count' => 'integer',
        'expires_at'   => 'datetime',
        'last_sent_at' => 'datetime',
        'verified_at'  => 'datetime',
    ];

    // Never expose the hash in JSON responses. The plaintext column was
    // dropped in UI-002 — verification uses code_hash only.
    protected $hidden = ['code_hash'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
