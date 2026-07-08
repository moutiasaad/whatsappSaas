<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Web Live-Chat module

Embeddable chat widget that tenants install on their marketing sites. Operates alongside the WhatsApp module — same dashboard, separate database tables, separate broadcast channels.

### Embed snippet

Each tenant gets a public widget key (`wck_…`) from **Live Chat Settings** in the dashboard. The settings page shows a copy-paste snippet like:

```html
<script>window.WavadeskChat = { key: "wck_YOUR_TENANT_KEY_HERE" };</script>
<script src="https://your-app.example/webchat/widget.js" async></script>
```

The widget is a plain committed asset — no build step. It talks to `/api/webchat/*` (CORS-scoped separately in `config/cors.php`) and opens a Pusher-protocol WebSocket to Reverb.

### Environment variables

| Var | Default | Purpose |
|-----|---------|---------|
| `WEBCHAT_MESSAGE_MAX_LENGTH` | `4000` | Max length for both visitor and agent messages |
| `WEBCHAT_RELEASE_STALE_MINUTES` | `15` | Idle-claim threshold before `webchat:release-stale` reclaims |
| `WEBCHAT_WIDGET_POLL_MS` | `4000` | Widget's HTTP poll fallback interval |
| `WEBCHAT_RL_SESSION` | `60` | Session-endpoint rate limit per minute |
| `WEBCHAT_RL_MESSAGES` | `40` | Message-endpoint rate limit per minute |

Reverb settings (`REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`) are picked up from `config/broadcasting.php` and echoed to the widget in the `/session` response, so the same asset runs against local `http://` and prod `https://` without a rebuild.

### Artisan commands

- `php artisan webchat:release-stale` — releases conversations left claimed but idle beyond the threshold. Wired to run every minute in `routes/console.php`. Requires `php artisan schedule:work` (dev) or a system cron entry running `schedule:run` every minute (prod).
- `php artisan webchat:provision-widget {tenant}` — creates or reveals the widget key for a tenant from CLI.

### Allowed domains

Each widget has an `allowed_domains` list (JSON) enforced by `WebChatDomainGuard` on every request. An empty list means "any origin" (useful during development). Populate it via the Settings page before going public.

### Real-time

Live updates use Laravel Reverb. Run `php artisan reverb:start` alongside `queue:work`. Broadcast channels:

- `webchat.tenant.{tenantId}` — presence, one per tenant, for the agent inbox
- `webchat.conversation.{uuid}` — private, one per conversation, for the visitor widget and any observing agent

If Reverb is not running the widget silently falls back to HTTP polling at the interval above.

### Local cross-origin test

The widget is intended to embed on a domain other than the app's. To exercise CORS locally:

```bash
mkdir -p storage/logs/embed-test
cat > storage/logs/embed-test/index.html <<'HTML'
<script>window.WavadeskChat = { key: "wck_..." };</script>
<script src="http://127.0.0.1:8000/webchat/widget.js" async></script>
HTML
cd storage/logs/embed-test && php -S 127.0.0.1:9000
```

Open `http://127.0.0.1:9000/` while the Laravel app is running on `:8000`.

### Tests

`vendor/bin/phpunit tests/Feature/WebChatLifecycleTest.php` covers the visitor→agent lifecycle: session bootstrap, bot→pending promotion, atomic claim (double-claim returns 409), reply guard, close, cross-tenant isolation, and the release-stale command. The test builds its own schema in setup because the wider migration set is MySQL-specific.

