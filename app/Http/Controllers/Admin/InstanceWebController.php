<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;

class InstanceWebController extends Controller
{
    public function index()
    {
        $instances = WhatsAppInstance::orderBy('name')->get();
        return view('admin.instances.index', compact('instances'));
    }

    public function create()
    {
        $teams = Team::where('is_active', true)->orderBy('name')->get();
        return view('admin.instances.create', compact('teams'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'gateway'        => 'required|in:evolution_api,waha,cloud_api',
            'gateway_url'    => 'required|url',
            'gateway_api_key'=> 'required|string',
            'team_id'        => 'nullable|exists:teams,id',
        ]);

        $instance = WhatsAppInstance::create($data + ['tenant_id' => auth()->user()->tenant_id]);

        AuditLog::record('instance.created', $instance);

        return redirect()->route('admin.instances.index')
            ->with('success', "Instance \"{$instance->name}\" created.");
    }

    public function edit(WhatsAppInstance $instance)
    {
        $teams = Team::where('is_active', true)->orderBy('name')->get();
        return view('admin.instances.edit', compact('instance', 'teams'));
    }

    public function update(Request $request, WhatsAppInstance $instance)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'gateway_url'    => 'required|url',
            'gateway_api_key'=> 'nullable|string',
            'team_id'        => 'nullable|exists:teams,id',
        ]);

        if (empty($data['gateway_api_key'])) {
            unset($data['gateway_api_key']);
        }

        $instance->update($data);
        AuditLog::record('instance.updated', $instance);

        return redirect()->route('admin.instances.index')
            ->with('success', 'Instance updated.');
    }

    public function destroy(WhatsAppInstance $instance)
    {
        AuditLog::record('instance.deleted', $instance, ['name' => $instance->name]);
        $instance->delete();
        return redirect()->route('admin.instances.index')
            ->with('success', "Instance \"{$instance->name}\" deleted.");
    }
}
