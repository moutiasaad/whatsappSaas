@extends('layouts.admin')

@section('title', __('ui.team_form_page.create_title'))

@section('breadcrumb')
    @php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
    <a href="{{ route($panelPrefix . '.teams.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.team_form_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.team_form_page.title') }}</span>
@endsection

@section('content')
@php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
<style>
    .team-create-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        align-items: start;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 1024px) {
        .team-create-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">{{ __('ui.team_form_page.page_title') }}</h1>
        <p class="page-subtitle">{{ __('ui.team_form_page.subtitle') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route($panelPrefix . '.teams.index') }}" class="btn btn-outline">
                <i class="ri-arrow-left-line"></i> {{ __('ui.team_form_page.back') }}
        </a>
    </div>
</div>

<div x-data="teamQuickCreate()">
    <form action="{{ route($panelPrefix . '.teams.store') }}" method="POST" data-loading>
        @csrf

        <div class="team-create-grid">
            <div class="card" style="overflow:visible;">
                <div class="card-header">
                    <div>
                        <div class="card-title">{{ __('ui.team_form_page.team_details') }}</div>
                        <div class="card-subtitle">{{ __('ui.team_form_page.team_details_hint') }}</div>
                    </div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">
                    <div class="form-group">
                        <label class="form-label" for="name">{{ __('ui.team_form_page.team_name') }}</label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name') }}"
                               placeholder="{{ __('ui.team_form_page.name_placeholder') }}"
                               class="form-control @error('name') error @enderror">
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">{{ __('ui.team_form_page.description') }}</label>
                        <input type="text" id="description" name="description"
                               value="{{ old('description') }}"
                               placeholder="{{ __('ui.team_form_page.description_placeholder') }}"
                               class="form-control @error('description') error @enderror">
                        @error('description') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                            <label class="form-label" for="members" style="margin-bottom:0">{{ __('ui.team_form_page.team_members') }}</label>
                            @if(auth()->user()->hasAnyRole(['admin', 'super_admin']))
                            <button type="button" @click="openUserModal()"
                                    class="btn btn-outline btn-sm"
                                    style="height:26px;padding:0 .5rem;font-size:.75rem">
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
                                <option value="{{ $agent->id }}" @selected(in_array($agent->id, old('members', [])))>
                                    {{ $agent->name }} ({{ ucfirst($agent->role) }}) - {{ $agent->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('members') <div class="form-error">{{ $message }}</div> @enderror
                        @error('members.*') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">{{ __('ui.team_form_page.members_hint') }}</div>
                    </div>

                    <div style="display:flex;align-items:center;gap:.75rem">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', '1') ? 'checked' : '' }}>
                            <span class="toggle-text">{{ __('ui.team_form_page.active') }}</span>
                        </label>
                        <span style="font-size:.8125rem;color:var(--text-muted)">{{ __('ui.team_form_page.inactive_hint') }}</span>
                    </div>

                    <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:.75rem;padding:1rem;margin-top:.25rem">
                        <div style="display:flex;align-items:flex-start;gap:.625rem">
                            <svg style="color:var(--brand);flex-shrink:0;margin-top:.1rem" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div style="font-size:.8125rem;color:var(--text-secondary)">
                            {{ __('ui.team_form_page.pool_hint') }}
                                {{ __('ui.team_form_page.pool_hint_detail') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem">
            <a href="{{ route($panelPrefix . '.teams.index') }}" class="btn btn-outline">
                <i class="ri-close-line"></i> {{ __('ui.team_form_page.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="ri-check-line"></i> {{ __('ui.team_form_page.create_team') }}
            </button>
        </div>
    </form>

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

    function updateDisplay() {
        const selected = Array.from(select.selectedOptions);
        if (!selected.length) {
            displayInput.value = '';
            return;
        }
        if (selected.length === 1) {
            displayInput.value = selected[0].textContent.trim();
            return;
        }
        displayInput.value = selected.length + ' selected';
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

    function openDropdown() {
        wrap.classList.add('open');
        renderItems('');
        filterInput.value = '';
        filterInput.focus();
    }

    function closeDropdown() {
        wrap.classList.remove('open');
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
    item.className       = 'ss-item ss-selected';
    item.dataset.value   = user.id;
    item.dataset.label   = label;
    item.textContent     = label;
    list.appendChild(item);

    const sel = Array.from(select.selectedOptions);
    displayInput.value = sel.length === 1 ? sel[0].textContent.trim() : sel.length + ' selected';
}

function ucFirstTeam(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}
</script>
@endsection
