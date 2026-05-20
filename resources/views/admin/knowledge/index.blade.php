@extends('layouts.admin')

@section('title', 'Knowledge Base')

@section('breadcrumb')
    <span>Knowledge Base</span>
@endsection

@section('content')
<div x-data="{ showForm: {{ old('title') ? 'true' : 'false' }}, editId: null }">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
        <div>
            <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary)">Knowledge Base</h1>
            <p style="font-size:.875rem;color:var(--text-muted);margin-top:.125rem">Content the AI uses to answer customer questions accurately</p>
        </div>
        <button @click="showForm = true; editId = null" class="btn btn-primary">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Entry
        </button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start">

        {{-- Entry List --}}
        <div>
            {{-- Type Tabs --}}
            <div class="tab-nav" style="margin-bottom:1.25rem">
                @php
                    $types = ['all' => 'All', 'faq' => 'FAQ', 'product' => 'Products', 'policy' => 'Policies', 'company_profile' => 'Company', 'custom_instruction' => 'Instructions'];
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
                        <h4>No entries yet</h4>
                        <p>Add FAQ entries, product info, or company policies for the AI to reference</p>
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
                                    <span class="badge {{ $typeBadges[$entry->type] ?? 'badge-gray' }}" style="font-size:.6875rem">{{ ucfirst(str_replace('_',' ',$entry->type)) }}</span>
                                    @if(!$entry->is_active)
                                        <span class="badge badge-gray" style="font-size:.6875rem">Inactive</span>
                                    @endif
                                </div>
                                <div style="font-weight:600;font-size:.9375rem;margin-bottom:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $entry->title }}</div>
                                <div style="font-size:.8125rem;color:var(--text-muted);overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $entry->body }}</div>
                            </div>
                            <div style="display:flex;gap:.25rem;flex-shrink:0">
                                <a href="{{ route('admin.knowledge.edit', $entry) }}" class="action-btn">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <button onclick="confirmDelete('{{ route('admin.knowledge.destroy', $entry) }}', { title: 'Delete entry?', message: '{{ addslashes($entry->title) }} will be permanently removed.' })"
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

        {{-- Add/Edit Form --}}
        <div x-show="showForm" x-transition>
            <div class="card" style="position:sticky;top:1rem">
                <div class="card-header">
                    <div class="card-title" x-text="editId ? 'Edit Entry' : 'New Entry'"></div>
                    <button @click="showForm = false" class="modal-close">&times;</button>
                </div>
                <form :action="editId ? `/admin/knowledge/${editId}` : '{{ route('admin.knowledge.store') }}'" method="POST" data-loading>
                    @csrf
                    <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                    <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1rem">
                        <div class="form-group">
                            <label class="form-label">Type <span style="color:#ef4444">*</span></label>
                            <select name="type" class="form-control @error('type') error @enderror" required>
                                <option value="faq" {{ old('type') === 'faq' ? 'selected' : '' }}>FAQ</option>
                                <option value="product" {{ old('type') === 'product' ? 'selected' : '' }}>Product</option>
                                <option value="policy" {{ old('type') === 'policy' ? 'selected' : '' }}>Policy</option>
                                <option value="company_profile" {{ old('type') === 'company_profile' ? 'selected' : '' }}>Company Profile</option>
                                <option value="custom_instruction" {{ old('type') === 'custom_instruction' ? 'selected' : '' }}>Custom Instruction</option>
                            </select>
                            @error('type') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Title <span style="color:#ef4444">*</span></label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   placeholder="e.g. Shipping Policy"
                                   class="form-control @error('title') error @enderror" required>
                            @error('title') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Content <span style="color:#ef4444">*</span></label>
                            <textarea name="body" rows="8"
                                      class="form-control @error('body') error @enderror"
                                      placeholder="Enter the content the AI should know about…"
                                      required>{{ old('body') }}</textarea>
                            @error('body') <div class="form-error">{{ $message }}</div> @enderror
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <label class="toggle-label">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                                <span class="toggle-text">Active</span>
                            </label>
                        </div>
                    </div>

                    <div style="padding:1rem 1.5rem;border-top:1px solid var(--card-border);display:flex;gap:.5rem">
                        <button type="submit" class="btn btn-primary" style="flex:1">Save Entry</button>
                        <button type="button" @click="showForm = false" class="btn btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Empty right panel placeholder --}}
        <div x-show="!showForm" style="padding:2rem;text-align:center;color:var(--text-muted)">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" style="margin:0 auto 1rem;opacity:.3"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <div style="font-size:.875rem">Click "Add Entry" to create new knowledge base content</div>
        </div>
    </div>
</div>
@endsection
