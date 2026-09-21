@extends('layouts.admin')

@section('title', __('ui.platform_countries_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_countries_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_countries_page.title') }}</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_countries_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_countries_page.subtitle') }}</div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('super_admin.platform.countries.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> {{ __('ui.platform_countries_page.add_country') }}
            </a>
        </div>
    </div>

    <div class="card" style="padding:0;">
        @if($countries->isEmpty())
            <div class="empty-state" style="padding:3rem 1rem;">
                <div class="empty-state-icon"><i class="ri-earth-line"></i></div>
                <h4>{{ __('ui.platform_countries_page.no_countries_title') }}</h4>
                <p>{{ __('ui.platform_countries_page.no_countries_body') }}</p>
            </div>
        @else
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.platform_countries_page.col_country') }}</th>
                            <th>{{ __('ui.platform_countries_page.col_code') }}</th>
                            <th>{{ __('ui.platform_countries_page.col_currency') }}</th>
                            <th>{{ __('ui.platform_countries_page.col_status') }}</th>
                            <th style="text-align:end;">{{ __('ui.platform_countries_page.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($countries as $country)
                            <tr>
                                <td style="font-weight:600;">{{ $country->name }}</td>
                                <td><code style="font-family:var(--font-mono);font-size:12px;">{{ $country->code }}</code></td>
                                <td>
                                    <strong>{{ $country->currency_code }}</strong>
                                    <span style="color:var(--text-muted);margin-inline-start:6px;">{{ $country->currency_symbol }}</span>
                                </td>
                                <td>
                                    @if($country->is_active)
                                        <span class="badge badge-green"><i class="ri-checkbox-circle-line"></i> {{ __('ui.enabled') }}</span>
                                    @else
                                        <span class="badge badge-gray"><i class="ri-close-circle-line"></i> {{ __('ui.disabled') }}</span>
                                    @endif
                                </td>
                                <td style="text-align:end;">
                                    <div style="display:inline-flex;gap:.25rem;">
                                        <a href="{{ route('super_admin.platform.countries.edit', $country) }}" class="action-btn" title="{{ __('ui.edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        <form method="POST" action="{{ route('super_admin.platform.countries.toggle', $country) }}" style="margin:0;">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="action-btn" title="{{ $country->is_active ? __('ui.disable') : __('ui.enable') }}">
                                                <i class="{{ $country->is_active ? 'ri-forbid-2-line' : 'ri-checkbox-circle-line' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('super_admin.platform.countries.destroy', $country) }}"
                                              onsubmit="return confirm(@js(__('ui.platform_countries_page.delete_confirm', ['name' => $country->name])))"
                                              style="margin:0;">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="action-btn danger" title="{{ __('ui.delete') }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div style="margin-top:1rem;padding:1rem 1.25rem;font-size:12.5px;color:var(--text-muted);background:var(--page-bg);border-radius:8px;line-height:1.6;">
        <i class="ri-information-line"></i>
        {{ __('ui.platform_countries_page.footnote') }}
    </div>
</div>
@endsection
