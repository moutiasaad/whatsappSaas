@extends('layouts.admin')

@section('title', 'Edit Legal Page')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route(auth()->user()->routeNamePrefix() . '.platform.legal-pages.index') }}" style="color:var(--text-muted);text-decoration:none">Legal Pages</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('landing.' . $pages[$slug]['label_key']) }} — {{ strtoupper($locale) }}</span>
@endsection

@push('styles')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    .ql-container { font-family: inherit; font-size: 15px; }
    .ql-editor { min-height: 480px; line-height: 1.7; color: var(--text); }
    .ql-editor h2 { font-size: 20px; font-weight: 700; margin: 28px 0 10px; }
    .ql-editor h3 { font-size: 16px; font-weight: 600; margin: 20px 0 8px; }
    .ql-editor p  { margin-bottom: 12px; color: #374151; }
    .ql-editor ul, .ql-editor ol { padding-left: 24px; margin-bottom: 12px; }
    .ql-editor a  { color: #10b981; }
    .ql-toolbar.ql-snow { border-radius: 10px 10px 0 0; border-color: var(--border); background: var(--bg); }
    .ql-container.ql-snow { border-radius: 0 0 10px 10px; border-color: var(--border); border-top: none; background: #fff; }
    .ql-snow .ql-stroke { stroke: var(--text-muted); }
    .ql-snow .ql-fill  { fill: var(--text-muted); }

    .locale-tab { display:inline-flex; align-items:center; gap:6px; padding:7px 16px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; color:var(--text-muted); border:1.5px solid var(--border); transition:all .2s; }
    .locale-tab:hover { border-color:var(--brand); color:var(--brand); background:var(--brand-xlight); }
    .locale-tab.active { background:var(--brand); color:#fff; border-color:var(--brand); }

    .preview-hint { display:flex; align-items:center; gap:8px; padding:12px 16px; background:var(--brand-xlight); border:1px solid var(--brand-light); border-radius:10px; font-size:13px; color:var(--brand-xdark); margin-bottom:20px; }
</style>
@endpush

@section('content')
@php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">
            <i class="{{ $pages[$slug]['icon'] }}" style="margin-right:8px;color:var(--brand)"></i>
            {{ __('landing.' . $pages[$slug]['label_key']) }}
        </div>
        <div class="page-subtitle">Editing the <strong>{{ strtoupper($locale) }}</strong> version. Changes are live as soon as you save.</div>
    </div>
    <div class="page-header-actions">
        <a href="{{ route($pages[$slug]['route']) }}" target="_blank" class="btn btn-outline">
            <i class="ri-external-link-line"></i> Preview
        </a>
        <a href="{{ route($panelPrefix . '.platform.legal-pages.index') }}" class="btn btn-outline">
            <i class="ri-arrow-left-line"></i> Back
        </a>
    </div>
</div>

{{-- Locale tabs --}}
<div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
    @foreach($locales as $loc => $localeMeta)
    <a href="{{ route($panelPrefix . '.platform.legal-pages.edit', [$slug, $loc]) }}"
       class="locale-tab {{ $loc === $locale ? 'active' : '' }}">
        <span>{{ $localeMeta['flag'] }}</span> {{ $localeMeta['label'] }}
    </a>
    @endforeach
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:20px">
    <i class="ri-checkbox-circle-line"></i> {{ session('success') }}
</div>
@endif

@if(!$page->exists)
<div class="preview-hint">
    <i class="ri-information-line" style="font-size:16px;flex-shrink:0"></i>
    No saved content for this locale yet. The public page shows the built-in static fallback. Save to override it.
</div>
@endif

<form method="POST" action="{{ route($panelPrefix . '.platform.legal-pages.update', [$slug, $locale]) }}" id="legalForm">
    @csrf
    @method('PUT')

    {{-- Title --}}
    <div class="card" style="margin-bottom:20px;padding:24px">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px">
            Page Title
        </label>
        <input type="text" name="title" value="{{ old('title', $page->title) }}"
               placeholder="{{ __('landing.' . $pages[$slug]['label_key']) }}"
               style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:15px;font-weight:600;font-family:inherit;color:var(--text);background:#fff;outline:none;transition:border-color .2s"
               onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='var(--border)'">
        @error('title')
        <div style="font-size:12px;color:#ef4444;margin-top:6px">{{ $message }}</div>
        @enderror
    </div>

    {{-- Rich text editor --}}
    <div class="card" style="margin-bottom:24px;padding:24px">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:12px;text-transform:uppercase;letter-spacing:.4px">
            Content
        </label>

        <div id="quill-editor">{!! old('content', $page->content) !!}</div>
        <input type="hidden" name="content" id="content-input">

        @error('content')
        <div style="font-size:12px;color:#ef4444;margin-top:6px">{{ $message }}</div>
        @enderror
    </div>

    {{-- Actions --}}
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
        <a href="{{ route($panelPrefix . '.platform.legal-pages.index') }}" class="btn btn-outline">
            Cancel
        </a>
        <button type="submit" class="btn btn-primary" id="saveBtn">
            <i class="ri-save-line"></i> Save changes
        </button>
    </div>
</form>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const quill = new Quill('#quill-editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ header: [2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

// On submit, copy Quill HTML to hidden input
document.getElementById('legalForm').addEventListener('submit', function () {
    document.getElementById('content-input').value = quill.root.innerHTML;
});

// Disable submit if empty
quill.on('text-change', function () {
    const empty = quill.getText().trim().length === 0;
    document.getElementById('saveBtn').disabled = empty;
});
</script>
@endsection
