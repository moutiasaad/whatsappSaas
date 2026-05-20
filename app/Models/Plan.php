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
        'features'    => 'array',
        'ai_included' => 'boolean',
        'is_active'   => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_annual'  => 'decimal:2',
    ];

    public function tenants(): HasMany { return $this->hasMany(Tenant::class); }
}
