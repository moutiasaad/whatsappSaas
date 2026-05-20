<?php

namespace App\Http\Controllers\Api;

use App\Events\InstanceStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstanceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(WhatsAppInstance::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:100',
            'gateway'        => 'required|in:evolution,waha,cloud',
            'gateway_url'    => 'required|url',
            'gateway_api_key'=> 'required|string',
        ]);

        $instance = WhatsAppInstance::create($request->only(['name', 'gateway', 'gateway_url', 'gateway_api_key']));

        return response()->json($instance, 201);
    }

    public function connect(WhatsAppInstance $instance): JsonResponse
    {
        try {
            $gateway = $this->gateway($instance);

            if (!$instance->gateway_instance_id) {
                $result = $gateway->createInstance($instance->name);
                $instance->update([
                    'gateway_instance_id' => $result['instance']['instanceId'] ?? $result['instanceName'] ?? $instance->name,
                ]);
            }

            $qr = $gateway->getQrCode($instance->gateway_instance_id);
            $instance->update(['qr_code' => $qr, 'status' => 'connecting', 'last_status_at' => now()]);

            return response()->json(['qr_code' => $qr, 'status' => 'connecting']);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(WhatsAppInstance $instance): JsonResponse
    {
        try {
            $status = $this->gateway($instance)->getStatus($instance->gateway_instance_id);
            $instance->update(['status' => $status, 'last_status_at' => now()]);
            broadcast(new InstanceStatusChanged($instance->fresh()));
            return response()->json(['status' => $status, 'instance' => $instance->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function logout(WhatsAppInstance $instance): JsonResponse
    {
        $this->gateway($instance)->logout($instance->gateway_instance_id);
        $instance->update(['status' => 'disconnected', 'qr_code' => null]);
        return response()->json(['message' => 'Logged out.']);
    }

    private function gateway(WhatsAppInstance $instance): EvolutionApiClient
    {
        return new EvolutionApiClient($instance->gateway_url, $instance->gateway_api_key);
    }
}
