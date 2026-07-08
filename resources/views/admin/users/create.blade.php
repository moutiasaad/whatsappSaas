@extends('layouts.admin')

@section('title', __('ui.user_form_page.create_title'))

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.user_form_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.user_form_page.title') }}</span>
@endsection

@section('content')
<div x-data="userCreateModals()" @keydown.escape.window="escHandler($event)">
<form action="{{ route('admin.users.store') }}" method="POST" data-loading>
    @csrf

    <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">
        <div class="card" style="overflow:visible;">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.user_form_page.account_information') }}</div>
                    <div class="card-subtitle">{{ __('ui.user_form_page.account_information_hint') }}</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="name">{{ __('ui.user_form_page.full_name') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') error @enderror">
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">{{ __('ui.user_form_page.email_address') }}</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') error @enderror">
                        @error('email') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">{{ __('ui.user_form_page.role') }}</label>
                    <select id="role" name="role" class="form-control @error('role') error @enderror">
                        <option value="">{{ __('ui.user_form_page.select_role') }}</option>
                        <option value="agent" {{ old('role') === 'agent' ? 'selected' : '' }}>{{ __('ui.roles.agent') }}</option>
                        <option value="supervisor" {{ old('role') === 'supervisor' ? 'selected' : '' }}>{{ __('ui.roles.supervisor') }}</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>{{ __('ui.roles.admin') }}</option>
                    </select>
                    @error('role') <div class="form-error">{{ $message }}</div> @enderror

                    <div style="margin-top:.625rem;display:flex;flex-direction:column;gap:.375rem">
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:flex-start;gap:.375rem">
                            <span style="font-weight:600;color:var(--text-secondary);min-width:70px">{{ __('ui.roles.agent') }}</span>
                            {{ __('ui.user_form_page.agent_desc') }}
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:flex-start;gap:.375rem">
                            <span style="font-weight:600;color:var(--text-secondary);min-width:70px">{{ __('ui.roles.supervisor') }}</span>
                            {{ __('ui.user_form_page.supervisor_desc') }}
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:flex-start;gap:.375rem">
                            <span style="font-weight:600;color:var(--text-secondary);min-width:70px">{{ __('ui.roles.admin') }}</span>
                            {{ __('ui.user_form_page.admin_desc') }}
                        </div>
                    </div>
                </div>

                @if(auth()->user()->isSuperAdmin())
                <div class="form-group" id="tenantField" style="{{ old('role') === 'admin' ? '' : 'display:none;' }};overflow:visible;">
                    <label class="form-label" for="tenant_id">{{ __('ui.user_form_page.tenant') }}</label>
                    <select id="tenant_id" name="tenant_id" class="form-control @error('tenant_id') error @enderror">
                        <option value="">{{ __('ui.user_form_page.select_tenant') }}</option>
                        @foreach($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((string) old('tenant_id') === (string) $tenant->id)>
                                {{ $tenant->name }} ({{ $tenant->slug }})
                            </option>
                        @endforeach
                    </select>
                    @error('tenant_id') <div class="form-error">{{ $message }}</div> @enderror
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.375rem">{{ __('ui.user_form_page.tenant_hint') }}</div>
                </div>
                @endif

                <div class="form-group" style="overflow:visible">
                    <label class="form-label" for="teams">{{ __('ui.user_form_page.assign_to_teams') }}</label>
                    <div style="display:flex;gap:.5rem;align-items:flex-start">
                        <div style="flex:1;min-width:0">
                            <select id="teams" name="teams[]" multiple
                                    data-no-ss
                                    data-user-teams-ss
                                    class="form-control @error('teams') error @enderror @error('teams.*') error @enderror"
                                    style="display:none;">
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}" @selected(in_array($team->id, old('teams', [])))>
                                        {{ $team->name }} @if($team->description) - {{ $team->description }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" @click="openTeamModal()"
                                class="btn btn-outline btn-sm"
                                style="flex-shrink:0;height:38px;padding:0 .65rem;font-size:1.1rem;line-height:1"
                                title="{{ __('ui.user_form_page.create_team_button_title') }}">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                    @error('teams') <div class="form-error">{{ $message }}</div> @enderror
                    @error('teams.*') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint" x-show="!hasTeams">
                        {{ __('ui.user_form_page.no_teams_created') }} — <a href="#" @click.prevent="openTeamModal()" style="color:var(--brand);font-weight:600">{{ __('ui.user_form_page.create_team_first') }}</a>
                    </div>
                    <div class="form-hint" x-show="hasTeams">{{ __('ui.user_form_page.assign_to_teams_hint') }}</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        {{ __('ui.user_form_page.temporary_password') }}
                        <span style="font-size:.75rem;font-weight:400;color:var(--text-muted)">{{ __('ui.user_form_page.temporary_password_hint') }}</span>
                    </label>
                    <input type="password" id="password" name="password" placeholder="{{ __('ui.user_form_page.password_placeholder') }}" class="form-control @error('password') error @enderror">
                    @error('password') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline">{{ __('ui.user_form_page.cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('ui.user_form_page.send_invitation') }}</button>
    </div>
</form>

{{-- ══════════════════════════════════════════
     Modal 1: Create Team
══════════════════════════════════════════ --}}
<div x-show="teamModal && !userModal" x-cloak
     class="modal-overlay show" style="z-index:1000"
     @click.self="teamModal=false">
    <div class="modal-box" style="max-width:500px;text-align:left;padding:1.5rem;overflow:visible" @click.stop>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin:0">
                <i class="ri-team-line" style="color:var(--brand);margin-right:.375rem"></i>
                {{ __('ui.user_form_page.team_modal_title') }}
            </h3>
            <button type="button" @click="teamModal=false" class="btn btn-ghost btn-sm" style="padding:.25rem .5rem">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem">

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">{{ __('ui.user_form_page.team_name') }} <span style="color:var(--brand)">*</span></label>
                <input type="text" x-model="teamForm.name"
                       :class="{'error': teamErrors.name}"
                       class="form-control"
                       placeholder="{{ __('ui.user_form_page.team_name_placeholder') }}"
                       @keydown.enter.prevent>
                <div x-show="teamErrors.name" x-text="teamErrors.name?.[0]" class="form-error"></div>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">{{ __('ui.user_form_page.team_description') }}</label>
                <input type="text" x-model="teamForm.description"
                       :class="{'error': teamErrors.description}"
                       class="form-control"
                       placeholder="{{ __('ui.user_form_page.team_description_placeholder') }}">
                <div x-show="teamErrors.description" x-text="teamErrors.description?.[0]" class="form-error"></div>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                    <label class="form-label" style="margin-bottom:0">{{ __('ui.user_form_page.team_members') }}</label>
                    <button type="button" @click="openUserModal()"
                            class="btn btn-outline btn-sm"
                            style="height:26px;padding:0 .625rem;font-size:.75rem">
                        <i class="ri-add-line"></i> {{ __('ui.user_form_page.create_agent') }}
                    </button>
                </div>

                <div class="ss-wrap" :class="{'open': membersOpen, 'open-up': true}"
                     @click.outside="membersOpen=false">
                    <input type="text" class="ss-input" readonly
                           :value="membersDisplayText"
                           :placeholder="agents.length ? @js(__('ui.user_form_page.team_members_placeholder')) : @js(__('ui.user_form_page.no_agents_placeholder'))"
                           @click.stop="membersOpen = !membersOpen; membersSearch = ''">
                    <i class="ri-arrow-down-s-line ss-chevron"></i>
                    <div class="ss-dropdown" @click.stop>
                        <div class="ss-search-row">
                            <div class="ss-search-inner">
                                <i class="ri-search-line"></i>
                                <input type="text" x-model="membersSearch" placeholder="{{ __('ui.team_form_page.filter') }}" autocomplete="off">
                            </div>
                        </div>
                        <div class="ss-list">
                            <template x-for="agent in filteredAgents" :key="agent.id">
                                <div class="ss-item"
                                     :class="{'ss-selected': teamForm.members.includes(agent.id)}"
                                     @mousedown.prevent="toggleMember(agent.id)"
                                     :data-value="agent.id">
                                    <span x-text="agent.name + ' (' + agent.role + ')'"></span>
                                </div>
                            </template>
                            <div class="ss-empty"
                                 x-show="filteredAgents.length === 0"
                                 x-text="membersSearch ? @js(__('ui.select_no_results')) : @js(__('ui.user_form_page.no_agents_available'))">
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="teamForm.members.length"
                     x-text="teamForm.members.length + ' ' + @js(__('ui.selected_items'))"
                     style="font-size:.75rem;color:var(--text-muted);margin-top:.375rem"></div>
            </div>

            <div x-show="teamError" x-text="teamError"
                 style="font-size:.8125rem;color:#ef4444;background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.625rem .875rem"></div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.5rem">
            <button type="button" @click="teamModal=false" class="btn btn-outline">{{ __('ui.cancel') }}</button>
            <button type="button" @click="createTeam()"
                    :disabled="teamSaving || !teamForm.name.trim()"
                    class="btn btn-primary">
                <span x-show="!teamSaving"><i class="ri-check-line"></i> {{ __('ui.user_form_page.create_team_submit') }}</span>
                <span x-show="teamSaving"><span class="btn-spinner"></span> {{ __('ui.user_form_page.creating') }}</span>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════
     Modal 2: Create Agent
══════════════════════════════════════════ --}}
<div x-show="userModal" x-cloak
     class="modal-overlay show" style="z-index:1100"
     @click.self="userModal=false">
    <div class="modal-box" style="max-width:440px;text-align:left;padding:1.5rem" @click.stop>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin:0">
                <i class="ri-user-add-line" style="color:var(--brand);margin-right:.375rem"></i>
                {{ __('ui.user_form_page.user_modal_title') }}
            </h3>
            <button type="button" @click="userModal=false" class="btn btn-ghost btn-sm" style="padding:.25rem .5rem">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem">

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">{{ __('ui.user_form_page.full_name') }} <span style="color:var(--brand)">*</span></label>
                <input type="text" x-model="userForm.name"
                       :class="{'error': userErrors.name}"
                       class="form-control"
                       placeholder="{{ __('ui.user_form_page.full_name_placeholder') }}"
                       @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.name" x-text="userErrors.name?.[0]" class="form-error"></div>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">{{ __('ui.user_form_page.email_address') }} <span style="color:var(--brand)">*</span></label>
                <input type="email" x-model="userForm.email"
                       :class="{'error': userErrors.email}"
                       class="form-control"
                       placeholder="email@example.com"
                       @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.email" x-text="userErrors.email?.[0]" class="form-error"></div>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">
                    {{ __('ui.user_form_page.temporary_password') }}
                    <span style="font-weight:400;color:var(--text-muted)">{{ __('ui.user_form_page.temporary_password_hint') }}</span>
                </label>
                <input type="password" x-model="userForm.password"
                       :class="{'error': userErrors.password}"
                       class="form-control"
                       placeholder="{{ __('ui.user_form_page.password_placeholder') }}"
                       @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.password" x-text="userErrors.password?.[0]" class="form-error"></div>
            </div>

            <div x-show="userError" x-text="userError"
                 style="font-size:.8125rem;color:#ef4444;background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.625rem .875rem"></div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.5rem">
            <button type="button" @click="userModal=false" class="btn btn-outline">{{ __('ui.cancel') }}</button>
            <button type="button" @click="createUser()"
                    :disabled="userSaving || !userForm.name.trim() || !userForm.email.trim()"
                    class="btn btn-primary">
                <span x-show="!userSaving"><i class="ri-check-line"></i> {{ __('ui.user_form_page.create_agent_submit') }}</span>
                <span x-show="userSaving"><span class="btn-spinner"></span> {{ __('ui.user_form_page.creating') }}</span>
            </button>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}

