@extends('layouts.admin')

@section('title', 'Invite User')

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}" style="color:var(--text-secondary);text-decoration:none">Users</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>Invite User</span>
@endsection

@section('content')
<form action="{{ route('admin.users.store') }}" method="POST" data-loading>
    @csrf

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">

        {{-- Left: Account Info --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Account Information</div>
                    <div class="card-subtitle">They'll receive an email to set their password</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="name">Full Name <span style="color:#ef4444">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                               class="form-control @error('name') error @enderror" required>
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address <span style="color:#ef4444">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') error @enderror" required>
                        @error('email') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">Role <span style="color:#ef4444">*</span></label>
                    <select id="role" name="role" class="form-control @error('role') error @enderror" required>
                        <option value="">Select role…</option>
                        <option value="agent" {{ old('role') === 'agent' ? 'selected' : '' }}>Agent</option>
                        <option value="supervisor" {{ old('role') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                    @error('role') <div class="form-error">{{ $message }}</div> @enderror

                    <div style="margin-top:.625rem;display:flex;flex-direction:column;gap:.375rem">
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:flex-start;gap:.375rem">
                            <span style="font-weight:600;color:var(--text-secondary);min-width:70px">Agent</span>
                            Can see pool, claim and reply to conversations assigned to them
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:flex-start;gap:.375rem">
                            <span style="font-weight:600;color:var(--text-secondary);min-width:70px">Supervisor</span>
                            Same as agent, plus can view team conversations and reassign
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:flex-start;gap:.375rem">
                            <span style="font-weight:600;color:var(--text-secondary);min-width:70px">Admin</span>
                            Full tenant access: users, teams, instances, conversations, AI settings, and audit log
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        Temporary Password
                        <span style="font-size:.75rem;font-weight:400;color:var(--text-muted)">(optional — leave blank to send invite email)</span>
                    </label>
                    <input type="password" id="password" name="password"
                           placeholder="Min 8 characters"
                           class="form-control @error('password') error @enderror">
                    @error('password') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Right: Team Assignment --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Assign to Teams</div>
                    <div class="card-subtitle">Optional — can be changed later</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem">
                @forelse($teams as $team)
                <label style="display:flex;align-items:center;gap:.625rem;cursor:pointer;padding:.625rem .875rem;border:1.5px solid var(--card-border);border-radius:.5rem;margin-bottom:.5rem;transition:border-color .15s"
                       x-data
                       @click="$el.style.borderColor = $el.querySelector('input').checked ? 'var(--card-border)' : 'var(--brand)'; $el.style.background = $el.querySelector('input').checked ? 'transparent' : 'rgba(16,185,129,.04)'">
                    <input type="checkbox" name="teams[]" value="{{ $team->id }}"
                           {{ in_array($team->id, old('teams', [])) ? 'checked' : '' }}
                           style="accent-color:var(--brand);cursor:pointer;width:1rem;height:1rem">
                    <div style="flex:1">
                        <div style="font-size:.875rem;font-weight:500">{{ $team->name }}</div>
                        @if($team->description)
                            <div style="font-size:.75rem;color:var(--text-muted)">{{ $team->description }}</div>
                        @endif
                    </div>
                    <span style="font-size:.75rem;color:var(--text-muted)">
                        {{ $team->users_count ?? 0 }} member{{ ($team->users_count ?? 0) !== 1 ? 's' : '' }}
                    </span>
                </label>
                @empty
                <div style="padding:2rem;text-align:center">
                    <div class="empty-state-icon" style="margin:0 auto .75rem">
                        <i class="ri-team-line" style="font-size:1.5rem"></i>
                    </div>
                    <p style="color:var(--text-muted);font-size:.875rem">No teams created yet</p>
                    <a href="{{ route('admin.teams.create') }}" style="font-size:.8125rem;color:var(--brand)">Create a team first</a>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Send Invitation</button>
    </div>
</form>
@endsection
