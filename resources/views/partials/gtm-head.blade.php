{{-- Google Tag Manager — <head>.
     Must be as high in the head as possible: the container loads
     asynchronously, but dataLayer has to exist before anything on the page
     pushes to it.

     The id has a default in config/services.php rather than living only in
     .env, because the two hosts have separate .env files and only one of
     them is reachable from a deploy. GTM_ID still overrides it per host if
     the sites ever need different containers.

     The literal second argument is the same id again, and it is not
     redundant: config() falls back to it only when the KEY IS ABSENT, which
     is exactly what a box running a config cache built before services.gtm
     existed looks like. Without it the include renders nothing there and the
     tag is silently missing on that host — which is how this shipped the
     first time. An explicit empty GTM_ID still switches the tags off,
     because then the key exists and holds an empty string. --}}
@php($gtmId = config('services.gtm.id', 'GTM-WS2XL7ZL'))
@if($gtmId)
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $gtmId }}');</script>
<!-- End Google Tag Manager -->
@endif
