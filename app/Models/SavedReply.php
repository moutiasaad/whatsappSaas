<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReply extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'owner_user_id', 'scope', 'title', 'shortcut', 'body', 'sort_order',
    ];

    protected $casts = [
        'tenant_id'     => 'integer',
        'owner_user_id' => 'integer',
        'sort_order'    => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeVisibleTo($q, User $user)
    {
        return $q->where('tenant_id', $user->tenant_id)
            ->where(function ($inner) use ($user) {
                $inner->where('scope', 'tenant')
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('scope', 'personal')->where('owner_user_id', $user->id);
                    });
            });
    }
}
