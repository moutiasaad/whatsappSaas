{{-- Wavadesk web-chat widget (dogfood: wavadesk answering questions about wavadesk).
     Loaded on every public + panel page via each layout's pre-</body> hook.

     Cache-bust uses this box's local widget.js mtime rather than a hardcoded
     number: same repo ships to both marketing and core, so a widget.js edit
     that lands via git auto-invalidates the CDN copy on the next page load.
     `1` fallback covers the (very unlikely) case where the file isn't on disk
     yet on a fresh deploy — the browser will still cache-bust once. --}}
@php
    $wavadeskWidgetVersion = @filemtime(public_path('webchat/widget.js')) ?: 1;
@endphp
<script>window.WavadeskChat = { key: "wck_j23t4v02CAG8dnXBdTqE0zaBpdVhbP6pfHmDYiqydp5b2G0P" };</script>
<script src="https://app.wavadesk.com/webchat/widget.js?v={{ $wavadeskWidgetVersion }}" async></script>
