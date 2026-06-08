<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'role',
        'is_active', 'last_login_at', 'avatar_url', 'api_key',
    ];

    protected $hidden = ['password', 'remember_token', 'api_key'];

    protected function casts(): array
    {
        return [
            'tenant_id'          => 'integer',
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot('role_in_team')->withTimestamps();
    }

    public function ownedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'owner_agent_id');
    }

    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isSupervisor(): bool { return $this->role === 'supervisor'; }
    public function isAgent(): bool { return $this->role === 'agent'; }
    public function hasRole(string $role): bool { return $this->role === $role; }
    public function hasAnyRole(array $roles): bool { return in_array($this->role, $roles); }

    public function routeNamePrefix(): string
    {
        return match ($this->role) {
            'super_admin' => 'super_admin',
            'admin'       => 'tenant_admin',
            'supervisor'  => 'supervisor',
            default       => 'agent',
        };
    }

    public function homeRouteName(): string
    {
        return match ($this->role) {
            'supervisor', 'agent' => $this->routeNamePrefix() . '.conversations.index',
            default               => $this->routeNamePrefix() . '.dashboard',
        };
    }

    public function getAvatarUrlAttribute(?string $value): string
    {
        return $value ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=10b981&color=fff';
    }
}
