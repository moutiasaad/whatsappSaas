<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectSendController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'number'  => 'required|string',
            'message' => 'required|string|max:4096',
        ]);

        $user = auth()->user();

        $instance = WhatsAppInstance::where('tenant_id', $user->tenant_id)
            ->where('status', 'connected')
            ->orderBy('id')
            ->first();

        if (!$instance) {
            return response()->json([
                'message' => 'No connected WhatsApp instance found for your account.',
            ], 422);
        }

        try {
            $gateway = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );

            $result = $gateway->sendText(
                $instance->gateway_instance_id,
                $request->number,
                $request->message
            );

            return response()->json([
                'success'     => true,
                'instance_id' => $instance->id,
                'data'        => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
