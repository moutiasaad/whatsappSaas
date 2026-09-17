<?php

namespace App\Models\Messenger;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Messenger\Message
 *
 * Mirrors WebChat\Message. Two Messenger-specific bits:
 *
 *   - `external_id` (Meta's `mid`) is unique-indexed to make ingest
 *     idempotent under Meta's webhook retries. When storing, use
 *     firstOrCreate on external_id.
 *
 *   - `attachments` is a first-class column separate from `body`
 *     because Messenger messages can carry no text (image-only,
 *     sticker, quick reply).
 */
class Message extends Model
{
    protected $table = 'messenger_messages';

    public const SENDER_VISITOR = 'visitor';
    public const SENDER_AGENT   = 'agent';
    public const SENDER_SYSTEM  = 'system';
    public const SENDER_BOT     = 'bot';

    protected $fillable = [
        'conversation_id', 'external_id',
        'sender_type', 'sender_id',
        'body', 'attachments', 'meta', 'read_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'sender_id'       => 'integer',
        'attachments'     => 'array',
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
