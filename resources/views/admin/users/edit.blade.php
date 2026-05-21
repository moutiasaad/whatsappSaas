@extends('layouts.admin')

@section('title', 'Edit User')

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}" style="color:var(--text-secondary);text-decoration:none">Users</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $user->name }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 280px;gap:1.5rem;align-items:start">

    {{-- Left: Edit Form --}}
    <div class="card">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:.875rem">
                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                     style="width:2.75rem;height:2.75rem;border-radius:50%;object-fit:cover">
                <div>
                    <div class="card-title">{{ $user->name }}</div>
                    <div class="card-subtitle">{{ $user->email }}</div>
                </div>
            </div>
            <span class="badge {{ $user->is_active ? 'badge-green' : 'badge-gray' }}">
                {{ $user->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form action="{{ route('admin.users.update', $user) }}" method="POST" data-unsaved data-loading>
            @csrf
            @method('PUT')

            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                {{-- Name + Email --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label class="form-label" for="name">Full Name</label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $user->name) }}"
                               class="form-control @error('name') error @enderror">
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email"
                               value="{{ $user->email }}"
                               class="form-control"
                               style="background:var(--page-bg);color:var(--text-muted);cursor:not-allowed"
                               disabled>
                        <div class="form-hint">Email cannot be changed after account creation</div>
                    </div>
                </div>

                {{-- Role --}}
                <div class="form-group">
                    <label class="form-label" for="role">Role</label>
                    <select id="role" name="role"
                            class="form-control @error('role') error @enderror"
                            x-data x-model="$el.value"
                            @change="updateRoleHint($event.target.value)"
                            >
                        <option value="agent"      {{ old('role', $user->role) === 'agent'      ? 'selected' : '' }}>Agent</option>
                        <option value="supervisor" {{ old('role', $user->role) === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                        <option value="admin"      {{ old('role', $user->role) === 'admin'      ? 'selected' : '' }}>Admin</option>
                    </select>
                    @error('role') <div class="form-error">{{ $message }}</div> @enderror
                    <div id="role-hint" style="margin-top:.5rem;font-size:.8125rem;color:var(--text-muted)"></div>
                </div>

                {{-- Status toggle --}}
                <div class="form-group">
                    <label class="form-label">Account Status</label>
                    <div style="display:flex;align-items:center;gap:1rem;padding:.75rem 1rem;border:1px solid var(--card-border);border-radius:.625rem">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                            <span class="toggle-text">Active</span>
                        </label>
                        <span style="font-size:.8125rem;color:var(--text-muted)">Inactive users cannot log in or access the platform</span>
                    </div>
                </div>

                {{-- Teams --}}
                <div class="form-group">
                    <label class="form-label">Team Membership</label>
                    @if($teams->isEmpty())
                        <div style="font-size:.8125rem;color:var(--text-muted);padding:.5rem 0">No teams created yet</div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.25rem">
                            @foreach($teams as $team)
                            @php $checked = in_array($team->id, old('teams', $user->teams->pluck('id')->toArray())); @endphp
                            <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer;padding:.625rem .875rem;border:1.5px solid {{ $checked ? 'var(--brand)' : 'var(--card-border)' }};border-radius:.5rem;transition:border-color .15s;background:{{ $checked ? 'rgba(16,185,129,.04)' : 'transparent' }}"
                                   x-data
                                   @click="$el.style.borderColor = $el.querySelector('input').checked ? 'var(--card-border)' : 'var(--brand)'; $el.style.background = $el.querySelector('input').checked ? 'transparent' : 'rgba(16,185,129,.04)'">
                                <input type="checkbox" name="teams[]" value="{{ $team->id }}"
                                       {{ $checked ? 'checked' : '' }}
                                       style="accent-color:var(--brand);cursor:pointer;width:1rem;height:1rem">
                                <div style="flex:1">
                                    <div style="font-size:.875rem;font-weight:500">{{ $team->name }}</div>
                                    @if($team->description)
                                        <div style="font-size:.75rem;color:var(--text-muted)">{{ $team->description }}</div>
                                    @endif
                                </div>
                                <span style="font-size:.75rem;color:var(--text-muted)">
                                    {{ $team->users_count }} member{{ $team->users_count !== 1 ? 's' : '' }}
                                </span>
                            </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Reset Password --}}
                <div class="form-group" x-data="{ show: false }">
                    <label class="form-label">
                        Password
                        <button type="button" @click="show = !show"
                                style="font-size:.75rem;font-weight:400;color:var(--brand);background:none;border:none;cursor:pointer;padding:0;margin-left:.375rem"
                                x-text="show ? 'Cancel' : 'Reset password'"></button>
                    </label>
                    <div x-show="show" x-transition style="display:flex;flex-direction:column;gap:.75rem;margin-top:.375rem">
                        <div style="position:relative">
                            <input type="password" id="password" name="password"
                                   placeholder="New password (min 8 characters)"
                                   class="form-control @error('password') error @enderror"
                                   style="padding-right:2.75rem">
                            <button type="button"
                                    onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password'"
                                    style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:.25rem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        @error('password') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">Leave blank to keep the current password</div>
                    </div>
                    <div x-show="!show" style="font-size:.8125rem;color:var(--text-muted);margin-top:.25rem">
                        ••••••••  <span style="font-size:.75rem">(hidden)</span>
                    </div>
                </div>

                {{-- Danger Zone --}}
                @if(auth()->id() !== $user->id)
                <div style="border:1px solid rgba(239,68,68,.2);border-radius:.75rem;padding:1rem">
                    <div style="font-size:.875rem;font-weight:600;color:#ef4444;margin-bottom:.375rem">Danger Zone</div>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
                        <div style="font-size:.8125rem;color:var(--text-muted)">
                            Permanently delete this user account and remove them from all teams.
                        </div>
                        <button type="button"
                                onclick="confirmDelete('{{ route('admin.users.destroy', $user) }}', { title: 'Delete {{ addslashes($user->name) }}?', message: 'This will permanently remove the user account and all team memberships.' })"
                                class="btn btn-danger btn-sm">
                            Delete User
                        </button>
                    </div>
                </div>
                @endif
            </div>

            <div style="padding:1.25rem 1.5rem;border-top:1px solid var(--card-border);display:flex;justify-content:flex-end;gap:.5rem">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    {{-- Right: Activity Sidebar --}}
    <div style="display:flex;flex-direction:column;gap:1rem">

        {{-- Quick Stats --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Activity</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.625rem;font-size:.8125rem">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Joined</span>
                    <span>{{ $user->created_at->format('M j, Y') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Last login</span>
                    <span>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Active convos</span>
                    <span>{{ $stats['active'] }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Closed total</span>
                    <span>{{ $stats['closed_total'] }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Closed this month</span>
                    <span>{{ $stats['closed_month'] }}</span>
                </div>
            </div>
        </div>

        {{-- Current Teams --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Teams</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.5rem">
                @forelse($user->teams as $team)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .625rem;background:var(--page-bg);border-radius:.5rem">
                    <span style="font-size:.875rem;font-weight:500">{{ $team->name }}</span>
                    <a href="{{ route('admin.teams.edit', $team) }}"
                       style="font-size:.75rem;color:var(--text-muted);text-decoration:none"
                       onmouseenter="this.style.color='var(--brand)'" onmouseleave="this.style.color='var(--text-muted)'">
                        Edit
                    </a>
                </div>
                @empty
                <div style="font-size:.8125rem;color:var(--text-muted);text-align:center;padding:.5rem 0">Not in any team</div>
                @endforelse
            </div>
        </div>

        {{-- Impersonate --}}
        @if(auth()->id() !== $user->id)
        @can('impersonate', $user)
        <div class="card">
            <div style="padding:1.25rem">
                <div style="font-size:.875rem;font-weight:600;color:var(--text-primary);margin-bottom:.375rem">Impersonate</div>
                <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.875rem">
                    Log in as this user to debug issues. An amber banner will remind you.
                </div>
                <a href="{{ route('admin.users.impersonate', $user) }}"
                   onclick="return confirm('Impersonate {{ $user->name }}?')"
                   class="btn btn-outline btn-sm" style="width:100%;justify-content:center">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Log in as {{ $user->name }}
                </a>
            </div>
        </div>
        @endcan
        @endif
    </div>
</div>

<script>
const roleHints = {
    agent:      'Can see the pool, claim conversations, and reply to their own assigned conversations.',
    supervisor: 'Same as agent, plus can view all team conversations and reassign to any agent.',
    admin:      'Full tenant access: users, teams, instances, conversations, AI settings, and audit log.'
};

function updateRoleHint(role) {
    const el = document.getElementById('role-hint');
    if (el) el.textContent = roleHints[role] || '';
}

// Init hint on page load
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('role');
    if (sel) updateRoleHint(sel.value);
});
</script>
@endsection
