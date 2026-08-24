<?php

namespace App\Models\WebChat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'webchat_messages';

    public const SENDER_VISITOR = 'visitor';
    public const SENDER_AGENT   = 'agent';
    public const SENDER_SYSTEM  = 'system';
    public const SENDER_BOT     = 'bot';

    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_id', 'body', 'meta', 'read_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'sender_id'       => 'integer',
        'meta'            => 'array',
        'read_at'         => 'datetime',
    ];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class, 'conversation_id'); }
    public function sender(): BelongsTo       { return $this->belongsTo(User::class, 'sender_id'); }

    public function isFromVisitor(): bool { return $this->sender_type === self::SENDER_VISITOR; }
    public function isFromAgent(): bool   { return $this->sender_type === self::SENDER_AGENT; }
    public function isSystem(): bool      { return $this->sender_type === self::SENDER_SYSTEM; }
    public function isFromBot(): bool     { return $this->sender_type === self::SENDER_BOT; }
}
