@if ($paginator->hasPages())
<div style="display:flex;align-items:center;justify-content:space-between;font-size:.875rem">
    <div style="color:var(--text-muted)">
        Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
    </div>
    <div style="display:flex;gap:.25rem">
        {{-- Prev --}}
        @if($paginator->onFirstPage())
            <span style="padding:.375rem .625rem;border:1px solid var(--card-border);border-radius:.5rem;color:var(--text-muted);cursor:not-allowed">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               style="padding:.375rem .625rem;border:1px solid var(--card-border);border-radius:.5rem;color:var(--text-secondary);text-decoration:none;transition:all .15s"
               onmouseenter="this.style.borderColor='var(--brand)';this.style.color='var(--brand)'"
               onmouseleave="this.style.borderColor='var(--card-border)';this.style.color='var(--text-secondary)'">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
        @endif

        {{-- Page Numbers --}}
        @foreach($elements as $element)
            @if(is_string($element))
                <span style="padding:.375rem .5rem;color:var(--text-muted)">…</span>
            @endif
            @if(is_array($element))
                @foreach($element as $page => $url)
                    @if($page == $paginator->currentPage())
                        <span style="padding:.375rem .625rem;background:var(--brand);color:#fff;border-radius:.5rem;font-weight:600;min-width:2rem;text-align:center">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}"
                           style="padding:.375rem .625rem;border:1px solid var(--card-border);border-radius:.5rem;color:var(--text-secondary);text-decoration:none;min-width:2rem;text-align:center;transition:all .15s"
                           onmouseenter="this.style.borderColor='var(--brand)';this.style.color='var(--brand)'"
                           onmouseleave="this.style.borderColor='var(--card-border)';this.style.color='var(--text-secondary)'">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               style="padding:.375rem .625rem;border:1px solid var(--card-border);border-radius:.5rem;color:var(--text-secondary);text-decoration:none;transition:all .15s"
               onmouseenter="this.style.borderColor='var(--brand)';this.style.color='var(--brand)'"
               onmouseleave="this.style.borderColor='var(--card-border)';this.style.color='var(--text-secondary)'">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </a>
        @else
            <span style="padding:.375rem .625rem;border:1px solid var(--card-border);border-radius:.5rem;color:var(--text-muted);cursor:not-allowed">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </span>
        @endif
    </div>
</div>
@endif
