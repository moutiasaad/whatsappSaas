<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'conversation_id', 'tenant_id', 'direction', 'author_type', 'author_id',
        'external_message_id', 'type', 'body', 'media_url', 'media_mime',
        'media_filename', 'status', 'ai_metadata', 'sent_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'tenant_id'       => 'integer',
        'author_id'       => 'integer',
        'ai_metadata'     => 'array',
        'sent_at'         => 'datetime',
    ];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }

    public function isInbound(): bool { return $this->direction === 'in'; }
    public function isOutbound(): bool { return $this->direction === 'out'; }
    public function isFromAi(): bool { return $this->author_type === 'ai'; }
    public function isNote(): bool { return ($this->ai_metadata['is_note'] ?? false) === true; }
}
