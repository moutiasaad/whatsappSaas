@extends('layouts.admin')

@section('title', __('ui.instance_edit_page.title'))

@section('breadcrumb')
    <a href="{{ route('admin.instances.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.instances_page.instances') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ $instance->name }}</span>
@endsection

@section('content')
<form action="{{ route('admin.instances.update', $instance) }}" method="POST" data-unsaved data-loading>
    @csrf
    @method('PUT')

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">
        <div class="card" style="overflow:visible">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.instance_edit_page.card_title') }}</div>
                    <div class="card-subtitle">{{ __('ui.instance_edit_page.card_subtitle', ['name' => $instance->name]) }}</div>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem">
                    <span class="status-dot {{ $instance->statusColor }}"></span>
                    @php $statusLabels = __('ui.instances_page.status_labels'); @endphp
                    <span style="font-size:.875rem;color:var(--text-secondary)">{{ $statusLabels[$instance->status] ?? ucfirst($instance->status) }}</span>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">
                <div class="form-group">
                    <label class="form-label" for="name">{{ __('ui.instance_edit_page.instance_name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $instance->name) }}" class="form-control @error('name') error @enderror">
                    @error('name') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="team_id">{{ __('ui.instance_edit_page.assigned_team') }}</label>
                    <select id="team_id" name="team_id" class="form-control @error('team_id') error @enderror">
                        <option value="">{{ __('ui.instance_edit_page.no_team') }}</option>
                        @foreach($teams as $team)
                        <option value="{{ $team->id }}" {{ old('team_id', $instance->team_id ?? '') == $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
                        @endforeach
                    </select>
                    @error('team_id') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.5rem">
            @if($instance->phone_number)
            <div class="card">
                <div style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.875rem">
                    <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0">
                        <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:.75rem;font-weight:600;color:var(--brand);text-transform:uppercase;letter-spacing:.04em">{{ __('ui.instance_edit_page.connected_number') }}</div>
                        <div style="font-size:.9375rem;font-weight:600;color:var(--text-primary);margin-top:.125rem">{{ $instance->phone_number }}</div>
                    </div>
                </div>
            </div>
            @endif

            @if(in_array($instance->status, ['connected', 'qr_pending', 'connecting']))
            <div class="card">
                <div style="padding:1rem 1.25rem;border-left:3px solid #f59e0b;border-radius:0 var(--radius-lg) var(--radius-lg) 0">
                    <div style="font-size:.875rem;font-weight:600;color:#f59e0b;margin-bottom:.375rem">{{ __('ui.instance_edit_page.disconnect_zone') }}</div>
                    <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.875rem">{{ __('ui.instance_edit_page.disconnect_message') }}</div>
                    <button type="button" id="disconnectInstanceBtn" class="btn btn-outline btn-sm" style="border-color:#f59e0b;color:#f59e0b">
                        <i class="ri-logout-box-r-line"></i> {{ __('ui.instance_edit_page.disconnect_button') }}
                    </button>
                </div>
            </div>
            @endif

            <div class="card">
                <div style="padding:1rem 1.25rem;border-left:3px solid #ef4444;border-radius:0 var(--radius-lg) var(--radius-lg) 0">
                    <div style="font-size:.875rem;font-weight:600;color:#ef4444;margin-bottom:.375rem">{{ __('ui.instance_edit_page.danger_zone') }}</div>
                    <div style="font-size:.8125rem;color:var(--text-muted);margin-bottom:.875rem">{{ __('ui.instance_edit_page.delete_message') }}</div>
                    <button type="button" onclick="confirmDelete('{{ route('admin.instances.destroy', $instance) }}', { title: @js(__('ui.instance_edit_page.delete_prompt', ['name' => $instance->name])), message: @js(__('ui.instance_edit_page.delete_warning')) })" class="btn btn-danger btn-sm">{{ __('ui.instance_edit_page.delete_instance') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.instances.index') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('ui.instance_edit_page.save_changes') }}</button>
    </div>
</form>

@if(in_array($instance->status, ['connected', 'qr_pending', 'connecting']))
<script>
(function () {
    const btn = document.getElementById('disconnectInstanceBtn');
    if (!btn) return;

    btn.addEventListener('click', () => {
        window.confirmDelete(null, {
            title: @json(__('ui.instance_edit_page.disconnect_prompt', ['name' => $instance->name])),
            message: @json(__('ui.instance_edit_page.disconnect_warning')),
            callback: async () => {
                btn.disabled = true;
                try {
                    const res = await fetch(@json("/api/instances/{$instance->id}/logout"), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                    });
                    if (!res.ok) throw new Error();
                    window.showToast?.('success', @json(__('ui.instance_edit_page.disconnect_success')));
                    setTimeout(() => window.location.reload(), 700);
                } catch {
                    window.showToast?.('error', @json(__('ui.instance_edit_page.disconnect_error')));
                    btn.disabled = false;
                }
            },
        });
    });
})();
</script>
@endif
@endsection
