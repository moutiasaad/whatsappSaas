<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'type', 'title', 'body', 'metadata', 'is_active', 'sort_order'];
    protected $casts = ['metadata' => 'array', 'is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOfType($q, string $type) { return $q->where('type', $type); }
}
