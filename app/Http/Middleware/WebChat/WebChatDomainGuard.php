<?php

namespace App\Http\Middleware\WebChat;

use App\Models\WebChat\Widget;
use Closure;
use Illuminate\Http\Request;

class WebChatDomainGuard
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Widget|null $widget */
        $widget = $request->attributes->get('webchat_widget');

        if (!$widget) {
            abort(500, 'webchat_widget_not_resolved');
        }

        $origin = $request->header('Origin') ?: $request->header('Referer');

        if (!$widget->isDomainAllowed($origin)) {
            abort(403, 'webchat_domain_not_allowed');
        }

        return $next($request);
    }
}
