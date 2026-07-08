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
        $token = $request->bearerToken()
            ?: $request->header('X-WebChat-Visitor-Token')
            ?: $request->input('visitor_token');

        if (!$token) {
            abort(401, 'webchat_visitor_token_missing');
        }

        $visitor = Visitor::withoutGlobalScope('tenant')
            ->where('token', $token)
            ->first();

        if (!$visitor) {
            abort(401, 'webchat_visitor_token_invalid');
        }

        /** @var Widget|null $widget */
        $widget = $request->attributes->get('webchat_widget');

        if ($widget) {
            // A widget was resolved upstream (e.g. via {key} in the path).
            // The visitor's token must belong to that widget's tenant + widget.
            if ($visitor->tenant_id !== $widget->tenant_id
                || $visitor->widget_id !== $widget->id) {
                abort(401, 'webchat_visitor_token_invalid');
            }
        } else {
            // No widget context (e.g. /api/webchat/broadcasting/auth). Resolve
            // the widget from the visitor so downstream code can rely on both
            // request attributes being set.
            $widget = Widget::withoutGlobalScope('tenant')
                ->where('id', $visitor->widget_id)
                ->where('enabled', true)
                ->first();

            if (!$widget) {
                abort(401, 'webchat_visitor_token_invalid');
            }

            app()->instance('current_tenant_id', $widget->tenant_id);
            $request->attributes->set('webchat_widget', $widget);
        }

        $visitor->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set('webchat_visitor', $visitor);

        return $next($request);
    }
}
