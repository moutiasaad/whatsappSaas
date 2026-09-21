<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A country the super admin has enabled for per-country pricing.
 * `code` is ISO alpha-2 (matches CF-IPCountry header).
 */
class Country extends Model
{
    protected $fillable = ['code', 'name', 'currency_code', 'currency_symbol', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function planPrices(): HasMany
    {
        return $this->hasMany(PlanCountryPrice::class, 'country_code', 'code');
    }
}
