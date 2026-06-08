<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppInstance;
use App\Services\Conversations\ConversationService;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DirectSendController extends Controller
{
    public function send(Request $request, ConversationService $convService): JsonResponse
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

            // Normalise the phone number the same way the gateway does
            $phone = preg_replace('/\D+/', '', $request->number);

            // Persist conversation + message so they appear in the web app
            $conversation = $convService->findOrCreateForIncoming(
                $instance,
                $phone,
                ''
            );

            // Reopen closed conversations when an outbound message is sent
            if ($conversation->isClosed()) {
                $conversation->update([
                    'state'          => 'pool',
                    'owner_agent_id' => null,
                    'claimed_at'     => null,
                    'last_message_at'=> now(),
                ]);
            } else {
                $conversation->update(['last_message_at' => now()]);
            }

            $externalId = data_get($result, 'key.id')
                       ?? data_get($result, 'id')
                       ?? null;

            Message::withoutGlobalScope('tenant')->create([
                'conversation_id'   => $conversation->id,
                'tenant_id'         => $instance->tenant_id,
                'direction'         => 'out',
                'author_type'       => 'system',
                'author_id'         => null,
                'external_message_id' => $externalId,
                'type'              => 'text',
                'body'              => $request->message,
                'status'            => 'sent',
                'sent_at'           => now(),
            ]);

            return response()->json([
                'success'         => true,
                'instance_id'     => $instance->id,
                'conversation_id' => $conversation->id,
                'data'            => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('DirectSendController: send failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
