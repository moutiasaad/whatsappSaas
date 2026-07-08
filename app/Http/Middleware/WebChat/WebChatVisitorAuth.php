<?php

namespace App\Http\Middleware\WebChat;

use App\Models\WebChat\Visitor;
use App\Models\WebChat\Widget;
use Closure;
use Illuminate\Http\Request;

class WebChatVisitorAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Widget|null $widget */
        $widget = $request->attributes->get('webchat_widget');

        if (!$widget) {
            abort(500, 'webchat_widget_not_resolved');
        }

        $token = $request->bearerToken()
            ?: $request->header('X-WebChat-Visitor-Token')
            ?: $request->input('visitor_token');

        if (!$token) {
            abort(401, 'webchat_visitor_token_missing');
        }

        $visitor = Visitor::withoutGlobalScope('tenant')
            ->where('token', $token)
            ->where('tenant_id', $widget->tenant_id)
            ->where('widget_id', $widget->id)
            ->first();

        if (!$visitor) {
            abort(401, 'webchat_visitor_token_invalid');
        }

        $visitor->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set('webchat_visitor', $visitor);

        return $next($request);
    }
}
