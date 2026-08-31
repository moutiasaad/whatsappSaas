<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegisterOtp extends Model
{
    protected $table = 'register_otp_codes';

    protected $fillable = [
        'email', 'otp', 'data', 'password_hash',
        'expires_at', 'sent_at', 'verified_at', 'resend_count',
    ];

    protected $casts = [
        'data'         => 'array',
        'expires_at'   => 'datetime',
        'sent_at'      => 'datetime',
        'verified_at'  => 'datetime',
        'resend_count' => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
