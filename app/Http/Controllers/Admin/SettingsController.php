<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSettings;
use App\Models\AuditLog;
use App\Models\TenantPayment;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Workspace settings for a tenant admin.
 *
 * Every control on this page is bound to something the product actually reads:
 * the auto-close window drives the idle sweep (CloseIdleAiConversations), the
 * business hours and notification switches are stored on the tenant, and the
 * domain list gates invitations. Nothing here is decorative — a switch that
 * changes no behaviour is worse than an absent one.
 *
 * Automation lives on its own pages, not here: the AI agent under
 * /ai-settings and the reservations bot under /reservations/settings. Two
 * places writing the same AiSettings row is how one of them ends up stale.
 */
class SettingsController extends Controller
{
    /** Formats offered for dates and clocks, kept small on purpose. */
    private const DATE_FORMATS = ['d/m/Y', 'm/d/Y', 'Y-m-d', 'd M Y'];
    private const TIME_FORMATS = ['H:i', 'h:i A'];

    /** Auto-close presets, in minutes. 0 = never. */
    private const AUTO_CLOSE = [0, 15, 30, 60, 180, 360, 720, 1440, 4320, 10080];

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Notification switches the page offers, in display order. */
    public const NOTIFICATIONS = [
        'pool_new', 'unclaimed', 'ai_escalated', 'mentioned', 'daily_digest', 'product_updates',
    ];

    public function index()
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('super_admin.platform.global-settings');
        }

        $tenant = $this->currentTenant()->load('plan');
        $ai     = $tenant->aiSettings;

        return view('admin.settings.index', [
            'tenant'      => $tenant,
            'ai'          => $ai,
            'settings'    => (array) $tenant->settings,
            'dateFormats' => self::DATE_FORMATS,
            'timeFormats' => self::TIME_FORMATS,
            'autoClose'   => self::AUTO_CLOSE,
            'days'        => self::DAYS,
            'notifyKeys'  => self::NOTIFICATIONS,
            'timezones'   => \DateTimeZone::listIdentifiers(),
            'locales'     => config('locales.supported', []),

            // Read-only plan + usage figures for the Plan card.
            'usage'       => $this->usage($tenant, $ai),
            'lastPayment' => TenantPayment::where('tenant_id', $tenant->id)
                ->where('status', 'completed')->latest('paid_at')->first(),
        ]);
    }

    /**
     * The figures the Plan card reports. Seats include anything bought as an
     * add-on, so the number here matches what TenantQuota actually enforces.
     */
    private function usage($tenant, ?AiSettings $ai): array
    {
        $seatLimit = (int) ($tenant->plan?->max_users ?: 0) + (int) $tenant->extra_seats;
        $quota     = $ai ? $ai->monthly_message_quota : $tenant->plan?->ai_message_quota;

        return [
            'seats_used'   => User::where('tenant_id', $tenant->id)->count(),
            'seats_limit'  => $seatLimit,
            'extra_seats'  => (int) $tenant->extra_seats,
            'instances'    => WhatsAppInstance::where('tenant_id', $tenant->id)->count(),
            'ai_used'      => (int) ($ai?->ai_messages_used_this_period ?? 0),
            'ai_quota'     => $quota,
            'ai_credits'   => (int) ($ai?->extra_message_credits ?? 0),
        ];
    }

    public function update(Request $request)
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('super_admin.platform.global-settings');
        }

        $tenant = $this->currentTenant();

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:150'],
            'support_email'     => ['nullable', 'email', 'max:190'],
            'customer_name'     => ['nullable', 'string', 'max:150'],
            'reply_signature'   => ['nullable', 'string', 'max:300'],
            'timezone'          => ['required', 'timezone'],
            'locale'            => ['required', Rule::in(array_keys(config('locales.supported', [])))],
            'date_format'       => ['required', Rule::in(self::DATE_FORMATS)],
            'time_format'       => ['required', Rule::in(self::TIME_FORMATS)],
            'auto_close_minutes'=> ['required', 'integer', Rule::in(self::AUTO_CLOSE)],

            // Business hours: one open/close pair per weekday, each optional.
            'hours_enabled'     => ['nullable', 'boolean'],
            'hours'             => ['nullable', 'array'],
            'hours.*.open'      => ['nullable', 'date_format:H:i'],
            'hours.*.close'     => ['nullable', 'date_format:H:i'],
            'hours.*.closed'    => ['nullable', 'boolean'],

            'notify'            => ['nullable', 'array'],
            'notify.*'          => ['nullable', 'boolean'],

            'allowed_domains'   => ['nullable', 'string', 'max:500'],
        ]);

        $tenant->forceFill([
            'name'     => $data['name'],
            'timezone' => $data['timezone'],
            'settings' => array_replace_recursive((array) $tenant->settings, [
                // A `nullable` field the browser omitted entirely is absent
                // from $data, not empty — coalesce before trimming it.
                'workspace' => [
                    'support_email'   => ($data['support_email']   ?? null) ?: null,
                    'customer_name'   => ($data['customer_name']   ?? null) ?: null,
                    'reply_signature' => ($data['reply_signature'] ?? null) ?: null,
                ],
                'locale' => [
                    'default'     => $data['locale'],
                    'date_format' => $data['date_format'],
                    'time_format' => $data['time_format'],
                ],
                'conversations' => [
                    'auto_close_minutes' => (int) $data['auto_close_minutes'],
                ],
                'hours'  => $this->normaliseHours($request),
                'notify' => $this->normaliseNotifications($request),
                'security' => [
                    'allowed_domains' => $this->normaliseDomains($data['allowed_domains'] ?? null),
                ],
            ]),
        ])->save();

        AuditLog::record('tenant.settings.updated', $tenant, [
            'auto_close_minutes' => (int) $data['auto_close_minutes'],
        ]);

        return redirect()
            ->route(auth()->user()->routeNamePrefix() . '.settings.index')
            ->with('success', __('ui.controller_messages.settings_saved'));
    }

    /**
     * Business hours as {day => [open, close, closed]}.
     *
     * A day with no times, or one whose close is not after its open, is stored
     * as closed rather than as a window that can never match.
     */
    private function normaliseHours(Request $request): array
    {
        $out = ['enabled' => $request->boolean('hours_enabled'), 'days' => []];

        foreach (self::DAYS as $day) {
            $open   = $request->input("hours.$day.open");
            $close  = $request->input("hours.$day.close");
            $closed = $request->boolean("hours.$day.closed");

            $out['days'][$day] = ($closed || !$open || !$close || $close <= $open)
                ? ['closed' => true, 'open' => $open ?: null, 'close' => $close ?: null]
                : ['closed' => false, 'open' => $open, 'close' => $close];
        }

        return $out;
    }

    /** Notification switches, stored as a flat map of known keys only. */
    private function normaliseNotifications(Request $request): array
    {
        return collect(self::NOTIFICATIONS)
            ->mapWithKeys(fn ($k) => [$k => $request->boolean("notify.$k")])
            ->all();
    }

    /**
     * Allowed sign-in domains, lower-cased and stripped of any leading '@'.
     * An empty list means "no restriction" — never "nobody may join".
     */
    private function normaliseDomains(?string $raw): array
    {
        return collect(preg_split('/[\s,]+/', (string) $raw))
            ->map(fn ($d) => strtolower(ltrim(trim($d), '@')))
            ->filter(fn ($d) => $d !== '' && str_contains($d, '.'))
            ->unique()
            ->values()
            ->all();
    }
}
