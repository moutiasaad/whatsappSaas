<?php

namespace App\Models\WebChat;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Visitor extends Model
{
    use BelongsToTenant;

    protected $table = 'webchat_visitors';

    protected $fillable = [
        'tenant_id', 'widget_id', 'token', 'name', 'email',
        'attributes', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'tenant_id'     => 'integer',
        'widget_id'     => 'integer',
        'attributes'    => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->token)) {
                $model->token = Str::random(48);
            }
            if (empty($model->first_seen_at)) {
                $model->first_seen_at = now();
            }
            if (empty($model->last_seen_at)) {
                $model->last_seen_at = $model->first_seen_at ?? now();
            }
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function widget(): BelongsTo { return $this->belongsTo(Widget::class, 'widget_id'); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class, 'visitor_id'); }
}
