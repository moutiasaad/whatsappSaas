<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;

class ConversationWebController extends Controller
{
    public function index()
    {
        return view('admin.conversations.index');
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->load(['customer', 'instance', 'ownerAgent', 'team']);

        $events = $conversation->events()->with('actor')->orderBy('created_at')->get();

        $teamAgents = $conversation->team
            ? $conversation->team->users()->where('role', 'agent')->where('is_active', true)->get()
            : User::where('role', 'agent')
                ->where('is_active', true)
                ->when(!auth()->user()->isSuperAdmin(), fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id))
                ->get();

        $customerConversationCount = Conversation::where('customer_id', $conversation->customer_id)->count();

        $aiSettings = auth()->user()->tenant?->aiSettings;
        $aiMode = $aiSettings?->mode ?? 'off';

        return view('admin.conversations.show', compact(
            'conversation', 'events', 'teamAgents', 'customerConversationCount', 'aiMode'
        ));
    }
}
