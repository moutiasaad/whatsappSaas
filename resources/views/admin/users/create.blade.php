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

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">

        {{-- Left: Account Info --}}
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
                        <input type="text" id="name" name="name" value="{{ old('name') }}"
                               class="form-control @error('name') error @enderror">
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">{{ __('ui.user_form_page.email_address') }}</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') error @enderror">
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

                <div class="form-group">
                    <label class="form-label" for="password">
                        {{ __('ui.user_form_page.temporary_password') }}
                        <span style="font-size:.75rem;font-weight:400;color:var(--text-muted)">(optional — leave blank to send invite email)</span>
                    </label>
                    <input type="password" id="password" name="password"
                           placeholder="{{ __('ui.user_form_page.password_placeholder') }}"
                           class="form-control @error('password') error @enderror">
                    @error('password') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Right: Team Assignment --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.user_form_page.assign_to_teams') }}</div>
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
                    <p style="color:var(--text-muted);font-size:.875rem">{{ __('ui.user_form_page.no_teams_created') }}</p>
                    <a href="{{ route('admin.teams.create') }}" style="font-size:.8125rem;color:var(--brand)">{{ __('ui.user_form_page.create_team_first') }}</a>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline">{{ __('ui.user_form_page.cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('ui.user_form_page.send_invitation') }}</button>
    </div>
</form>
@if(auth()->user()->isSuperAdmin())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const tenantField = document.getElementById('tenantField');
    const tenantSelect = document.getElementById('tenant_id');

    function syncTenantField() {
        if (!roleSelect || !tenantField) return;
        tenantField.style.display = roleSelect.value === 'admin' ? '' : 'none';
    }

    roleSelect?.addEventListener('change', syncTenantField);
    syncTenantField();

    if (tenantSelect) {
        const wrap = tenantSelect.nextElementSibling;
        const dropdown = wrap?.querySelector('.ss-dropdown');
        const list = wrap?.querySelector('.ss-list');
        const searchInput = wrap?.querySelector('.ss-search-inner input');

        function syncDropdownPosition() {
            if (!wrap || !dropdown) return;
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
                list.style.maxHeight = Math.max(100, Math.min(220, room - 56)) + 'px';
            }
        }

        const observer = new MutationObserver(() => {
            if (wrap?.classList.contains('open')) syncDropdownPosition();
        });
        if (wrap) observer.observe(wrap, { attributes: true, attributeFilter: ['class'] });
        window.addEventListener('resize', () => {
            if (wrap?.classList.contains('open')) syncDropdownPosition();
        });
        window.addEventListener('scroll', () => {
            if (wrap?.classList.contains('open')) syncDropdownPosition();
        }, true);

        if (searchInput) {
            searchInput.addEventListener('focus', syncDropdownPosition);
        }
        const observer2 = new MutationObserver(syncDropdownPosition);
        if (list) observer2.observe(list, { childList: true, subtree: true });

        const tenantInput = wrap?.querySelector('.ss-input');
        const forceOpenUp = () => {
            if (!wrap || !wrap.classList.contains('open')) return;
            wrap.classList.add('open-up');
            syncDropdownPosition();
        };
        tenantInput?.addEventListener('click', () => setTimeout(forceOpenUp, 0));
        tenantInput?.addEventListener('keydown', (e) => {
            if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
                setTimeout(forceOpenUp, 0);
            }
        });
    }
});
</script>
@endif
@endsection