<script>
function initMultiSelect(widgetSelector, placeholder, filterPlaceholder) {
    const select = document.querySelector(widgetSelector);
    if (!select) return;

    // Make the call idempotent: drop a previously-built widget before rebuilding.
    const existingWrap = select.parentNode.querySelector(':scope > .ss-wrap[data-for="' + widgetSelector + '"]');
    if (existingWrap) existingWrap.remove();

    const wrap = document.createElement('div');
    wrap.className = 'ss-wrap';
    wrap.dataset.for = widgetSelector;
    if (select.classList.contains('error')) wrap.classList.add('error');

    const displayInput = document.createElement('input');
    displayInput.type = 'text';
    displayInput.className = 'ss-input';
    displayInput.readOnly = true;
    displayInput.placeholder = placeholder;

    const chevron = document.createElement('i');
    chevron.className = 'ri-arrow-down-s-line ss-chevron';

    const dropdown = document.createElement('div');
    dropdown.className = 'ss-dropdown';

    const searchRow = document.createElement('div');
    searchRow.className = 'ss-search-row';
    searchRow.innerHTML = '<div class="ss-search-inner"><i class="ri-search-line"></i><input type="text" placeholder="' + filterPlaceholder.replace(/"/g, '&quot;') + '" autocomplete="off"></div>';

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
        displayInput.value = selected.length === 1 ? selected[0].textContent.trim() : selected.length + ' ' + @json(__('ui.selected_items'));
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
        const estimatedHeight = 220;
        if (spaceBelow < estimatedHeight && spaceAbove > spaceBelow) {
            wrap.classList.add('open-up');
        }
        if (list) {
            const room = wrap.classList.contains('open-up') ? spaceAbove : spaceBelow;
            list.style.maxHeight = Math.max(100, Math.min(220, room - searchRowHeight)) + 'px';
        }
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
        }
    });

    filterInput.addEventListener('input', function () {
        renderItems(filterInput.value);
        syncDropdownPosition();
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) closeDropdown();
    });

    window.addEventListener('resize', syncDropdownPosition);
    window.addEventListener('scroll', syncDropdownPosition, true);

    updateDisplay();
    renderItems('');
}

