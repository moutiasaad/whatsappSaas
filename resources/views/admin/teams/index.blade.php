@extends('layouts.admin')

@section('title', 'Teams')

@section('breadcrumb')
    <span>Teams</span>
@endsection

@section('content')

    {{-- Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Teams</div>
            <div class="page-subtitle">Organize agents into teams for conversation routing</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('admin.teams.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> New Team
            </a>
        </div>
    </div>

    @if($teams->isEmpty())
        <div class="card">
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <h4>No teams yet</h4>
                <p>Create your first team to organize agents and route conversations</p>
                <a href="{{ route('admin.teams.create') }}" class="btn btn-primary">Create Team</a>
            </div>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:1rem">
            @foreach($teams as $team)
            <div class="card" style="transition:box-shadow .2s" onmouseenter="this.style.boxShadow='0 4px 20px rgba(16,185,129,.12)'" onmouseleave="this.style.boxShadow=''">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem">
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <div style="width:2.5rem;height:2.5rem;border-radius:.75rem;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(5,150,105,.25));display:flex;align-items:center;justify-content:center;color:var(--brand)">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <div>
                            <div style="font-weight:600">{{ $team->name }}</div>
                            @if($team->description)
                                <div style="font-size:.75rem;color:var(--text-muted)">{{ $team->description }}</div>
                            @endif
                        </div>
                    </div>
                    <span class="badge {{ $team->is_active ? 'badge-green' : 'badge-gray' }}">
                        {{ $team->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                {{-- Member Avatars --}}
                <div style="margin-bottom:1rem">
                    <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.5rem">{{ $team->users->count() }} member(s)</div>
                    <div style="display:flex;align-items:center">
                        @foreach($team->users->take(6) as $member)
                        <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}"
                             title="{{ $member->name }}"
                             style="width:1.875rem;height:1.875rem;border-radius:50%;border:2px solid var(--card-bg);object-fit:cover;margin-left:{{ $loop->first ? '0' : '-0.5rem' }}">
                        @endforeach
                        @if($team->users->count() > 6)
                            <div style="width:1.875rem;height:1.875rem;border-radius:50%;background:var(--page-bg);border:2px solid var(--card-bg);display:flex;align-items:center;justify-content:center;font-size:.625rem;font-weight:600;color:var(--text-secondary);margin-left:-.5rem">
                                +{{ $team->users->count() - 6 }}
                            </div>
                        @endif
                        @if($team->users->isEmpty())
                            <span style="font-size:.8125rem;color:var(--text-muted)">No members</span>
                        @endif
                    </div>
                </div>

                {{-- Stats --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;padding:.875rem;background:var(--page-bg);border-radius:.625rem;margin-bottom:1rem">
                    <div style="text-align:center">
                        <div style="font-size:1.125rem;font-weight:700;color:var(--text-primary)">{{ $team->active_conversations_count ?? 0 }}</div>
                        <div style="font-size:.6875rem;color:var(--text-muted)">Active</div>
                    </div>
                    <div style="text-align:center">
                        <div style="font-size:1.125rem;font-weight:700;color:var(--text-primary)">{{ $team->pool_count ?? 0 }}</div>
                        <div style="font-size:.6875rem;color:var(--text-muted)">In Pool</div>
                    </div>
                </div>

                {{-- Actions --}}
                <div style="display:flex;gap:.5rem;padding-top:.75rem;border-top:1px solid var(--card-border)">
                    <a href="{{ route('admin.teams.edit', $team) }}" class="btn btn-outline btn-sm" style="flex:1">Manage</a>
                    <button onclick="confirmDelete('{{ route('admin.teams.destroy', $team) }}', { title: 'Delete {{ addslashes($team->name) }}?', message: 'Existing conversations will become unassigned.' })"
                            class="btn btn-ghost btn-icon" style="color:#ef4444">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    @endif

@endsection
