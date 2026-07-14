@extends('layouts.admin')

@section('title', __('ui.team_edit_page.title'))

@section('breadcrumb')
    @php
        $panelPrefix = auth()->user()->routeNamePrefix();
        $canDeleteTeam = auth()->user()->hasAnyRole(['admin', 'super_admin']);
        $canManageInstances = auth()->user()->hasAnyRole(['admin', 'super_admin']);
    @endphp
    <a href="{{ route($panelPrefix . '.teams.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.team_form_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $team->name }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $canDeleteTeam = auth()->user()->hasAnyRole(['admin', 'super_admin']);
    $canManageInstances = auth()->user()->hasAnyRole(['admin', 'super_admin']);
@endphp
<div x-data="teamQuickCreate()" style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start">

    {{-- Left: Edit Form --}}
    <div class="card" style="overflow:visible;">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.team_edit_page.page_title') }}</div>
                <div class="card-subtitle">{{ __('ui.team_edit_page.subtitle', ['name' => $team->name]) }}</div>
            </div>
            <span class="badge {{ $team->is_active ? 'badge-green' : 'badge-gray' }}">
                {{ $team->is_active ? __('ui.active') : __('ui.inactive') }}
            </span>
        </div>

        <form action="{{ route($panelPrefix . '.teams.update', $team) }}" method="POST" data-unsaved data-loading>
            @csrf
            @method('PUT')

            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.5rem">

                {{-- Basic Info --}}
                <div style="display:flex;flex-direction:column;gap:1.125rem">
                    <div class="form-group">
                        <label class="form-label" for="name">{{ __('ui.team_form_page.team_name') }}</label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $team->name) }}"
                               class="form-control @error('name') error @enderror">
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">{{ __('ui.team_form_page.description') }}</label>
                        <input type="text" id="description" name="description"
                               value="{{ old('description', $team->description) }}"
                               placeholder="{{ __('ui.team_form_page.description_placeholder') }}"
                               class="form-control @error('description') error @enderror">
                        @error('description') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div style="display:flex;align-items:center;gap:.75rem">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $team->is_active) ? 'checked' : '' }}>
                            <span class="toggle-text">{{ __('ui.active') }}</span>
                        </label>
                        <span style="font-size:.8125rem;color:var(--text-muted)">{{ __('ui.team_edit_page.inactive_hint') }}</span>
                    </div>
                </div>
                {{-- Member Picker --}}
                <div class="form-group" style="overflow:visible">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.875rem">
                        <div>
                            <label class="form-label" for="members" style="margin-bottom:.125rem">{{ __('ui.team_form_page.team_members') }}</label>
                            <div style="font-size:.8125rem;color:var(--text-muted)">
                                {{ __('ui.team_form_page.members_hint') }}
                            </div>
                        </div>
                        @if(auth()->user()->hasAnyRole(['admin', 'super_admin']))
                        <button type="button" @click="openUserModal()"
                                class="btn btn-outline btn-sm"
                                style="height:26px;padding:0 .5rem;font-size:.75rem;flex-shrink:0">
                            <i class="ri-add-line"></i> Créer un utilisateur
                        </button>
                        @endif
                    </div>

                    <select id="members" name="members[]" multiple
                            data-no-ss
                            data-members-ss
                            class="form-control @error('members') error @enderror @error('members.*') error @enderror"
                            style="display:none;">
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected(in_array($agent->id, old('members', $team->users->pluck('id')->toArray())))>
                                {{ $agent->name }} ({{ ucfirst($agent->role) }}) - {{ $agent->email }}
                            </option>
                        @endforeach
                    </select>
                    @error('members') <div class="form-error">{{ $message }}</div> @enderror
                    @error('members.*') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint">{{ __('ui.team_form_page.members_hint') }}</div>
                </div>
                {{-- {{ __('ui.team_edit_page.danger_zone') }} --}}
                @if($canDeleteTeam)
                    <div style="border:1px solid rgba(239,68,68,.2);border-radius:.75rem;padding:1rem">
                        <div style="font-size:.875rem;font-weight:600;color:#ef4444;margin-bottom:.375rem">{{ __('ui.team_edit_page.danger_zone') }}</div>
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
                            <div style="font-size:.8125rem;color:var(--text-muted)">
                                {{ __('ui.team_edit_page.delete_message') }}
                            </div>
                            <button type="button"
                                    onclick="confirmDelete('{{ route($panelPrefix . '.teams.destroy', $team) }}', { title: '{{ __('ui.team_edit_page.delete_prompt', ['name' => addslashes($team->name)]) }}', message: @json(__('ui.team_edit_page.delete_message')) })"
                                    class="btn btn-danger btn-sm">
                                {{ __('ui.team_edit_page.delete_team') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <div style="padding:1.25rem 1.5rem;border-top:1px solid var(--card-border);display:flex;justify-content:flex-end;gap:.5rem">
                <a href="{{ route($panelPrefix . '.teams.index') }}" class="btn btn-outline">{{ __('ui.team_edit_page.cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('ui.team_edit_page.save_changes') }}</button>
            </div>
        </form>
    </div>

    {{-- Right: Stats & Linked Instances --}}
    <div style="display:flex;flex-direction:column;gap:1rem">

        {{-- Live Stats --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">{{ __('ui.team_edit_page.current_load') }}</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.875rem">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <div style="padding:.875rem;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15);border-radius:.625rem;text-align:center">
                        <div style="font-size:1.5rem;font-weight:700;color:#f59e0b">{{ $stats['pool'] }}</div>
                        <div style="font-size:.6875rem;color:var(--text-muted);margin-top:.125rem">{{ __('ui.team_edit_page.in_pool') }}</div>
                    </div>
                    <div style="padding:.875rem;background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.15);border-radius:.625rem;text-align:center">
                        <div style="font-size:1.5rem;font-weight:700;color:#3b82f6">{{ $stats['claimed'] }}</div>
                        <div style="font-size:.6875rem;color:var(--text-muted);margin-top:.125rem">{{ __('ui.team_edit_page.claimed') }}</div>
                    </div>
                </div>
                <div style="font-size:.8125rem;display:flex;justify-content:space-between;color:var(--text-muted)">
                    <span>{{ __('ui.team_edit_page.closed_today') }}</span>
                    <span style="color:var(--text-secondary);font-weight:500">{{ $stats['closed_today'] }}</span>
                </div>
                <div style="font-size:.8125rem;display:flex;justify-content:space-between;color:var(--text-muted)">
                    <span>{{ __('ui.team_edit_page.avg_response_time') }}</span>
                    <span style="color:var(--text-secondary);font-weight:500">{{ $stats['avg_response'] ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Linked Instances --}}
        {{-- TSHLBOT-HIDE-WHATSAPP:begin — Linked WhatsApp Instances card on team edit page disabled. --}}
        {{--
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">{{ __('ui.team_edit_page.linked_instances') }}</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.625rem">
                @forelse($linkedInstances as $instance)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.625rem .75rem;background:var(--page-bg);border-radius:.5rem">
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <span class="status-dot {{ $instance->statusColor }}" style="width:.5rem;height:.5rem"></span>
                        <span style="font-size:.8125rem;font-weight:500">{{ $instance->name }}</span>
                    </div>
                    @if($canManageInstances)
                        <a href="{{ route($panelPrefix . '.instances.edit', $instance) }}" style="color:var(--text-muted);font-size:.75rem;text-decoration:none">{{ __('ui.edit') }}</a>
                    @endif
                </div>
                @empty
                <div style="font-size:.8125rem;color:var(--text-muted);text-align:center;padding:.5rem 0">
                    {{ __('ui.team_edit_page.no_linked_instances') }}
                    @if($canManageInstances)
                        <a href="{{ route($panelPrefix . '.instances.index') }}" style="color:var(--brand);display:block;margin-top:.25rem">{{ __('ui.team_edit_page.manage_instances') }}</a>
                    @endif
                </div>
                @endforelse
            </div>
        </div>
        --}}
        {{-- TSHLBOT-HIDE-WHATSAPP:end --}}

        {{-- Quick Members Summary --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">{{ __('ui.team_edit_page.current_members') }}</div>
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
                <div style="font-size:.8125rem;color:var(--text-muted);text-align:center;padding:.5rem 0">{{ __('ui.team_edit_page.no_members_yet') }}</div>
                @endforelse
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.querySelector('select[data-members-ss]');
    if (!select) return;

    const wrap = document.createElement('div');
    wrap.className = 'ss-wrap';
    if (select.classList.contains('error')) wrap.classList.add('error');

    const displayInput = document.createElement('input');
    displayInput.type = 'text';
    displayInput.className = 'ss-input';
    displayInput.readOnly = true;
    displayInput.placeholder = @json(__('ui.team_form_page.select'));

    const chevron = document.createElement('i');
    chevron.className = 'ri-arrow-down-s-line ss-chevron';

    const dropdown = document.createElement('div');
    dropdown.className = 'ss-dropdown';

    const searchRow = document.createElement('div');
    searchRow.className = 'ss-search-row';
    searchRow.innerHTML = '<div class="ss-search-inner"><i class="ri-search-line"></i><input type="text" placeholder="{{ __('ui.team_form_page.filter') }}" autocomplete="off"></div>';

    const list = document.createElement('div');
    list.className = 'ss-list';

    Array.from(select.options).forEach(function (opt) {
        const item = document.createElement('div');
        item.className = 'ss-item';
        item.dataset.value = opt.value;
        item.dataset.label = opt.textContent.trim();
        item.textContent = opt.textContent.trim();
        if (opt.selected) item.classList.add('ss-selected');
        list.appendChild(item);
    });

    dropdown.appendChild(searchRow);
    dropdown.appendChild(list);
    wrap.appendChild(displayInput);
    wrap.appendChild(chevron);
    wrap.appendChild(dropdown);
    select.parentNode.insertBefore(wrap, select.nextSibling);

    const filterInput = searchRow.querySelector('input');
    const searchRowHeight = 56;

    function updateDisplay() {
        const selected = Array.from(select.selectedOptions);
        if (!selected.length) {
            displayInput.value = '';
            return;
        }
        displayInput.value = selected.length === 1 ? selected[0].textContent.trim() : selected.length + ' selected';
    }

    function renderItems(q) {
        const lower = (q || '').toLowerCase();
        let visible = 0;
        list.querySelectorAll('.ss-item').forEach(function (el) {
            const matches = !lower || el.dataset.label.toLowerCase().includes(lower);
            el.style.display = matches ? '' : 'none';
            if (matches) visible++;
        });
        let emptyEl = list.querySelector('.ss-empty');
        if (!visible) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'ss-empty';
                emptyEl.textContent = @json(__('ui.select_no_results'));
                list.appendChild(emptyEl);
            }
            emptyEl.style.display = '';
        } else if (emptyEl) {
            emptyEl.style.display = 'none';
        }
    }

    function setSelectedClass(value, isSelected) {
        const item = list.querySelector('.ss-item[data-value="' + CSS.escape(value) + '"]');
        if (item) item.classList.toggle('ss-selected', isSelected);
    }

    function syncDropdownPosition() {
        wrap.classList.remove('open-up');

        const rect = wrap.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom - 16;
        const spaceAbove = rect.top - 16;
        const estimatedHeight = 56 + 120;
        const openUp = spaceBelow < estimatedHeight && spaceAbove > spaceBelow;
        wrap.classList.toggle('open-up', openUp);

        const room = openUp ? spaceAbove : spaceBelow;
        const listMax = Math.max(100, Math.min(160, room - searchRowHeight));
        list.style.maxHeight = listMax + 'px';
    }

    function openDropdown() {
        syncDropdownPosition();
        wrap.classList.add('open');
        renderItems('');
        filterInput.value = '';
        filterInput.focus();
        syncDropdownPosition();
    }

    function closeDropdown() {
        wrap.classList.remove('open');
        wrap.classList.remove('open-up');
        list.style.maxHeight = '160px';
    }

    list.addEventListener('mousedown', function (e) {
        const item = e.target.closest('.ss-item');
        if (!item) return;
        e.preventDefault();
        const value = item.dataset.value;
        const option = Array.from(select.options).find(function (o) { return o.value === value; });
        if (!option) return;
        option.selected = !option.selected;
        setSelectedClass(value, option.selected);
        updateDisplay();
        select.dispatchEvent(new Event('change', { bubbles: true }));
        wrap.classList.remove('error');
    });

    displayInput.addEventListener('click', function () {
        if (wrap.classList.contains('open')) closeDropdown();
        else openDropdown();
    });

    displayInput.addEventListener('keydown', function (e) {
        if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
            e.preventDefault();
            openDropdown();
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    filterInput.addEventListener('input', function () {
        renderItems(this.value.trim());
    });

    window.addEventListener('resize', function () {
        if (wrap.classList.contains('open')) syncDropdownPosition();
    });

    window.addEventListener('scroll', function () {
        if (wrap.classList.contains('open')) syncDropdownPosition();
    }, true);

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) closeDropdown();
    });

    updateDisplay();
});
</script>

@if(auth()->user()->hasAnyRole(['admin', 'super_admin']))
{{-- ── Create User Modal ── --}}
<div x-show="userModal" x-cloak class="modal-overlay show" style="z-index:1100" @click.self="userModal=false" @keydown.escape.window="userModal=false">
    <div class="modal-box" style="max-width:440px;text-align:left;padding:1.5rem" @click.stop>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin:0">
                <i class="ri-user-add-line" style="color:var(--brand);margin-right:.375rem"></i>
                Créer un utilisateur
            </h3>
            <button type="button" @click="userModal=false" class="btn btn-ghost btn-sm" style="padding:.25rem .5rem">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div style="display:flex;flex-direction:column;gap:1rem">
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Nom complet <span style="color:var(--brand)">*</span></label>
                <input type="text" x-model="userForm.name" :class="{'error': userErrors.name}" class="form-control" placeholder="Prénom Nom" @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.name" x-text="userErrors.name?.[0]" class="form-error"></div>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Email <span style="color:var(--brand)">*</span></label>
                <input type="email" x-model="userForm.email" :class="{'error': userErrors.email}" class="form-control" placeholder="email@example.com" @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.email" x-text="userErrors.email?.[0]" class="form-error"></div>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Mot de passe <span style="font-weight:400;color:var(--text-muted)">(auto-généré si vide)</span></label>
                <input type="password" x-model="userForm.password" :class="{'error': userErrors.password}" class="form-control" placeholder="••••••••" @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.password" x-text="userErrors.password?.[0]" class="form-error"></div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.5rem">
            <button type="button" @click="userModal=false" class="btn btn-outline">Annuler</button>
            <button type="button" @click="createUser()" :disabled="userSaving" class="btn btn-primary">
                <span x-show="!userSaving"><i class="ri-check-line"></i> Créer</span>
                <span x-show="userSaving"><span class="btn-spinner"></span> Création…</span>
            </button>
        </div>
    </div>
</div>
@endif
</div>

<script>
function teamQuickCreate() {
    return {
        userModal:  false,
        userSaving: false,
        userErrors: {},
        userForm:   { name: '', email: '', password: '' },

        openUserModal() {
            this.userErrors = {};
            this.userForm   = { name: '', email: '', password: '' };
            this.userModal  = true;
        },

        async createUser() {
            this.userErrors = {};
            this.userSaving = true;
            try {
                const res = await fetch(@json(route($panelPrefix . '.users.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        name:     this.userForm.name.trim(),
                        email:    this.userForm.email.trim(),
                        password: this.userForm.password || null,
                        role:     'agent',
                    }),
                });
                if (res.status === 422) {
                    const err = await res.json();
                    this.userErrors = err.errors || {};
                    return;
                }
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    this.userErrors = { name: [err.message || 'Erreur serveur.'] };
                    return;
                }
                const user = await res.json();
                injectUserIntoSS(user);
                this.userForm  = { name: '', email: '', password: '' };
                this.userModal = false;
                window.showToast?.('success', user.name + ' ajouté(e).');
            } catch (e) {
                this.userErrors = { name: ['Erreur réseau. Réessayez.'] };
            } finally {
                this.userSaving = false;
            }
        },
    };
}

function injectUserIntoSS(user) {
    const select = document.querySelector('select[data-members-ss]');
    if (!select) return;

    const label = user.name + ' (' + ucFirstTeam(user.role) + ') - ' + user.email;

    const option = document.createElement('option');
    option.value    = user.id;
    option.text     = label;
    option.selected = true;
    select.add(option);

    const wrap = select.nextElementSibling;
    if (!wrap || !wrap.classList.contains('ss-wrap')) return;

    const list         = wrap.querySelector('.ss-list');
    const displayInput = wrap.querySelector('.ss-input');
    if (!list || !displayInput) return;

    const emptyEl = list.querySelector('.ss-empty');
    if (emptyEl) emptyEl.style.display = 'none';

    const item = document.createElement('div');
    item.className     = 'ss-item ss-selected';
    item.dataset.value = user.id;
    item.dataset.label = label;
    item.textContent   = label;
    list.appendChild(item);

    const sel = Array.from(select.selectedOptions);
    displayInput.value = sel.length === 1 ? sel[0].textContent.trim() : sel.length + ' selected';
}

function ucFirstTeam(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}
</script>
@endsection
