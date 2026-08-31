@extends('layouts.admin')

@section('title', __('ui.knowledge_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.knowledge_page.breadcrumb') }}</span>
@endsection

@section('content')
<div x-data="{ showForm: {{ old('title') ? 'true' : 'false' }}, editId: null, showImport: false, importFileName: '' }">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;gap:.75rem;flex-wrap:wrap">
        <div>
            <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">{{ __('ui.knowledge_page.title') }}</h1>
            <p style="font-size:.875rem;color:var(--text-muted);margin-top:.125rem">{{ __('ui.knowledge_page.subtitle') }}</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button @click="showImport = true" class="btn btn-outline" type="button">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                {{ __('ui.knowledge_page.import_json') }}
            </button>
            <button @click="showForm = true; editId = null" class="btn btn-primary" type="button">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                {{ __('ui.knowledge_page.add_entry') }}
            </button>
        </div>
    </div>

    @if(session('import_errors'))
        <div class="card" style="margin-bottom:1rem;padding:1rem 1.25rem;border-left:3px solid #f59e0b;background:#fffbeb">
            <div style="font-weight:600;font-size:.875rem;color:#92400e;margin-bottom:.375rem">
                {{ __('ui.knowledge_page.import_errors_title') }}
            </div>
            <ul style="margin:0;padding-inline-start:1.25rem;font-size:.8125rem;color:#78350f;line-height:1.6">
                @foreach(array_slice(session('import_errors'), 0, 20) as $err)
                    <li>{{ $err }}</li>
                @endforeach
                @if(count(session('import_errors')) > 20)
                    <li style="color:#a16207">…{{ count(session('import_errors')) - 20 }} more</li>
                @endif
            </ul>
        </div>
    @endif

    {{-- Import JSON modal — teleported to <body> so it escapes the admin layout
         wrappers (sidebar sits at z-index:100 and would otherwise cover the
         backdrop, which is what caused the clipped-modal design bug). --}}
    <template x-teleport="body">
    <div x-show="showImport" x-cloak
         @keydown.escape.window="showImport = false"
         style="position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem"
         x-transition.opacity>
        <div @click.outside="showImport = false" x-transition
             style="background:#fff;border-radius:12px;max-width:520px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between">
                <div style="font-weight:700;font-size:1rem">{{ __('ui.knowledge_page.import_json_title') }}</div>
                <button @click="showImport = false" class="modal-close" type="button">&times;</button>
            </div>
            <form action="{{ route('admin.knowledge.import') }}" method="POST" enctype="multipart/form-data" data-loading>
                @csrf
                <div style="padding:1.25rem;display:flex;flex-direction:column;gap:1rem">
                    <p style="font-size:.8125rem;color:var(--text-muted);margin:0;line-height:1.55">
                        {{ __('ui.knowledge_page.import_json_hint') }}
                    </p>

                    <label class="form-label" style="display:flex;flex-direction:column;gap:.5rem;cursor:pointer">
                        <span>{{ __('ui.knowledge_page.select_file') }} <span style="color:#ef4444">*</span></span>
                        <input type="file" name="file" accept=".json,application/json,text/json,text/plain" required
                               @change="importFileName = $event.target.files[0]?.name || ''"
                               class="form-control @error('file') error @enderror"
                               style="padding:.5rem">
                        <template x-if="importFileName">
                            <span style="font-size:.75rem;color:var(--text-muted)" x-text="importFileName"></span>
                        </template>
                        @error('file') <span class="form-error">{{ $message }}</span> @enderror
                    </label>

                    <a href="{{ route('admin.knowledge.import.template') }}"
                       style="font-size:.8125rem;color:var(--brand);text-decoration:none;display:inline-flex;align-items:center;gap:.375rem;align-self:flex-start">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        {{ __('ui.knowledge_page.download_template') }}
                    </a>
                </div>
                <div style="padding:1rem 1.25rem;border-top:1px solid var(--card-border);display:flex;gap:.5rem;justify-content:flex-end">
                    <button type="button" @click="showImport = false" class="btn btn-outline">{{ __('ui.knowledge_page.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('ui.knowledge_page.upload_button') }}</button>
                </div>
            </form>
        </div>
    </div>
    </template>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start">
        <div>
            <div class="tab-nav" style="margin-bottom:1.25rem">
                @php
                    $types = [
                        'all' => __('ui.knowledge_page.all'),
                        'faq' => __('ui.knowledge_page.types.faq'),
                        'product' => __('ui.knowledge_page.types.product'),
                        'policy' => __('ui.knowledge_page.types.policy'),
                        'company_profile' => __('ui.knowledge_page.types.company_profile'),
                        'custom_instruction' => __('ui.knowledge_page.types.custom_instruction'),
                    ];
                @endphp
                @foreach($types as $value => $label)
                    <a href="{{ route('admin.knowledge.index', $value !== 'all' ? ['type' => $value] : []) }}"
                       class="tab-btn {{ request('type', 'all') === $value ? 'active' : '' }}"
                       style="text-decoration:none">
                        {{ $label }}
                        @if($value !== 'all')
                            <span style="margin-left:.25rem;opacity:.7">{{ $counts[$value] ?? 0 }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            @if($entries->isEmpty())
                <div class="card">
                    <div class="empty-state" style="padding:3rem">
                        <div class="empty-state-icon">
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <h4>{{ __('ui.knowledge_page.empty_title') }}</h4>
                        <p>{{ __('ui.knowledge_page.empty_description') }}</p>
                    </div>
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:.625rem">
                    @foreach($entries as $entry)
                        <div class="card" style="padding:.875rem 1.25rem;transition:box-shadow .15s"
                             onmouseenter="this.style.boxShadow='0 2px 12px rgba(0,0,0,.08)'" onmouseleave="this.style.boxShadow=''">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem">
                                <div style="flex:1;min-width:0">
                                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.375rem">
                                        @php
                                            $typeBadges = ['faq'=>'badge-blue','product'=>'badge-green','policy'=>'badge-orange','company_profile'=>'badge-purple','custom_instruction'=>'badge-gray'];
                                        @endphp
                                        <span class="badge {{ $typeBadges[$entry->type] ?? 'badge-gray' }}" style="font-size:.6875rem">
                                            {{ __('ui.knowledge_page.types.' . $entry->type) ?: ucfirst(str_replace('_',' ',$entry->type)) }}
                                        </span>
                                        @if(!$entry->is_active)
                                            <span class="badge badge-gray" style="font-size:.6875rem">{{ __('ui.knowledge_page.inactive') }}</span>
                                        @endif
                                    </div>
                                    <div style="font-weight:600;font-size:.9375rem;margin-bottom:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $entry->title }}</div>
                                    <div style="font-size:.8125rem;color:var(--text-muted);overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $entry->body }}</div>
                                </div>
                                <div style="display:flex;gap:.25rem;flex-shrink:0">
                                    <a href="{{ route('admin.knowledge.edit', $entry) }}" class="action-btn">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    <button onclick="confirmDelete('{{ route('admin.knowledge.destroy', $entry) }}', { title: @js(__('ui.knowledge_page.delete_prompt')), message: @js(__('ui.knowledge_page.delete_message', ['name' => $entry->title])) })"
                                            class="action-btn danger">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($entries->hasPages())
                    <div style="margin-top:1rem">
                        {{ $entries->links('admin.partials.pagination') }}
                    </div>
                @endif
            @endif
        </div>

        <div x-show="showForm" x-transition>
            <div class="card" style="position:sticky;top:1rem">
                <div class="card-header">
                    <div class="card-title" x-text="editId ? @json(__('ui.knowledge_page.edit_entry')) : @json(__('ui.knowledge_page.new_entry'))"></div>
                    <button @click="showForm = false" class="modal-close">&times;</button>
                </div>
                <form :action="editId ? `/admin/knowledge/${editId}` : '{{ route('admin.knowledge.store') }}'" method="POST" data-loading>
                    @csrf
                    <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                    <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1rem">
                        <div class="form-group">
                            <label class="form-label">{{ __('ui.knowledge_page.type') }} <span style="color:#ef4444">*</span></label>
                            <select name="type" class="form-control @error('type') error @enderror" required>
                                <option value="faq" {{ old('type') === 'faq' ? 'selected' : '' }}>{{ __('ui.knowledge_page.types.faq') }}</option>
                                <option value="product" {{ old('type') === 'product' ? 'selected' : '' }}>{{ __('ui.knowledge_page.types.product') }}</option>
                                <option value="policy" {{ old('type') === 'policy' ? 'selected' : '' }}>{{ __('ui.knowledge_page.types.policy') }}</option>
                                <option value="company_profile" {{ old('type') === 'company_profile' ? 'selected' : '' }}>{{ __('ui.knowledge_page.types.company_profile') }}</option>
                                <option value="custom_instruction" {{ old('type') === 'custom_instruction' ? 'selected' : '' }}>{{ __('ui.knowledge_page.types.custom_instruction') }}</option>
                            </select>
                            @error('type') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.knowledge_page.entry_title') }} <span style="color:#ef4444">*</span></label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   placeholder="{{ __('ui.knowledge_page.title_placeholder') }}"
                                   class="form-control @error('title') error @enderror" required>
                            @error('title') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('ui.knowledge_page.content') }} <span style="color:#ef4444">*</span></label>
                            <textarea name="body" rows="8"
                                      class="form-control @error('body') error @enderror"
                                      placeholder="{{ __('ui.knowledge_page.content_placeholder') }}"
                                      required>{{ old('body') }}</textarea>
                            @error('body') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <label class="toggle-label">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                                <span class="toggle-text">{{ __('ui.knowledge_page.active') }}</span>
                            </label>
                        </div>
                    </div>

                    <div style="padding:1rem 1.5rem;border-top:1px solid var(--card-border);display:flex;gap:.5rem">
                        <button type="submit" class="btn btn-primary" style="flex:1">{{ __('ui.knowledge_page.save_entry') }}</button>
                        <button type="button" @click="showForm = false" class="btn btn-outline">{{ __('ui.knowledge_page.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="!showForm" style="padding:2rem;text-align:center;color:var(--text-muted)">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" style="margin:0 auto 1rem;opacity:.3"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <div style="font-size:.875rem">{{ __('ui.knowledge_page.empty_panel_hint') }}</div>
        </div>
    </div>
</div>
@endsection
