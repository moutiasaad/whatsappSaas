<?php

namespace App\Http\Middleware\WebChat;

use App\Models\WebChat\Widget;
use Closure;
use Illuminate\Http\Request;

class ResolveWebChatWidget
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = $request->route('key') ?: $request->header('X-WebChat-Key');

        if (!$key) {
            abort(404, 'webchat_widget_not_found');
        }

        $widget = Widget::withoutGlobalScope('tenant')
            ->where('public_key', $key)
            ->where('enabled', true)
            ->first();

        if (!$widget) {
            abort(404, 'webchat_widget_not_found');
        }

        app()->instance('current_tenant_id', $widget->tenant_id);
        $request->attributes->set('webchat_widget', $widget);

        return $next($request);
    }
}
