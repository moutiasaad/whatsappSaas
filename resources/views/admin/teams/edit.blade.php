@extends('layouts.admin')

@section('title', 'Edit Team')

@section('breadcrumb')
    <a href="{{ route('admin.teams.index') }}" style="color:var(--text-secondary);text-decoration:none">Teams</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $team->name }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start"
     x-data="teamForm()">

    {{-- Left: Edit Form --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Edit Team</div>
                <div class="card-subtitle">Update settings for <strong>{{ $team->name }}</strong></div>
            </div>
            <span class="badge {{ $team->is_active ? 'badge-green' : 'badge-gray' }}">
                {{ $team->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form action="{{ route('admin.teams.update', $team) }}" method="POST" data-unsaved data-loading>
            @csrf
            @method('PUT')

            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.5rem">

                {{-- Basic Info --}}
                <div style="display:flex;flex-direction:column;gap:1.125rem">
                    <div class="form-group">
                        <label class="form-label" for="name">
                            Team Name <span style="color:#ef4444">*</span>
                        </label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $team->name) }}"
                               class="form-control @error('name') error @enderror"
                               required>
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <input type="text" id="description" name="description"
                               value="{{ old('description', $team->description) }}"
                               placeholder="Short description of this team's focus"
                               class="form-control @error('description') error @enderror">
                        @error('description') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div style="display:flex;align-items:center;gap:.75rem">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $team->is_active) ? 'checked' : '' }}>
                            <span class="toggle-text">Active</span>
                        </label>
                        <span style="font-size:.8125rem;color:var(--text-muted)">Inactive teams won't receive new conversations</span>
                    </div>
                </div>

                {{-- Member Picker --}}
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.875rem">
                        <div>
                            <div style="font-size:.9375rem;font-weight:600;color:var(--text-primary)">Team Members</div>
                            <div style="font-size:.8125rem;color:var(--text-muted);margin-top:.125rem">
                                Check to include in this team
                            </div>
                        </div>
                        <span x-text="`${selected.length} member${selected.length !== 1 ? 's' : ''}`"
                              style="font-size:.8125rem;color:var(--text-muted)"></span>
                    </div>

                    {{-- Search --}}
                    <div style="position:relative;margin-bottom:.75rem">
                        <svg style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" x-model="search"
                               placeholder="Filter by name…"
                               class="filter-input" style="padding-left:2.25rem;width:100%">
                    </div>

                    {{-- Agent List --}}
                    <div style="border:1px solid var(--card-border);border-radius:.75rem;overflow:hidden;max-height:380px;overflow-y:auto">
                        @forelse($agents as $agent)
                        @php $isMember = $team->users->contains('id', $agent->id); @endphp
                        <label style="display:flex;align-items:center;gap:.875rem;padding:.75rem 1rem;cursor:pointer;border-bottom:1px solid var(--card-border);transition:background .12s"
                               x-show="!search || '{{ strtolower($agent->name) }}'.includes(search.toLowerCase())"
                               :style="selected.includes({{ $agent->id }}) ? 'background:rgba(16,185,129,.06)' : ''"
                               onmouseenter="if(!this.querySelector('input').checked) this.style.background='var(--page-bg)'"
                               onmouseleave="this.style.background=this.querySelector('input').checked?'rgba(16,185,129,.06)':''">
                            <input type="checkbox" name="members[]" value="{{ $agent->id }}"
                                   x-model="selected"
                                   :value="{{ $agent->id }}"
                                   {{ in_array($agent->id, old('members', $team->users->pluck('id')->toArray())) ? 'checked' : '' }}
                                   style="accent-color:var(--brand);width:1rem;height:1rem;flex-shrink:0;cursor:pointer">
                            <img src="{{ $agent->avatar_url }}" alt="{{ $agent->name }}"
                                 style="width:2rem;height:2rem;border-radius:50%;object-fit:cover;flex-shrink:0">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:500;font-size:.875rem">{{ $agent->name }}</div>
                                <div style="font-size:.75rem;color:var(--text-muted)">{{ $agent->email }}</div>
                            </div>
                            <div style="display:flex;align-items:center;gap:.375rem">
                                <span class="badge {{ $agent->role === 'supervisor' ? 'badge-blue' : 'badge-green' }}" style="font-size:.6875rem">
                                    {{ ucfirst($agent->role) }}
                                </span>
                                @if($isMember)
                                <span style="font-size:.6875rem;color:var(--text-muted)">current</span>
                                @endif
                            </div>
                        </label>
                        @empty
                        <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:.875rem">
                            No agents available.
                        </div>
                        @endforelse
                    </div>

                    <div style="display:flex;gap:.5rem;margin-top:.5rem">
                        <button type="button" @click="selectAll()" class="btn btn-ghost btn-sm" style="font-size:.75rem">Select all</button>
                        <button type="button" @click="selected = []" class="btn btn-ghost btn-sm" style="font-size:.75rem">Clear all</button>
                    </div>
                </div>

                {{-- Danger Zone --}}
                <div style="border:1px solid rgba(239,68,68,.2);border-radius:.75rem;padding:1rem">
                    <div style="font-size:.875rem;font-weight:600;color:#ef4444;margin-bottom:.375rem">Danger Zone</div>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
                        <div style="font-size:.8125rem;color:var(--text-muted)">
                            Permanently delete this team. Existing conversations will become unassigned.
                        </div>
                        <button type="button"
                                onclick="confirmDelete('{{ route('admin.teams.destroy', $team) }}', { title: 'Delete {{ addslashes($team->name) }}?', message: 'Existing conversations will become unassigned.' })"
                                class="btn btn-danger btn-sm">
                            Delete Team
                        </button>
                    </div>
                </div>
            </div>

            <div style="padding:1.25rem 1.5rem;border-top:1px solid var(--card-border);display:flex;justify-content:flex-end;gap:.5rem">
                <a href="{{ route('admin.teams.index') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    {{-- Right: Stats & Linked Instances --}}
    <div style="display:flex;flex-direction:column;gap:1rem">

        {{-- Live Stats --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Current Load</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.875rem">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <div style="padding:.875rem;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15);border-radius:.625rem;text-align:center">
                        <div style="font-size:1.5rem;font-weight:700;color:#f59e0b">{{ $stats['pool'] }}</div>
                        <div style="font-size:.6875rem;color:var(--text-muted);margin-top:.125rem">In Pool</div>
                    </div>
                    <div style="padding:.875rem;background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.15);border-radius:.625rem;text-align:center">
                        <div style="font-size:1.5rem;font-weight:700;color:#3b82f6">{{ $stats['claimed'] }}</div>
                        <div style="font-size:.6875rem;color:var(--text-muted);margin-top:.125rem">Claimed</div>
                    </div>
                </div>
                <div style="font-size:.8125rem;display:flex;justify-content:space-between;color:var(--text-muted)">
                    <span>Closed today</span>
                    <span style="color:var(--text-secondary);font-weight:500">{{ $stats['closed_today'] }}</span>
                </div>
                <div style="font-size:.8125rem;display:flex;justify-content:space-between;color:var(--text-muted)">
                    <span>Avg. response time</span>
                    <span style="color:var(--text-secondary);font-weight:500">{{ $stats['avg_response'] ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Linked Instances --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Linked Instances</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.625rem">
                @forelse($linkedInstances as $instance)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.625rem .75rem;background:var(--page-bg);border-radius:.5rem">
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <span class="status-dot {{ $instance->statusColor }}" style="width:.5rem;height:.5rem"></span>
                        <span style="font-size:.8125rem;font-weight:500">{{ $instance->name }}</span>
                    </div>
                    <a href="{{ route('admin.instances.edit', $instance) }}" style="color:var(--text-muted);font-size:.75rem;text-decoration:none">Edit</a>
                </div>
                @empty
                <div style="font-size:.8125rem;color:var(--text-muted);text-align:center;padding:.5rem 0">
                    No instances linked to this team.
                    <a href="{{ route('admin.instances.index') }}" style="color:var(--brand);display:block;margin-top:.25rem">Manage instances</a>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Quick Members Summary --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">Current Members</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.5rem">
                @forelse($team->users as $member)
                <div style="display:flex;align-items:center;gap:.625rem">
                    <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}"
                         style="width:1.75rem;height:1.75rem;border-radius:50%;object-fit:cover;flex-shrink:0">
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.8125rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $member->name }}</div>
                    </div>
                    <span class="badge {{ $member->role === 'supervisor' ? 'badge-blue' : 'badge-green' }}" style="font-size:.6rem">
                        {{ ucfirst($member->role) }}
                    </span>
                </div>
                @empty
                <div style="font-size:.8125rem;color:var(--text-muted);text-align:center;padding:.5rem 0">No members yet</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
function teamForm() {
    return {
        search: '',
        selected: @json(old('members', $team->users->pluck('id')->toArray())),
        allIds: @json($agents->pluck('id')),
        selectAll() { this.selected = [...this.allIds]; }
    }
}
</script>
@endsection
