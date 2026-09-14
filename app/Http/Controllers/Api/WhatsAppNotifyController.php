<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notify\WhatsAppNotifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppNotifyController extends Controller
{
    public function __construct(private WhatsAppNotifyService $service) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['ok' => false, 'error' => 'no_tenant'], 422);
        }

        $result = $this->service->send($tenant, $data['message']);

        return response()->json($result, $result['ok'] ? 200 : ($result['status'] ?? 400));
    }
}
