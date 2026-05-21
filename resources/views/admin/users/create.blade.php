@extends('layouts.admin')

@section('title', __('ui.user_form_page.create_title'))

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.user_form_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.user_form_page.title') }}</span>
@endsection

@section('content')
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
                    @if($teams->isEmpty())
                        <div style="font-size:.8125rem;color:var(--text-muted);padding:.5rem 0">{{ __('ui.user_form_page.no_teams_created') }}</div>
                        <a href="{{ route('admin.teams.create') }}" style="font-size:.8125rem;color:var(--brand)">{{ __('ui.user_form_page.create_team_first') }}</a>
                    @else
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
                        @error('teams') <div class="form-error">{{ $message }}</div> @enderror
                        @error('teams.*') <div class="form-error">{{ $message }}</div> @enderror
                        <div class="form-hint">{{ __('ui.user_form_page.assign_to_teams_hint') }}</div>
                    @endif
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

document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const tenantField = document.getElementById('tenantField');

    function syncTenantField() {
        if (!roleSelect || !tenantField) return;
        tenantField.style.display = roleSelect.value === 'admin' ? '' : 'none';
    }

    roleSelect?.addEventListener('change', syncTenantField);
    syncTenantField();

    initMultiSelect(
        'select[data-user-teams-ss]',
        @json(__('ui.team_form_page.select')),
        @json(__('ui.team_form_page.filter'))
    );
});
</script>
@endsection
