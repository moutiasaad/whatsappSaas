<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'type', 'title', 'body', 'metadata', 'is_active', 'sort_order'];
    protected $casts = [
        'tenant_id'  => 'integer',
        'metadata'   => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOfType($q, string $type) { return $q->where('type', $type); }
}
