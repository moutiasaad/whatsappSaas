@extends('layouts.admin')

@section('title', __('ui.platform_plans_edit_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_plans_edit_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('super_admin.platform.plans') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.platform_plans_edit_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $plan->name }}</span>
@endsection

@section('content')
    <div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.platform_plans_edit_page.page_title') }}</div>
                <div class="card-subtitle">{{ __('ui.platform_plans_edit_page.subtitle') }}</div>
            </div>
            <a href="{{ route('super_admin.platform.plans.show', $plan) }}" class="btn btn-outline btn-sm">
                <i class="ri-eye-line"></i> {{ __('ui.platform_plans_edit_page.view') }}
            </a>
        </div>

        <form method="POST" action="{{ route('super_admin.platform.plans.update', $plan) }}" style="padding:20px;">
            @csrf
            @method('PUT')

            @include('admin.platform.partials.plan-form-fields', ['plan' => $plan])

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                <a href="{{ route('super_admin.platform.plans.show', $plan) }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.platform_plans_edit_page.save_changes') }}
                </button>
            </div>
        </form>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div class="card">
            <div class="card-header" style="padding-bottom:12px;">
                <div class="card-title">{{ __('ui.platform_plans_edit_page.plan_usage') }}</div>
            </div>
            <div style="padding:0 18px 18px 18px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">{{ __('ui.platform_plans_edit_page.assigned_tenants') }}</span>
                    <strong>{{ number_format($plan->tenants_count) }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">{{ __('ui.platform_plans_edit_page.status') }}</span>
                    <strong>{{ $plan->is_active ? __('ui.platform_plans_page.active') : __('ui.platform_plans_page.disabled') }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Country prices — separate form + endpoint so pricing edits don't
     require re-submitting the whole plan. Empty rows mean "use base USD
     price for this country" (see updatePlanCountryPrices). --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div>
            <div class="card-title">{{ __('ui.platform_plans_edit_page.country_prices_title') }}</div>
            <div class="card-subtitle">{{ __('ui.platform_plans_edit_page.country_prices_subtitle', ['base' => '$' . number_format((float) $plan->price_monthly, 2)]) }}</div>
        </div>
        <a href="{{ route('super_admin.platform.countries.index') }}" class="btn btn-outline btn-sm">
            <i class="ri-earth-line"></i> {{ __('ui.platform_plans_edit_page.manage_countries') }}
        </a>
    </div>

    @if($countries->isEmpty())
        <div class="empty-state" style="padding:2rem 1rem;">
            <div class="empty-state-icon"><i class="ri-earth-line"></i></div>
            <h4>{{ __('ui.platform_plans_edit_page.no_countries_title') }}</h4>
            <p>{{ __('ui.platform_plans_edit_page.no_countries_body') }}</p>
            <a href="{{ route('super_admin.platform.countries.create') }}" class="btn btn-primary" style="margin-top:12px;">
                <i class="ri-add-line"></i> {{ __('ui.platform_countries_page.add_country') }}
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('super_admin.platform.plans.country-prices.update', $plan) }}" style="padding:0;">
            @csrf @method('PUT')
            <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.platform_plans_edit_page.col_country') }}</th>
                            <th>{{ __('ui.platform_plans_edit_page.col_currency') }}</th>
                            <th>{{ __('ui.platform_plans_edit_page.col_price_monthly') }}</th>
                            <th>{{ __('ui.platform_plans_edit_page.col_price_annual') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($countries as $country)
                            @php $row = $countryPrices->get($country->code); @endphp
                            <tr>
                                <td style="font-weight:600;">
                                    {{ $country->name }}
                                    <span style="color:var(--text-muted);font-family:var(--font-mono);font-size:11px;margin-inline-start:4px;">({{ $country->code }})</span>
                                </td>
                                <td>
                                    <span style="font-weight:600;">{{ $country->currency_code }}</span>
                                    <span style="color:var(--text-muted);margin-inline-start:4px;">{{ $country->currency_symbol }}</span>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="999999"
                                           name="prices[{{ $country->code }}][price_monthly]"
                                           value="{{ old('prices.' . $country->code . '.price_monthly', $row?->price_monthly) }}"
                                           placeholder="—"
                                           class="form-control" style="max-width:140px;">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="999999"
                                           name="prices[{{ $country->code }}][price_annual]"
                                           value="{{ old('prices.' . $country->code . '.price_annual', $row?->price_annual) }}"
                                           placeholder="—"
                                           class="form-control" style="max-width:140px;">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-top:1px solid var(--card-border);">
                <div style="color:var(--text-muted);font-size:12.5px;">
                    <i class="ri-information-line"></i>
                    {{ __('ui.platform_plans_edit_page.country_prices_hint') }}
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> {{ __('ui.platform_plans_edit_page.save_country_prices') }}
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
