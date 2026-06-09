@extends('layouts.admin')

@section('title', __('ui.super_admins_page.create_title'))

@section('breadcrumb')
    <a href="{{ route(auth()->user()->routeNamePrefix() . '.super-admins.index') }}">{{ __('ui.super_admins_page.breadcrumb') }}</a>
    <span class="sep"><i class="ri-arrow-right-s-line"></i></span>
    <span>{{ __('ui.super_admins_page.create_title') }}</span>
@endsection

@php
    $panelPrefix  = auth()->user()->routeNamePrefix();
    $checkedPerms = old('permissions', []);
    // Preserve slug keys when grouping
    $groups = [];
    foreach ($permissions as $slug => $meta) {
        $groups[$meta['group']][$slug] = $meta;
    }
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ __('ui.super_admins_page.create_title') }}</div>
        <div class="page-subtitle">{{ __('ui.super_admins_page.create_subtitle') }}</div>
    </div>
    <div class="page-header-actions">
        <a href="{{ route($panelPrefix . '.super-admins.index') }}" class="btn btn-outline btn-sm">
            <i class="ri-arrow-left-line"></i> {{ __('ui.back') }}
        </a>
    </div>
</div>

<form method="POST" action="{{ route($panelPrefix . '.super-admins.store') }}">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.25rem;align-items:start;">

        {{-- Left: account details + permissions --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.super_admins_page.section_account') }}</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.super_admins_page.name_label') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.super_admins_page.email_label') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('ui.super_admins_page.password_label') }}</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">{{ __('ui.super_admins_page.password_confirm') }}</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="card" style="padding:0;overflow:hidden;">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;">
                    <div>
                        <div class="card-title">{{ __('ui.super_admins_page.section_perms') }}</div>
                        <div class="card-subtitle">{{ __('ui.super_admins_page.section_perms_hint') }}</div>
                    </div>
                    <div style="display:flex;gap:.5rem;">
                        <button type="button" class="btn btn-outline btn-sm" id="selectAll">{{ __('ui.super_admins_page.select_all') }}</button>
                        <button type="button" class="btn btn-outline btn-sm" id="deselectAll">{{ __('ui.super_admins_page.deselect_all') }}</button>
                    </div>
                </div>

                @foreach($groups as $group => $items)
                <div class="perm-group">
                    <div class="perm-group-label">{{ __('ui.super_admins_page.group_' . $group) }}</div>
                    @foreach($items as $slug => $meta)
                    @php $isChecked = in_array($slug, $checkedPerms); @endphp
                    <label class="perm-row">
                        <div class="perm-row-left">
                            <span class="perm-icon-wrap">
                                <i class="{{ $meta['icon'] }}"></i>
                            </span>
                            <span class="perm-label-text">{{ __('ui.super_admins_page.perm_' . $slug) }}</span>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" name="permissions[]" value="{{ $slug }}"
                                   class="perm-checkbox" {{ $isChecked ? 'checked' : '' }}>
                            <span class="toggle-track">
                                <span class="toggle-thumb"></span>
                            </span>
                        </div>
                    </label>
                    @endforeach
                </div>
                @endforeach
            </div>

        </div>

        {{-- Right: action card --}}
        <div class="card" style="position:sticky;top:1rem;">
            <div class="card-body">
                <button type="submit" class="btn btn-primary" style="width:100%;margin-bottom:.75rem;">
                    <i class="ri-user-add-line"></i> {{ __('ui.super_admins_page.add_btn') }}
                </button>
                <a href="{{ route($panelPrefix . '.super-admins.index') }}" class="btn btn-outline" style="width:100%;">
                    {{ __('ui.cancel') }}
                </a>
            </div>
        </div>

    </div>
</form>

@include('admin.super-admins._perm_styles')
@endsection
