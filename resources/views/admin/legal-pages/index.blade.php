@extends('layouts.admin')

@section('title', 'Legal Pages')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>Legal Pages</span>
@endsection

@section('content')
@php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Legal Pages</div>
        <div class="page-subtitle">Manage Terms, Privacy Policy, and Cookie Policy visible to users.</div>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('legal.terms') }}" target="_blank" class="btn btn-outline">
            <i class="ri-external-link-line"></i> View site
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:20px">
    <i class="ri-checkbox-circle-line"></i> {{ session('success') }}
</div>
@endif

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px">

    @foreach($pages as $slug => $meta)
    @php
        $slugRecords = $records->get($slug, collect())->keyBy('locale');
        $publicRoute = route($meta['route']);
    @endphp

    <div class="card" style="padding:0;overflow:hidden">
        {{-- Card header --}}
        <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:40px;height:40px;border-radius:10px;background:var(--brand-xlight);border:1px solid var(--brand-light);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0">
                    <i class="{{ $meta['icon'] }}" style="font-size:18px"></i>
                </div>
                <div>
                    <div style="font-size:15px;font-weight:700;color:var(--text)">{{ __('landing.' . $meta['label_key']) }}</div>
                    <a href="{{ $publicRoute }}" target="_blank" style="font-size:12px;color:var(--text-muted);text-decoration:none;display:flex;align-items:center;gap:4px">
                        {{ parse_url($publicRoute, PHP_URL_PATH) }} <i class="ri-external-link-line" style="font-size:11px"></i>
                    </a>
                </div>
            </div>
        </div>

        {{-- Locale rows --}}
        <div style="padding:8px 0">
            @foreach($locales as $locale => $localeMeta)
            @php $rec = $slugRecords->get($locale); @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 24px;border-bottom:1px solid var(--border)">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:18px">{{ $localeMeta['flag'] }}</span>
                    <div>
                        <div style="font-size:14px;font-weight:600;color:var(--text)">{{ $localeMeta['label'] }}</div>
                        @if($rec)
                        <div style="font-size:12px;color:var(--text-muted)">Last saved {{ $rec->updated_at->diffForHumans() }}</div>
                        @else
                        <div style="font-size:12px;color:var(--text-muted)">Using static fallback</div>
                        @endif
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    @if($rec)
                    <span class="badge badge-success" style="font-size:11px">Saved</span>
                    @else
                    <span class="badge badge-warning" style="font-size:11px">Default</span>
                    @endif
                    <a href="{{ route($panelPrefix . '.platform.legal-pages.edit', [$slug, $locale]) }}"
                       class="btn btn-sm btn-outline">
                        <i class="ri-pencil-line"></i> Edit
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

</div>
@endsection
