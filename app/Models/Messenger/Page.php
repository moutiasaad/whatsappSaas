<?php

namespace App\Models\Messenger;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Messenger\Page
 *
 * A Facebook Page a tenant has connected as a Messenger channel. Mirrors
 * the shape of WebChat\Widget: per-tenant config row, on/off toggle,
 * encrypted access token, disconnect fields.
 *
 * The token is a Page-scoped access token obtained through the OAuth
 * flow in App\Http\Controllers\Admin\MessengerPageController (Phase 5).
 * When it came from a long-lived user token, it does not expire on its
 * own — only when the user revokes access or changes password. Graph API
 * signals invalidation with error code 190; we set `disconnected_at` and
 * `disconnect_reason` when that happens so the tenant sees why the
 * channel stopped instead of silently dropping messages.
 */
class Page extends Model
{
    use BelongsToTenant;

    protected $table = 'messenger_pages';

    protected $fillable = [
        'tenant_id',
        'page_id',
        'page_name',
        'access_token',
        'enabled',
        'subscribed_at',
        'disconnected_at',
        'disconnect_reason',
        'meta',
    ];

    protected $casts = [
        'tenant_id'         => 'integer',
        'enabled'           => 'boolean',
        'subscribed_at'     => 'datetime',
        'disconnected_at'   => 'datetime',
        'meta'              => 'array',
        // Laravel's 'encrypted' cast uses APP_KEY. A rotated APP_KEY
        // means every token becomes unreadable, matching how sessions
        // and webchat visitor tokens behave.
        'access_token'      => 'encrypted',
    ];

    protected $hidden = [
        // Never leak the token through a JSON serialization.
        'access_token',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isConnected(): bool
    {
        return $this->enabled && $this->disconnected_at === null;
    }
}
