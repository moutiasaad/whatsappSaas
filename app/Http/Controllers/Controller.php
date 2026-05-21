<?php

namespace App\Http\Controllers;

abstract class Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    protected function currentTenant(): \App\Models\Tenant
    {
        return \App\Models\Tenant::find(auth()->user()->tenant_id)
            ?? abort(403, __('ui.controller_messages.no_tenant_associated'));
    }
}
