<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiApiUsage extends Model
{
    protected $fillable = [
        'tenant_id', 'source', 'conversation_id', 'model',
        'input_tokens', 'output_tokens', 'cost_usd', 'meta',
    ];

    protected $casts = [
        'tenant_id'     => 'integer',
        'conversation_id' => 'integer',
        'input_tokens'  => 'integer',
        'output_tokens' => 'integer',
        'cost_usd'      => 'decimal:6',
        'meta'          => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
