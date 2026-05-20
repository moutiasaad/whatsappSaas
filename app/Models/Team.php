<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class)->withPivot('role_in_team')->withTimestamps(); }
    public function agents() { return $this->users()->wherePivot('role_in_team', 'agent'); }
    public function supervisors() { return $this->users()->wherePivot('role_in_team', 'supervisor'); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
}
