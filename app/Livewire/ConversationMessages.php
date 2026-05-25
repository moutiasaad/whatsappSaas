<?php

namespace App\Livewire;

use App\Models\Message;
use Livewire\Attributes\On;
use Livewire\Component;

class ConversationMessages extends Component
{
    public int $conversationId;
    public string $customerInitials = '??';
    public int $perPage = 40;
    public bool $hasMore = false;

    #[On('messages-refresh')]
    public function refresh(): void {}

    public function loadMore(): void
    {
        $this->perPage += 40;
    }

    public function render()
    {
        $total = Message::where('conversation_id', $this->conversationId)->count();
        $this->hasMore = $total > $this->perPage;

        $messages = Message::where('conversation_id', $this->conversationId)
            ->orderBy('id', 'desc')
            ->limit($this->perPage)
            ->get()
            ->reverse()
            ->values();

        return view('livewire.conversation-messages', ['messages' => $messages]);
    }
}
