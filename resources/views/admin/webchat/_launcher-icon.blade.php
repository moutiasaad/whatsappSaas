{{-- Launcher glyphs shared by the icon picker and the live preview bubble.
     Kept in one place so the tile and the mock can never drift apart. --}}
@php $s = $size ?? 20; @endphp
@switch($icon)
    @case('message')
        <svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none"><rect x="3" y="4.5" width="18" height="13" rx="2.6" stroke="currentColor" stroke-width="2"/><path d="M8 21l3-3.5h2L8 21z" fill="currentColor"/></svg>
        @break
    @case('help')
        <svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M9.5 9.5a2.6 2.6 0 015 .9c0 1.7-2.5 2-2.5 3.6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1.1" fill="currentColor"/></svg>
        @break
    @case('sparkle')
        <svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z" fill="currentColor"/><path d="M18.5 15l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2z" fill="currentColor" opacity=".65"/></svg>
        @break
    @default
        <svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 01-9 8.4 8.9 8.9 0 01-3.9-.9L3 20.5l1.5-4.6A8.4 8.4 0 013.6 11.5a8.4 8.4 0 018.4-8.4 8.4 8.4 0 019 8.4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
@endswitch
