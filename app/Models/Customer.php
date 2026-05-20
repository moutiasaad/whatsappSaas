<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'phone_e164', 'display_name', 'profile_pic_url', 'tags', 'notes', 'first_contact_at'];
    protected $casts = [
        'tenant_id'         => 'integer',
        'tags'              => 'array',
        'first_contact_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }

    public function getDisplayNameOrPhoneAttribute(): string
    {
        return $this->display_name ?: $this->phone_e164;
    }
}
