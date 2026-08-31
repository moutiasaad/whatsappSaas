<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpCode extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'identifier', 'code', 'code_hash',
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

    // Never expose the plaintext or hash in JSON responses (API returns).
    // The plaintext still lives in the DB row for admin/debug lookups.
    protected $hidden = ['code', 'code_hash'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
