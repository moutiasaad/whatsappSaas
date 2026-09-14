{{-- Locale picker for the marketing surfaces. Posts back to the current URL so
     switching language keeps you on the page you were reading. --}}
<div class="lang-wrap">
    <button class="lang" type="button" aria-haspopup="true" aria-expanded="false" data-lang-btn>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 2.5c2.5 3 2.5 16 0 19M12 2.5c-2.5 3-2.5 16 0 19" stroke="currentColor" stroke-width="1.6"/></svg>
        {{ strtoupper(app()->getLocale()) }}
    </button>
    <div class="lang-dd" data-lang-dd>
        @foreach(config('locales.supported', []) as $code => $meta)
        <form method="POST" action="{{ route('locale.update') }}" style="margin:0">
            @csrf
            <input type="hidden" name="locale" value="{{ $code }}">
            <input type="hidden" name="redirect" value="{{ url()->full() }}">
            <button type="submit" class="lang-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $meta['native'] ?? $code }}</button>
        </form>
        @endforeach
    </div>
</div>
<script>
(function () {
    const btn = document.querySelector('[data-lang-btn]');
    const dd  = document.querySelector('[data-lang-dd]');
    if (!btn || !dd) return;
    btn.addEventListener('click', e => {
        e.stopPropagation();
        const open = dd.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', () => {
        dd.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
    });
})();
</script>
