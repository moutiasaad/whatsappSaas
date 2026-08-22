<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function __construct(private OtpService $service) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['ok' => false, 'error' => 'no_tenant'], 422);
        }

        $result = $this->service->send($tenant, $data['phone']);

        return response()->json($result, $result['ok'] ? 200 : ($result['status'] ?? 400));
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code'  => ['required', 'string', 'max:16'],
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['ok' => false, 'error' => 'no_tenant'], 422);
        }

        $result = $this->service->verify($tenant, $data['phone'], $data['code']);

        return response()->json($result, $result['ok'] ? 200 : ($result['status'] ?? 400));
    }
}
