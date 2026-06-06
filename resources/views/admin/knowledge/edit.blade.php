@extends('layouts.admin')

@section('title', __('ui.knowledge_page.edit_entry'))

@section('breadcrumb')
    @php $panelPrefix = auth()->user()->routeNamePrefix(); @endphp
    <a href="{{ route($panelPrefix . '.knowledge.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.knowledge_page.breadcrumb') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $entry->title }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $typeBadges  = ['faq'=>'badge-blue','product'=>'badge-green','policy'=>'badge-orange','company_profile'=>'badge-purple','custom_instruction'=>'badge-gray'];
@endphp

<div style="max-width:700px">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">{{ __('ui.knowledge_page.edit_entry') }}</div>
                <div class="card-subtitle">{{ $entry->title }}</div>
            </div>
            <span class="badge {{ $typeBadges[$entry->type] ?? 'badge-gray' }}">
                {{ __('ui.knowledge_page.types.' . $entry->type) }}
            </span>
        </div>

        <form action="{{ route($panelPrefix . '.knowledge.update', $entry) }}" method="POST" data-unsaved data-loading>
            @csrf
            @method('PUT')

            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">

                <div class="form-group">
                    <label class="form-label">{{ __('ui.knowledge_page.type') }} <span style="color:#ef4444">*</span></label>
                    <select name="type" class="form-control @error('type') error @enderror" required>
                        @foreach(['faq','product','policy','company_profile','custom_instruction'] as $t)
                            <option value="{{ $t }}" {{ old('type', $entry->type) === $t ? 'selected' : '' }}>
                                {{ __('ui.knowledge_page.types.' . $t) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('ui.knowledge_page.entry_title') }} <span style="color:#ef4444">*</span></label>
                    <input type="text" name="title"
                           value="{{ old('title', $entry->title) }}"
                           placeholder="{{ __('ui.knowledge_page.title_placeholder') }}"
                           class="form-control @error('title') error @enderror" required>
                    @error('title') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('ui.knowledge_page.content') }} <span style="color:#ef4444">*</span></label>
                    <textarea name="body" rows="10"
                              class="form-control @error('body') error @enderror"
                              placeholder="{{ __('ui.knowledge_page.content_placeholder') }}"
                              required>{{ old('body', $entry->body) }}</textarea>
                    @error('body') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div style="display:flex;align-items:center;gap:.5rem">
                    <label class="toggle-label">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $entry->is_active) ? 'checked' : '' }}>
                        <span class="toggle-text">{{ __('ui.knowledge_page.active') }}</span>
                    </label>
                </div>

            </div>

            <div style="padding:1rem 1.5rem;border-top:1px solid var(--card-border);display:flex;gap:.5rem;justify-content:flex-end">
                <a href="{{ route($panelPrefix . '.knowledge.index') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    {{ __('ui.knowledge_page.save_entry') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
