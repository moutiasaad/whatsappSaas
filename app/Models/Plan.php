<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'stripe_price_id_monthly', 'stripe_price_id_annual',
        'price_monthly', 'price_annual', 'max_users', 'max_instances',
        'max_conversations_per_month', 'ai_included', 'ai_token_quota', 'features', 'is_active',
    ];

    protected $casts = [
        'price_monthly'               => 'decimal:2',
        'price_annual'                => 'decimal:2',
        'max_users'                   => 'integer',
        'max_instances'               => 'integer',
        'max_conversations_per_month' => 'integer',
        'ai_included'                 => 'boolean',
        'ai_token_quota'              => 'integer',
        'features'                    => 'array',
        'is_active'                   => 'boolean',
    ];

    public function tenants(): HasMany { return $this->hasMany(Tenant::class); }
}
