@extends('layouts.admin')

@section('title', 'New Team')

@section('breadcrumb')
    <a href="{{ route('admin.teams.index') }}" style="color:var(--text-secondary);text-decoration:none">Teams</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>New Team</span>
@endsection

@section('content')
<div x-data="teamForm()">
    <form action="{{ route('admin.teams.store') }}" method="POST" data-loading>
        @csrf

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">

            {{-- Left: Basic Info --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Team Details</div>
                        <div class="card-subtitle">Name, description and status</div>
                    </div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                    <div class="form-group">
                        <label class="form-label" for="name">
                            Team Name <span style="color:#ef4444">*</span>
                        </label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name') }}"
                               placeholder="e.g. Technical Support, Sales, Billing"
                               class="form-control @error('name') error @enderror"
                               required>
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <input type="text" id="description" name="description"
                               value="{{ old('description') }}"
                               placeholder="Short description of this team's focus"
                               class="form-control @error('description') error @enderror">
                        @error('description') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div style="display:flex;align-items:center;gap:.75rem">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', '1') ? 'checked' : '' }}>
                            <span class="toggle-text">Active</span>
                        </label>
                        <span style="font-size:.8125rem;color:var(--text-muted)">Inactive teams won't receive new conversations</span>
                    </div>

                    {{-- Routing Info --}}
                    <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:.75rem;padding:1rem;margin-top:.25rem">
                        <div style="display:flex;align-items:flex-start;gap:.625rem">
                            <svg style="color:var(--brand);flex-shrink:0;margin-top:.1rem" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div style="font-size:.8125rem;color:var(--text-secondary)">
                                Conversations from WhatsApp instances assigned to this team will go to this team's pool.
                                All members can see and claim these conversations.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Member Picker --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Team Members</div>
                        <div class="card-subtitle">Select agents and supervisors to add</div>
                    </div>
                    <span x-text="`${selected.length} selected`"
                          style="font-size:.8125rem;color:var(--text-muted)"></span>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.875rem">

                    {{-- Search --}}
                    <div style="position:relative">
                        <svg style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" x-model="search"
                               placeholder="Filter by name…"
                               class="filter-input" style="padding-left:2.25rem;width:100%">
                    </div>

                    {{-- Agent List --}}
                    <div style="border:1px solid var(--card-border);border-radius:.75rem;overflow:hidden;max-height:380px;overflow-y:auto">
                        @forelse($agents as $agent)
                        <label style="display:flex;align-items:center;gap:.875rem;padding:.75rem 1rem;cursor:pointer;border-bottom:1px solid var(--card-border);transition:background .12s"
                               x-show="!search || '{{ strtolower($agent->name) }}'.includes(search.toLowerCase())"
                               :style="selected.includes({{ $agent->id }}) ? 'background:rgba(16,185,129,.06)' : ''"
                               onmouseenter="if(!this.querySelector('input').checked) this.style.background='var(--page-bg)'"
                               onmouseleave="this.style.background=this.querySelector('input').checked?'rgba(16,185,129,.06)':''">
                            <input type="checkbox" name="members[]" value="{{ $agent->id }}"
                                   x-model="selected"
                                   :value="{{ $agent->id }}"
                                   {{ in_array($agent->id, old('members', [])) ? 'checked' : '' }}
                                   style="accent-color:var(--brand);width:1rem;height:1rem;flex-shrink:0;cursor:pointer">
                            <img src="{{ $agent->avatar_url }}" alt="{{ $agent->name }}"
                                 style="width:2rem;height:2rem;border-radius:50%;object-fit:cover;flex-shrink:0">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:500;font-size:.875rem">{{ $agent->name }}</div>
                                <div style="font-size:.75rem;color:var(--text-muted)">{{ $agent->email }}</div>
                            </div>
                            <span class="badge {{ $agent->role === 'supervisor' ? 'badge-blue' : 'badge-brand' }}" style="font-size:.6875rem">
                                {{ ucfirst($agent->role) }}
                            </span>
                        </label>
                        @empty
                        <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:.875rem">
                            No agents available. <a href="{{ route('admin.users.create') }}" style="color:var(--brand)">Invite one</a>
                        </div>
                        @endforelse
                    </div>

                    @if($agents->isNotEmpty())
                    <div style="display:flex;gap:.5rem">
                        <button type="button" @click="selectAll()" class="btn btn-ghost btn-sm" style="font-size:.75rem">Select all</button>
                        <button type="button" @click="selected = []" class="btn btn-ghost btn-sm" style="font-size:.75rem">Clear</button>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div style="display:flex;justify-content:flex-end;gap:.5rem">
            <a href="{{ route('admin.teams.index') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Team</button>
        </div>
    </form>
</div>

<script>
function teamForm() {
    return {
        search: '',
        selected: @json(old('members', [])),
        allIds: @json($agents->pluck('id')),
        selectAll() { this.selected = [...this.allIds]; }
    }
}
</script>
@endsection
