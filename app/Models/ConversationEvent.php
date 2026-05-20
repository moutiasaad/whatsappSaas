<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'type', 'actor_id', 'payload', 'created_at'];
    protected $casts = [
        'conversation_id' => 'integer',
        'actor_id'        => 'integer',
        'payload'         => 'array',
        'created_at'      => 'datetime',
    ];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
