{{-- Google Tag Manager — immediately after <body>.
     The noscript iframe is the only part that has to sit in the body, and it
     only does anything for visitors with JavaScript off. --}}
@php($gtmId = config('services.gtm.id', 'GTM-WS2XL7ZL'))
@if($gtmId)
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@endif
