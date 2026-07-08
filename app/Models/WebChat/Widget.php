<?php

namespace App\Models\WebChat;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Widget extends Model
{
    use BelongsToTenant;

    protected $table = 'webchat_widgets';

    protected $fillable = [
        'tenant_id', 'public_key', 'name', 'enabled',
        'welcome_message', 'suggestions', 'pre_chat_ask_email',
        'offline_message', 'theme_color', 'position', 'launcher_text',
        'allowed_domains',
    ];

    protected $casts = [
        'tenant_id'          => 'integer',
        'enabled'            => 'boolean',
        'pre_chat_ask_email' => 'boolean',
        'suggestions'        => 'array',
        'allowed_domains'    => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->public_key)) {
                $model->public_key = 'wck_' . Str::random(48);
            }
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function visitors(): HasMany { return $this->hasMany(Visitor::class, 'widget_id'); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class, 'widget_id'); }

    public function isDomainAllowed(?string $origin): bool
    {
        $list = $this->allowed_domains ?? [];
        if (empty($list)) return true;
        if (!$origin) return false;

        $originHost = parse_url($origin, PHP_URL_HOST) ?: $origin;

        foreach ($list as $entry) {
            $entryHost = parse_url($entry, PHP_URL_HOST) ?: $entry;
            if (strcasecmp($originHost, $entryHost) === 0) return true;
        }
        return false;
    }
}
