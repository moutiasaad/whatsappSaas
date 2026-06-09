@extends('layouts.admin')

@section('title', __('ui.super_admins_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.super_admins_page.breadcrumb') }}</span>
@endsection

@php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp

@section('content')
<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">{{ __('ui.super_admins_page.title') }}</div>
        <div class="page-subtitle">{{ __('ui.super_admins_page.subtitle') }}</div>
    </div>
    <div class="page-header-actions">
        <a href="{{ route($panelPrefix . '.super-admins.create') }}" class="btn btn-primary btn-sm">
            <i class="ri-add-line"></i> {{ __('ui.super_admins_page.add_btn') }}
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

<div class="table-wrap">
    @if($admins->isEmpty())
        <div class="empty-state">
            <div class="empty-state-icon"><i class="ri-shield-user-line"></i></div>
            <h4>{{ __('ui.super_admins_page.no_admins') }}</h4>
            <p>{{ __('ui.super_admins_page.no_admins_hint') }}</p>
            <a href="{{ route($panelPrefix . '.super-admins.create') }}" class="btn btn-primary btn-sm">
                <i class="ri-add-line"></i> {{ __('ui.super_admins_page.add_btn') }}
            </a>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('ui.super_admins_page.name_col') }}</th>
                    <th>{{ __('ui.super_admins_page.email_col') }}</th>
                    <th>{{ __('ui.super_admins_page.access_col') }}</th>
                    <th>{{ __('ui.super_admins_page.created_col') }}</th>
                    <th style="width:120px;">{{ __('ui.super_admins_page.actions_col') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($admins as $admin)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:.625rem;">
                            <div style="width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;flex-shrink:0;">
                                {{ strtoupper(substr($admin->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:.9375rem;">{{ $admin->name }}</div>
                                @if($admin->isMasterSuperAdmin())
                                    <span class="badge badge-green" style="font-size:.6875rem;">
                                        <i class="ri-shield-star-line" style="font-size:.625rem;"></i>
                                        {{ __('ui.super_admins_page.master_badge') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-secondary);">{{ $admin->email }}</td>
                    <td>
                        @if($admin->isMasterSuperAdmin())
                            <span class="badge badge-green">
                                <i class="ri-infinity-line"></i>
                                {{ __('ui.super_admins_page.full_access') }}
                            </span>
                        @elseif(empty($admin->sidebar_permissions))
                            <span class="badge badge-gray">{{ __('ui.super_admins_page.no_permissions') }}</span>
                        @else
                            <div style="display:flex;flex-wrap:wrap;gap:.25rem;max-width:320px;">
                                @foreach($admin->sidebar_permissions as $perm)
                                    <span class="badge badge-blue" style="font-size:.6875rem;">
                                        <i class="{{ $permissions[$perm]['icon'] ?? 'ri-checkbox-circle-line' }}" style="font-size:.625rem;"></i>
                                        {{ __('ui.super_admins_page.perm_' . $perm) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td style="color:var(--text-muted);font-size:.8125rem;">{{ $admin->created_at->format('Y-m-d') }}</td>
                    <td>
                        <div style="display:flex;gap:.375rem;align-items:center;">
                            @if(!$admin->isMasterSuperAdmin())
                                <a href="{{ route($panelPrefix . '.super-admins.edit', $admin) }}" class="btn btn-outline btn-sm">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                @if($admin->id !== auth()->id())
                                <form method="POST" action="{{ route($panelPrefix . '.super-admins.destroy', $admin) }}"
                                      onsubmit="return confirm('{{ __('ui.super_admins_page.delete_confirm') }}')" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                                @endif
                            @else
                                <a href="{{ route($panelPrefix . '.super-admins.edit', $admin) }}" class="btn btn-outline btn-sm">
                                    <i class="ri-pencil-line"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
