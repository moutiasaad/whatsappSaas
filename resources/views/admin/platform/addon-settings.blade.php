@extends('layouts.admin')

@section('title', __('ui.platform_addons_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.platform_addons_page.breadcrumb_root') }}</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.platform_addons_page.breadcrumb') }}</span>
@endsection

@section('content')
@php $cur = $pricing['currency']; @endphp
<div x-data="addonPricing({
        seatPrice: {{ old('seat_price', $pricing['seat_price']) }},
        packPrice: {{ old('ai_pack_price', $pricing['pack_price']) }},
        packMessages: {{ old('ai_pack_messages', $pricing['pack_messages']) }},
        currency: @js($cur),
     })">
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.platform_addons_page.page_title') }}</div>
            <div class="page-subtitle">{{ __('ui.platform_addons_page.subtitle') }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('super_admin.platform.addons.update') }}">
        @csrf
        @method('PUT')

        <div class="ao-grid">

            {{-- ══════════ SEATS ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title"><i class="ri-team-line"></i>{{ __('ui.platform_addons_page.seats_title') }}</div>
                        <div class="card-subtitle">{{ __('ui.platform_addons_page.seats_subtitle') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <label class="ao-toggle">
                        <input type="checkbox" name="seats_enabled" value="1"
                               @checked(old('seats_enabled', $pricing['seats_enabled']))>
                        <span>{{ __('ui.platform_addons_page.on_sale') }}</span>
                    </label>
                    <p class="form-help" style="margin:-4px 0 16px">{{ __('ui.platform_addons_page.on_sale_help') }}</p>

                    <div class="form-group">
                        <label class="form-label" for="seat_price">{{ __('ui.platform_addons_page.seat_price', ['currency' => $cur]) }}</label>
                        <input id="seat_price" type="number" step="0.01" min="0" max="9999"
                               name="seat_price" x-model.number="seatPrice"
                               value="{{ old('seat_price', number_format($pricing['seat_price'], 2, '.', '')) }}"
                               class="form-control @error('seat_price') error @enderror">
                        <small class="form-help">{{ __('ui.platform_addons_page.seat_price_help') }}</small>
                        @error('seat_price') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="seat_max">{{ __('ui.platform_addons_page.seat_max') }}</label>
                        <input id="seat_max" type="number" step="1" min="1" max="500" name="seat_max"
                               value="{{ old('seat_max', $pricing['seat_max']) }}"
                               class="form-control @error('seat_max') error @enderror">
                        <small class="form-help">{{ __('ui.platform_addons_page.seat_max_help') }}</small>
                        @error('seat_max') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="ao-preview">
                        <i class="ri-eye-line"></i>
                        <span x-html="seatPreview"></span>
                    </div>
                </div>
            </div>

            {{-- ══════════ AI MESSAGE PACKS ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title"><i class="ri-sparkling-2-line"></i>{{ __('ui.platform_addons_page.packs_title') }}</div>
                        <div class="card-subtitle">{{ __('ui.platform_addons_page.packs_subtitle') }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <label class="ao-toggle">
                        <input type="checkbox" name="ai_packs_enabled" value="1"
                               @checked(old('ai_packs_enabled', $pricing['packs_enabled']))>
                        <span>{{ __('ui.platform_addons_page.on_sale') }}</span>
                    </label>
                    <p class="form-help" style="margin:-4px 0 16px">{{ __('ui.platform_addons_page.on_sale_help') }}</p>

                    <div class="form-group">
                        <label class="form-label" for="ai_pack_messages">{{ __('ui.platform_addons_page.pack_messages') }}</label>
                        <input id="ai_pack_messages" type="number" step="1" min="1" max="1000000"
                               name="ai_pack_messages" x-model.number="packMessages"
                               value="{{ old('ai_pack_messages', $pricing['pack_messages']) }}"
                               class="form-control @error('ai_pack_messages') error @enderror">
                        <small class="form-help">{{ __('ui.platform_addons_page.pack_messages_help') }}</small>
                        @error('ai_pack_messages') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ai_pack_price">{{ __('ui.platform_addons_page.pack_price', ['currency' => $cur]) }}</label>
                        <input id="ai_pack_price" type="number" step="0.01" min="0" max="9999"
                               name="ai_pack_price" x-model.number="packPrice"
                               value="{{ old('ai_pack_price', number_format($pricing['pack_price'], 2, '.', '')) }}"
                               class="form-control @error('ai_pack_price') error @enderror">
                        <small class="form-help">{{ __('ui.platform_addons_page.pack_price_help') }}</small>
                        @error('ai_pack_price') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ai_pack_max">{{ __('ui.platform_addons_page.pack_max') }}</label>
                        <input id="ai_pack_max" type="number" step="1" min="1" max="500" name="ai_pack_max"
                               value="{{ old('ai_pack_max', $pricing['pack_max']) }}"
                               class="form-control @error('ai_pack_max') error @enderror">
                        <small class="form-help">{{ __('ui.platform_addons_page.pack_max_help') }}</small>
                        @error('ai_pack_max') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="ao-preview">
                        <i class="ri-eye-line"></i>
                        <span x-html="packPreview"></span>
                    </div>
                </div>
            </div>

        </div>

        <div class="ao-note">
            <i class="ri-information-line"></i>
            <div>{{ __('ui.platform_addons_page.oneoff_note') }}</div>
        </div>

        <div class="ao-actions">
            <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i>{{ __('ui.save') }}</button>
        </div>
    </form>
</div>
<script>
/* Live preview of what a tenant will see, so the operator can price against
   the actual sentence rather than guessing from two number fields. */
function addonPricing(cfg) {
    return {
        seatPrice: cfg.seatPrice,
        packPrice: cfg.packPrice,
        packMessages: cfg.packMessages,
        currency: cfg.currency,

        b(v) { return '<b>' + v + '</b>'; },
        money(n) { return this.currency + ' ' + Number(n || 0).toFixed(2); },

        get seatPreview() {
            return @js(__('ui.platform_addons_page.seat_preview'))
                .replace(':price', this.b(this.money(this.seatPrice)))
                .replace(':five', this.b(this.money(this.seatPrice * 5)));
        },

        get packPreview() {
            const n = Number(this.packMessages) || 0;
            // Per-1,000 is the number that makes two different pack sizes
            // comparable; guard the divide so an empty field shows 0.00.
            const per = n > 0 ? (this.packPrice / n) * 1000 : 0;
            return @js(__('ui.platform_addons_page.pack_preview'))
                .replace(':n', this.b(n.toLocaleString()))
                .replace(':price', this.b(this.money(this.packPrice)))
                .replace(':per', this.b(this.money(per)));
        },
    };
}
</script>
@endsection

@push('styles')
<style>
    .ao-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; align-items: start; }
    .ao-toggle { display: flex; align-items: center; gap: 9px; font-size: 13.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; cursor: pointer; }
    .ao-toggle input { width: 16px; height: 16px; accent-color: var(--brand); cursor: pointer; }
    .ao-preview { display: flex; gap: 9px; align-items: flex-start; background: var(--brand-xlight); border: 1px solid var(--brand-light); border-radius: 11px; padding: 12px 14px; font-size: 12.5px; color: var(--brand-dark); line-height: 1.55; margin-top: 4px; }
    .ao-preview i { flex-shrink: 0; font-size: 15px; line-height: 1.35; }
    .ao-note { display: flex; gap: 9px; align-items: flex-start; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 14px 16px; font-size: 13px; color: var(--text-secondary); line-height: 1.55; margin-top: 16px; }
    .ao-note i { flex-shrink: 0; color: var(--brand); font-size: 16px; line-height: 1.35; }
    .ao-actions { display: flex; justify-content: flex-end; margin-top: 16px; }
</style>
@endpush
