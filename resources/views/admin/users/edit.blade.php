@extends('layouts.admin')

@section('title', __('ui.user_form_page.edit_title'))

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.user_form_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $user->name }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 280px;gap:1.5rem;align-items:start">

    {{-- Left: {{ __('ui.user_form_page.edit') }} Form --}}
    <div class="card" style="overflow:visible;">
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
                        <label class="form-label" for="name">{{ __('ui.user_form_page.full_name') }}</label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name', $user->name) }}"
                               class="form-control @error('name') error @enderror">
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">{{ __('ui.user_form_page.email_address') }}</label>
                        <input type="email" id="email"
                               value="{{ $user->email }}"
                               class="form-control"
                               style="background:var(--page-bg);color:var(--text-muted);cursor:not-allowed"
                               disabled>
                        <div class="form-hint">{{ __('ui.user_form_page.email_hint') }}</div>
                    </div>
                </div>

                {{-- Role --}}
                <div class="form-group">
                    <label class="form-label" for="role">{{ __('ui.user_form_page.role') }}</label>
                    <select id="role" name="role"
                            class="form-control @error('role') error @enderror"
                            x-data x-model="$el.value"
                            @change="updateRoleHint($event.target.value)"
                            >
                        <option value="agent"      {{ old('role', $user->role) === 'agent'      ? 'selected' : '' }}>{{ __('ui.roles.agent') }}</option>
                        <option value="supervisor" {{ old('role', $user->role) === 'supervisor' ? 'selected' : '' }}>{{ __('ui.roles.supervisor') }}</option>
                        <option value="admin"      {{ old('role', $user->role) === 'admin'      ? 'selected' : '' }}>{{ __('ui.roles.admin') }}</option>
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
                        <span style="font-size:.8125rem;color:var(--text-muted)">{{ __('ui.user_form_page.inactive_hint') }}</span>
                    </div>
                </div>

                {{-- Teams --}}
                <div class="form-group" style="overflow:visible">
                    <label class="form-label" for="teams">{{ __('ui.user_form_page.team_membership') }}</label>
                    @if($teams->isEmpty())
                        <div style="font-size:.8125rem;color:var(--text-muted);padding:.5rem 0">{{ __('ui.user_form_page.no_teams_created') }}</div>
                    @else
                        <select id="teams" name="teams[]" multiple
                                data-no-ss
                                data-user-teams-ss
                                class="form-control @error('teams') error @enderror @error('teams.*') error @enderror"
                                style="display:none;">
                            @foreach($teams as $team)
                                @php $checked = in_array($team->id, old('teams', $user->teams->pluck('id')->toArray())); @endphp
                                <option value="{{ $team->id }}" @selected($checked)>
                                    {{ $team->name }} @if($team->description) - {{ $team->description }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('teams') <div class="form-error">{{ $message }}</div> @enderror
                        @error('teams.*') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">{{ __('ui.user_form_page.assign_to_teams_hint') }}</div>
                    @endif
                </div>

                {{-- Reset {{ __('ui.user_form_page.temporary_password') }} --}}
                <div class="form-group" x-data="{ show: false }">
                    <label class="form-label">
                        {{ __('ui.user_form_page.temporary_password') }}
                        <button type="button" @click="show = !show"
                                style="font-size:.75rem;font-weight:400;color:var(--brand);background:none;border:none;cursor:pointer;padding:0;margin-left:.375rem"
                                x-text="show ? '{{ __('ui.user_form_page.cancel') }}' : '{{ __('ui.user_form_page.reset_password') }}'"></button>
                    </label>
                    <div x-show="show" x-transition style="display:flex;flex-direction:column;gap:.75rem;margin-top:.375rem">
                        <div style="position:relative">
                            <input type="password" id="password" name="password"
                                   placeholder="{{ __('ui.user_form_page.new_password_placeholder') }}"
                                   class="form-control @error('password') error @enderror"
                                   style="padding-right:2.75rem">
                            <button type="button"
                                    onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password'"
                                    style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:.25rem">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        @error('password') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">{{ __('ui.user_form_page.password_hint') }}</div>
                    </div>
                    <div x-show="!show" style="font-size:.8125rem;color:var(--text-muted);margin-top:.25rem">
                        ••••••••  <span style="font-size:.75rem">(hidden)</span>
                    </div>
                </div>

                {{-- {{ __('ui.user_form_page.danger_zone') }} --}}
                @if(auth()->id() !== $user->id)
                <div style="border:1px solid rgba(239,68,68,.2);border-radius:.75rem;padding:1rem">
                    <div style="font-size:.875rem;font-weight:600;color:#ef4444;margin-bottom:.375rem">{{ __('ui.user_form_page.danger_zone') }}</div>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
                        <div style="font-size:.8125rem;color:var(--text-muted)">
                            {{ __('ui.user_form_page.delete_message') }}
                        </div>
                        <button type="button"
                                onclick="confirmDelete('{{ route('admin.users.destroy', $user) }}', { title: '{{ __('ui.user_form_page.delete_prompt', ['name' => addslashes($user->name)]) }}', message: '{{ __('ui.user_form_page.delete_warning') }}' })"
                                class="btn btn-danger btn-sm">
                            {{ __('ui.user_form_page.delete_user') }}
                        </button>
                    </div>
                </div>
                @endif
            </div>

            <div style="padding:1.25rem 1.5rem;border-top:1px solid var(--card-border);display:flex;justify-content:flex-end;gap:.5rem">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">{{ __('ui.user_form_page.cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('ui.user_form_page.save_changes') }}</button>
            </div>
        </form>
    </div>

    {{-- Right: Activity Sidebar --}}
    <div style="display:flex;flex-direction:column;gap:1rem">

        {{-- Quick Stats --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">{{ __('ui.user_form_page.activity') }}</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.625rem;font-size:.8125rem">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">{{ __('ui.user_form_page.joined') }}</span>
                    <span>{{ $user->created_at->format('M j, Y') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">{{ __('ui.user_form_page.last_login') }}</span>
                    <span>{{ $user->last_login_at?->diffForHumans() ?? __('ui.users_page.never') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">{{ __('ui.user_form_page.active_conversations') }}</span>
                    <span>{{ $stats['active'] }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">{{ __('ui.user_form_page.closed_total') }}</span>
                    <span>{{ $stats['closed_total'] }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">{{ __('ui.user_form_page.closed_month') }}</span>
                    <span>{{ $stats['closed_month'] }}</span>
                </div>
            </div>
        </div>

        {{-- Current Teams --}}
        <div class="card">
            <div class="card-header" style="padding-bottom:.75rem">
                <div class="card-title">{{ __('ui.user_form_page.teams') }}</div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;flex-direction:column;gap:.5rem">
                @forelse($user->teams as $team)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .625rem;background:var(--page-bg);border-radius:.5rem">
                    <span style="font-size:.875rem;font-weight:500">{{ $team->name }}</span>
                    <a href="{{ route('admin.teams.edit', $team) }}"
                       style="font-size:.75rem;color:var(--text-muted);text-decoration:none"
                       onmouseenter="this.style.color='var(--brand)'" onmouseleave="this.style.color='var(--text-muted)'">
                        {{ __('ui.user_form_page.edit') }}
                    </a>
                </div>
                @empty
                <div style="font-size:.8125rem;color:var(--text-muted);text-align:center;padding:.5rem 0">{{ __('ui.user_form_page.no_team_membership') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Impersonate --}}
        @if(auth()->id() !== $user->id)
        @can('impersonate', $user)
        <div class="card">
            <div style="padding:1.25rem">
                <div style="font-size:.875rem;font-weight:600;color:var(--text-primary);margin-bottom:.375rem">{{ __('ui.user_form_page.impersonate') }}</div>
                <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.875rem">
                    {{ __('ui.user_form_page.impersonate_desc') }}
                </div>
                <a href="{{ route('admin.users.impersonate', $user) }}"
                   onclick="event.preventDefault(); confirmSend({ title: @js(__('ui.user_form_page.impersonate')), message: @js(__('ui.user_form_page.impersonate_confirm', ['name' => $user->name])), callback: function(){ window.location.href = @js(route('admin.users.impersonate', $user)); } })"
                   class="btn btn-outline btn-sm" style="width:100%;justify-content:center">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    {{ __('ui.user_form_page.log_in_as', ['name' => $user->name]) }}
                </a>
            </div>
        </div>
        @endcan
        @endif
    </div>
</div>

<script>
function initMultiSelect(widgetSelector, placeholder, filterPlaceholder) {
    const select = document.querySelector(widgetSelector);
    if (!select) return;

    const wrap = document.createElement('div');
    wrap.className = 'ss-wrap';
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

const roleHints = {
    agent:      @json(__('ui.user_form_page.agent_desc')),
    supervisor: @json(__('ui.user_form_page.supervisor_desc')),
    admin:      @json(__('ui.user_form_page.admin_desc'))
};

function updateRoleHint(role) {
    const el = document.getElementById('role-hint');
    if (el) el.textContent = roleHints[role] || '';
}

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('role');
    if (sel) updateRoleHint(sel.value);

    initMultiSelect(
        'select[data-user-teams-ss]',
        @json(__('ui.team_form_page.select')),
        @json(__('ui.team_form_page.filter'))
    );
});
</script>
@endsection