function rebuildTeamsMultiSelect() {
    initMultiSelect(
        'select[data-user-teams-ss]',
        @json(__('ui.team_form_page.select')),
        @json(__('ui.team_form_page.filter'))
    );
}
window.rebuildTeamsMultiSelect = rebuildTeamsMultiSelect;

document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const tenantField = document.getElementById('tenantField');

    function syncTenantField() {
        if (!roleSelect || !tenantField) return;
        tenantField.style.display = roleSelect.value === 'admin' ? '' : 'none';
    }

    roleSelect?.addEventListener('change', syncTenantField);
    syncTenantField();

    rebuildTeamsMultiSelect();
});

function userCreateModals() {
    return {
        teamModal:     false,
        userModal:     false,
        teamSaving:    false,
        userSaving:    false,
        teamError:     '',
        userError:     '',
        teamErrors:    {},
        userErrors:    {},
        agents:        @json($agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'role' => $a->role])),
        teamForm:      { name: '', description: '', members: [] },
        userForm:      { name: '', email: '', password: '' },
        membersOpen:   false,
        membersSearch: '',
        hasTeams:      {{ $teams->isEmpty() ? 'false' : 'true' }},

        get membersDisplayText() {
            if (!this.teamForm.members.length) return '';
            const sel = this.agents.filter(a => this.teamForm.members.includes(a.id));
            if (sel.length === 1) return sel[0].name + ' (' + sel[0].role + ')';
            return sel.length + ' ' + @json(__('ui.selected_items'));
        },

        get filteredAgents() {
            const q = this.membersSearch.trim().toLowerCase();
            if (!q) return this.agents;
            return this.agents.filter(a => (a.name + ' ' + a.role).toLowerCase().includes(q));
        },

        toggleMember(id) {
            const idx = this.teamForm.members.indexOf(id);
            if (idx >= 0) this.teamForm.members.splice(idx, 1);
            else          this.teamForm.members.push(id);
        },

        openTeamModal() {
            this.teamError     = '';
            this.teamErrors    = {};
            this.membersOpen   = false;
            this.membersSearch = '';
            this.teamForm      = { name: '', description: '', members: [] };
            this.teamModal     = true;
        },

        openUserModal() {
            this.userError  = '';
            this.userErrors = {};
            this.userForm   = { name: '', email: '', password: '' };
            this.userModal  = true;
        },

        escHandler(e) {
            if (this.membersOpen) { this.membersOpen = false; e.stopPropagation(); return; }
            if (this.userModal)   { this.userModal   = false; e.stopPropagation(); return; }
            if (this.teamModal)   { this.teamModal   = false; e.stopPropagation(); }
        },

        async createTeam() {
            if (!this.teamForm.name.trim()) return;
            this.teamError  = '';
            this.teamErrors = {};
            this.teamSaving = true;
            try {
                const res = await fetch(@json(route('admin.teams.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        name:        this.teamForm.name.trim(),
                        description: this.teamForm.description.trim() || null,
                        is_active:   1,
                        members:     this.teamForm.members,
                    }),
                });

                if (!res.ok) {
                    const err = await res.json();
                    if (res.status === 422) this.teamErrors = err.errors || {};
                    else                    this.teamError  = err.message || @json(__('ui.user_form_page.team_create_error'));
                    return;
                }

                const team = await res.json();

                // Inject into the user form's team select & auto-select, then rebuild the widget.
                const sel = document.getElementById('teams');
                const opt = document.createElement('option');
                opt.value    = team.id;
                opt.text     = team.name + (team.description ? ' - ' + team.description : '');
                opt.selected = true;
                sel.appendChild(opt);
                this.hasTeams = true;
                window.rebuildTeamsMultiSelect();

                this.teamModal = false;
                window.showToast?.('success', @json(__('ui.user_form_page.team_created_toast')).replace(':name', team.name));
            } catch (e) {
                this.teamError = @json(__('ui.user_form_page.team_create_network_error'));
            } finally {
                this.teamSaving = false;
            }
        },

        async createUser() {
            if (!this.userForm.name.trim() || !this.userForm.email.trim()) return;
            this.userError  = '';
            this.userErrors = {};
            this.userSaving = true;
            try {
                const res = await fetch(@json(route('admin.users.store')), {
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

                if (!res.ok) {
                    const err = await res.json();
                    if (res.status === 422) this.userErrors = err.errors || {};
                    else                    this.userError  = err.message || @json(__('ui.user_form_page.agent_create_error'));
                    return;
                }

                const user = await res.json();

                this.agents.push({ id: user.id, name: user.name, role: user.role });
                this.$nextTick(() => {
                    if (!this.teamForm.members.includes(user.id)) {
                        this.teamForm.members.push(user.id);
                    }
                });

                this.userModal = false;
                window.showToast?.('success', @json(__('ui.user_form_page.agent_created_toast')).replace(':name', user.name));
            } catch (e) {
                this.userError = @json(__('ui.user_form_page.agent_create_network_error'));
            } finally {
                this.userSaving = false;
            }
        },
    };
}
</script>
@endsection
