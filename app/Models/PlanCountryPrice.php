<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanCountryPrice extends Model
{
    protected $fillable = ['plan_id', 'country_code', 'price_monthly', 'price_annual'];

    protected $casts = [
        'plan_id'       => 'integer',
        'price_monthly' => 'decimal:2',
        'price_annual'  => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
