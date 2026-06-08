<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOutgoingMessage;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OutboundConversationController extends Controller
{
    /**
     * Start a new outbound conversation: verify the number is on WhatsApp,
     * find or create customer + conversation, send the first message.
     *
     * POST /api/conversations/start
     * {
     *   "instance_id": 1,
     *   "phone":       "21612345678",
     *   "message":     "Hello!"
     * }
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'instance_id' => 'required|integer|exists:whatsapp_instances,id',
            'phone'       => ['required', 'string', 'regex:/^[0-9]{7,15}$/'],
            'message'     => 'required|string|max:4096',
        ]);

        // Ensure instance belongs to the user's tenant
        $instance = WhatsAppInstance::find($data['instance_id']);

        if (!$user->isSuperAdmin() && (int) $instance->tenant_id !== (int) $user->tenant_id) {
            return response()->json(['message' => 'Instance not found.'], 404);
        }

        if ($instance->status !== 'connected') {
            return response()->json([
                'message' => 'Instance is not connected. Please connect it first.',
                'code'    => 'instance_not_connected',
            ], 422);
        }

        $phone   = preg_replace('/\D+/', '', $data['phone']);
        $gateway = new EvolutionApiClient(
            $instance->effectiveGatewayUrl(),
            $instance->effectiveGatewayApiKey()
        );

        // Check the number is registered on WhatsApp
        try {
            $check = $gateway->checkNumbers($instance->gateway_instance_id, [$phone]);
            $valid = collect($check)->first(fn($r) => !empty($r['exists']) || !empty($r['jid']));

            if (!$valid) {
                return response()->json([
                    'message' => 'This phone number is not registered on WhatsApp.',
                    'code'    => 'number_not_on_whatsapp',
                ], 422);
            }
        } catch (\Exception $e) {
            // If check fails, continue — some gateway versions don't support it
        }

        // Find or create customer
        $customer = Customer::withoutGlobalScope('tenant')->firstOrCreate(
            ['tenant_id' => $instance->tenant_id, 'phone_e164' => $phone],
            ['first_contact_at' => now()]
        );

        // Find open conversation or create a new one
        $conversation = Conversation::withoutGlobalScope('tenant')
            ->where('instance_id', $instance->id)
            ->where('customer_id', $customer->id)
            ->whereIn('state', ['pool', 'claimed'])
            ->first();

        if (!$conversation) {
            $conversation = Conversation::withoutGlobalScope('tenant')->create([
                'tenant_id'       => $instance->tenant_id,
                'instance_id'     => $instance->id,
                'customer_id'     => $customer->id,
                'team_id'         => $instance->team_id,
                'state'           => 'claimed',
                'owner_agent_id'  => $user->id,
                'last_message_at' => now(),
            ]);
        }

        // Store the outbound message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'tenant_id'       => $conversation->tenant_id,
            'direction'       => 'out',
            'author_type'     => 'agent',
            'author_id'       => $user->id,
            'type'            => 'text',
            'body'            => $data['message'],
            'status'          => 'pending',
        ]);

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($data['message'], 200),
            'unread_count'         => 0,
        ]);

        // Dispatch to WhatsApp gateway
        SendOutgoingMessage::dispatch($message)->onQueue('whatsapp');

        return response()->json([
            'conversation' => $conversation->load(['customer', 'instance', 'team']),
            'message'      => $message->fresh(),
        ], 201);
    }

    /**
     * List conversations for the authenticated tenant with filters.
     * GET /api/conversations?tab=mine|pool|all|closed&search=...
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'tab'         => 'nullable|in:mine,pool,all,closed',
            'search'      => 'nullable|string|max:120',
            'team_id'     => 'nullable|integer',
            'instance_id' => 'nullable|integer',
            'state'       => 'nullable|in:pool,claimed,closed',
            'per_page'    => 'nullable|integer|min:1|max:100',
            'page'        => 'nullable|integer|min:1',
        ]);

        $tab = $data['tab'] ?? 'all';

        $query = Conversation::with(['customer', 'ownerAgent', 'instance', 'team'])
            ->where('tenant_id', $user->tenant_id);

        match ($tab) {
            'mine'   => $query->claimed()->where('owner_agent_id', $user->id),
            'pool'   => $query->pool(),
            'closed' => $query->closed(),
            default  => $query,
        };

        if ($user->isAgent() || $user->isSupervisor()) {
            $teamIds = $user->teams->pluck('id');
            $query->where(function ($q) use ($user, $teamIds) {
                $q->whereIn('team_id', $teamIds)->orWhereNull('team_id');
            });
        }

        if (!empty($data['team_id'])) {
            $query->where('team_id', (int) $data['team_id']);
        }

        if (!empty($data['instance_id'])) {
            $query->where('instance_id', (int) $data['instance_id']);
        }

        if (!empty($data['search'])) {
            $s = $data['search'];
            $query->where(function ($q) use ($s) {
                $q->whereHas('customer', fn($cq) => $cq->where('display_name', 'like', "%{$s}%")
                    ->orWhere('phone_e164', 'like', "%{$s}%"))
                  ->orWhere('last_message_preview', 'like', "%{$s}%");
            });
        }

        $query->orderByDesc('last_message_at');

        $perPage = min((int) ($data['per_page'] ?? 25), 100);
        $result  = $query->paginate($perPage);

        return response()->json($result);
    }

    /**
     * Delete a conversation and all its messages.
     * DELETE /api/conversations/{conversation}
     */
    public function destroy(Conversation $conversation): JsonResponse
    {
        $user = auth()->user();

        abort_unless(
            $user->isSuperAdmin() || (int) $conversation->tenant_id === (int) $user->tenant_id,
            403,
            'Conversation not found.'
        );

        $conversation->messages()->delete();
        $conversation->events()->delete();
        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }
}
