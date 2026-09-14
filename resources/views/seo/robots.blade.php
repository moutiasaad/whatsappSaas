User-agent: *
Allow: /
@php
// Application surfaces have nothing to rank and should not burn crawl budget.
$disallow = ['/tenant-admin/', '/admin-control-panel/', '/supervisor/', '/agent/',
             '/api/', '/payment/', '/webchat/', '/login', '/register', '/superadmin/'];
@endphp
@foreach($disallow as $path)
Disallow: {{ $path }}
@endforeach

Sitemap: {{ $sitemap }}
