@extends('layouts.admin')

@section('title', 'New Team')

@section('breadcrumb')
    @php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
    <a href="{{ route($panelPrefix . '.teams.index') }}" style="color:var(--text-secondary);text-decoration:none">Teams</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>New Team</span>
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
        <h1 class="page-title">Create Team</h1>
        <p class="page-subtitle">Set team details and assign members</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route($panelPrefix . '.teams.index') }}" class="btn btn-outline">
            <i class="ri-arrow-left-line"></i> Back
        </a>
    </div>
</div>

<div>
    <form action="{{ route($panelPrefix . '.teams.store') }}" method="POST" data-loading>
        @csrf

        <div class="team-create-grid">
            <div class="card" style="overflow:visible;">
                <div class="card-header">
                    <div>
                        <div class="card-title">Team Details</div>
                        <div class="card-subtitle">Name, description and status</div>
                    </div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">
                    <div class="form-group">
                        <label class="form-label" for="name">Team Name</label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name') }}"
                               placeholder="e.g. Technical Support, Sales, Billing"
                               class="form-control @error('name') error @enderror">
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

                    <div class="form-group">
                        <label class="form-label" for="members">Team Members</label>
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
                        <div class="form-hint">Click to open, then select one or more members.</div>
                    </div>

                    <div style="display:flex;align-items:center;gap:.75rem">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', '1') ? 'checked' : '' }}>
                            <span class="toggle-text">Active</span>
                        </label>
                        <span style="font-size:.8125rem;color:var(--text-muted)">Inactive teams will not receive new conversations</span>
                    </div>

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

        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem">
            <a href="{{ route($panelPrefix . '.teams.index') }}" class="btn btn-outline">
                <i class="ri-close-line"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="ri-check-line"></i> Create Team
            </button>
        </div>
    </form>
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
    displayInput.placeholder = 'Select...';

    const chevron = document.createElement('i');
    chevron.className = 'ri-arrow-down-s-line ss-chevron';

    const dropdown = document.createElement('div');
    dropdown.className = 'ss-dropdown';

    const searchRow = document.createElement('div');
    searchRow.className = 'ss-search-row';
    searchRow.innerHTML = '<div class="ss-search-inner"><i class="ri-search-line"></i><input type="text" placeholder="Filter..." autocomplete="off"></div>';

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
                emptyEl.textContent = 'No results found';
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
@endsection
