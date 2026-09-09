<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySlot;
use App\Models\Reservation;
use App\Models\ReservationSetting;
use App\Models\WhatsAppInstance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    // PROC-021: legal status transitions. Any status can only move to the ones
    // listed here; 'completed' is terminal. Moves back into a consuming state
    // (pending/confirmed) also require capacity to still be free — capacity is
    // defined as the count of pending + confirmed rows, so reinstating a
    // cancelled row can silently push a full slot to max_bookings + 1.
    private const ALLOWED_TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'cancelled' => ['pending', 'confirmed'],
        'completed' => [],
    ];

    private const CONSUMING_STATES = ['pending', 'confirmed'];

    // Bulk-generation caps. A year of daily windows is a normal season;
    // past that an operator has almost certainly mis-picked a year, and
    // the row cap keeps one careless submit from writing tens of
    // thousands of slots.
    private const BULK_MAX_DAYS = 366;
    private const BULK_MAX_ROWS = 2000;

    private function assertEnabled(): void
    {
        $tenant = auth()->user()->tenant;
        abort_unless(
            $tenant && $tenant->plan && $tenant->plan->reservations_enabled,
            403,
            'Reservations module not included in your plan.'
        );
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->assertEnabled();
        $tenantId = auth()->user()->tenant_id;

        if ($request->expectsJson()) {
            return $this->jsonIndex($request, $tenantId);
        }

        $setting = ReservationSetting::where('tenant_id', $tenantId)->first();
        $stats   = $this->stats($tenantId);

        return view('admin.reservations.index', compact('setting', 'stats'));
    }

    private function jsonIndex(Request $request, int $tenantId)
    {
        $query = Reservation::where('tenant_id', $tenantId)
            ->with('slot')
            ->orderByDesc('reservation_date')
            ->orderByDesc('start_time');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('reservation_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('reservation_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(fn($q) => $q->where('customer_name', 'like', $search)
                                      ->orWhere('customer_phone', 'like', $search));
        }

        $paginated = $query->paginate((int) $request->get('per_page', 25));

        return response()->json(array_merge($paginated->toArray(), [
            'stats' => $this->stats($tenantId),
        ]))->withHeaders(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    private function stats(int $tenantId): array
    {
        // CALC-010: "today" must be the tenant's local calendar day, not UTC.
        // Between 00:00 and 01:00 in a UTC+1 tenant's clock, today() in UTC
        // was still yesterday, so bookings dated for that day fell out of
        // the counter until an hour into the day.
        $tenant = \App\Models\Tenant::find($tenantId);
        $tz     = $tenant?->effectiveTimezone() ?? config('app.timezone', 'UTC');
        $today  = now($tz)->toDateString();

        $base = Reservation::where('tenant_id', $tenantId);
        return [
            'total'     => (clone $base)->count(),
            'today'     => (clone $base)->whereDate('reservation_date', $today)->count(),
            'upcoming'  => (clone $base)->whereIn('status', ['confirmed', 'pending'])
                                        ->whereDate('reservation_date', '>=', $today)->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];
    }

    public function show(Reservation $reservation)
    {
        $this->assertEnabled();
        abort_unless($reservation->tenant_id === auth()->user()->tenant_id, 403);

        $reservation->load(['slot', 'customer', 'conversation.instance']);

        return view('admin.reservations.show', compact('reservation'));
    }

    // ── Status update ─────────────────────────────────────────────────────────

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $this->assertEnabled();
        abort_unless($reservation->tenant_id === auth()->user()->tenant_id, 403);

        $request->validate(['status' => 'required|in:confirmed,cancelled,completed,pending']);
        $target  = $request->status;
        $current = $reservation->status;

        if ($current === $target) {
            return response()->json(['message' => __('ui.controller_messages.reservation_status_unchanged'), 'reservation' => $reservation]);
        }

        // PROC-021: enforce the transition table. Blocks completed -> anything
        // (a past appointment cannot become pending again) and refuses any
        // unlisted move rather than accepting the vocabulary alone.
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];
        if (!in_array($target, $allowed, true)) {
            return response()->json([
                'message' => __('ui.controller_messages.reservation_invalid_transition', ['from' => $current, 'to' => $target]),
                'allowed_next_states' => $allowed,
            ], 422);
        }

        $wasConsuming = in_array($current, self::CONSUMING_STATES, true);
        $willConsume  = in_array($target, self::CONSUMING_STATES, true);

        // Only check capacity when the move ADDS to it (cancelled/completed
        // -> pending/confirmed). pending<->confirmed doesn't change count.
        if (!$wasConsuming && $willConsume) {
            $updated = DB::transaction(function () use ($reservation, $target) {
                // Lock the slot row + the reservations on that date so a
                // concurrent PATCH from another admin browser cannot both
                // push past max_bookings. Same shape as ReservationBotService
                // completeBooking (PROC-020).
                $slot = AvailabilitySlot::whereKey($reservation->slot_id)->lockForUpdate()->first();
                if (!$slot) {
                    return null;
                }

                $consuming = Reservation::where('slot_id', $slot->id)
                    ->whereDate('reservation_date', $reservation->reservation_date)
                    ->whereIn('status', self::CONSUMING_STATES)
                    ->where('id', '!=', $reservation->id)
                    ->lockForUpdate()
                    ->count();

                if ($consuming >= $slot->max_bookings) {
                    return null;
                }

                $reservation->update(['status' => $target]);
                return $reservation->fresh();
            });

            if (!$updated) {
                return response()->json([
                    'message' => __('ui.controller_messages.reservation_slot_full'),
                ], 422);
            }

            return response()->json(['message' => __('ui.controller_messages.reservation_status_updated'), 'reservation' => $updated]);
        }

        $reservation->update(['status' => $target]);
        return response()->json(['message' => __('ui.controller_messages.reservation_status_updated'), 'reservation' => $reservation->fresh()]);
    }

    public function destroy(Reservation $reservation)
    {
        $this->assertEnabled();
        abort_unless($reservation->tenant_id === auth()->user()->tenant_id, 403);
        $reservation->delete();
        return response()->json(['message' => __('ui.controller_messages.reservation_deleted')]);
    }

    // ── Availability Slots ────────────────────────────────────────────────────

    public function slots(Request $request)
    {
        $this->assertEnabled();
        $tenantId = auth()->user()->tenant_id;

        if ($request->expectsJson()) {
            $slots = AvailabilitySlot::where('tenant_id', $tenantId)
                ->orderByRaw("FIELD(type,'recurring','specific')")
                ->orderBy('day_of_week')
                ->orderBy('specific_date')
                ->orderBy('start_time')
                ->get();

            return response()->json(['data' => $slots]);
        }

        return view('admin.reservations.slots');
    }

    public function storeSlot(Request $request)
    {
        $this->assertEnabled();
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'type'          => 'required|in:recurring,specific',
            'period'        => 'required|in:morning,afternoon',
            'day_of_week'   => 'nullable|integer|between:0,6',
            'specific_date' => 'nullable|date|after_or_equal:today',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i|after:start_time',
            'max_bookings'  => 'required|integer|min:1|max:999',
            'is_active'     => 'boolean',
        ]);

        if ($data['type'] === 'recurring') {
            abort_unless(isset($data['day_of_week']), 422, 'day_of_week required for recurring slots.');
        } else {
            abort_unless(isset($data['specific_date']), 422, 'specific_date required for specific slots.');
        }

        $slot = AvailabilitySlot::create(array_merge($data, ['tenant_id' => $tenantId]));

        return response()->json(['message' => __('ui.controller_messages.slot_added'), 'slot' => $slot], 201);
    }

    /**
     * Generate one-off ("specific") slots across a date range.
     *
     * The single-slot form makes an operator re-enter the same opening hours
     * once per day, which stops being usable past about a week. Here they
     * describe the shape of the period once — a date range, optionally which
     * weekdays inside it, and one or more time windows — and every matching
     * date is materialised as its own row.
     *
     * Concrete rows (rather than a stored range) keep booking, capacity and
     * per-day edits working exactly as they already do: ReservationBotService
     * resolves availability through AvailabilitySlot::appliesToDate(), so it
     * needs no knowledge of ranges, and an operator can still amend or delete
     * one awkward day without unpicking the whole period.
     */
    public function bulkStoreSlots(Request $request)
    {
        $this->assertEnabled();
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'start_date'           => 'required|date|after_or_equal:today',
            'end_date'             => 'required|date|after_or_equal:start_date',
            'days_of_week'         => 'nullable|array',
            'days_of_week.*'       => 'integer|between:0,6',
            'windows'              => 'required|array|min:1|max:12',
            'windows.*.period'     => 'required|in:morning,afternoon',
            'windows.*.start_time' => 'required|date_format:H:i',
            'windows.*.end_time'   => 'required|date_format:H:i',
            'max_bookings'         => 'required|integer|min:1|max:999',
            'is_active'            => 'boolean',
        ]);

        // The validator cannot compare two fields inside an array entry, so the
        // ordering check lives here. Zero-padded H:i compares correctly as text.
        foreach ($data['windows'] as $i => $w) {
            if ($w['end_time'] <= $w['start_time']) {
                return response()->json([
                    'message' => __('ui.reservations.bulk_err_window_order', ['n' => $i + 1]),
                ], 422);
            }
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end   = Carbon::parse($data['end_date'])->startOfDay();

        if ($start->diffInDays($end) + 1 > self::BULK_MAX_DAYS) {
            return response()->json([
                'message' => __('ui.reservations.bulk_err_range_too_long', ['max' => self::BULK_MAX_DAYS]),
            ], 422);
        }

        // No weekday selection means "every day in the range", which is the
        // plain reading of picking a range and giving it opening hours.
        $dows = collect($data['days_of_week'] ?? [])->map(fn ($d) => (int) $d)->unique();

        // One query for the whole window. Re-running a generate must not
        // duplicate days it already created — operators extend a season by
        // resubmitting the same range with a later end date.
        $existing = AvailabilitySlot::where('tenant_id', $tenantId)
            ->where('type', 'specific')
            ->whereBetween('specific_date', [$start->toDateString(), $end->toDateString()])
            ->get(['specific_date', 'start_time', 'end_time'])
            ->mapWithKeys(fn ($s) => [
                $s->specific_date->toDateString()
                    . '|' . substr($s->start_time, 0, 5)
                    . '|' . substr($s->end_time, 0, 5) => true,
            ]);

        $now     = now();
        $rows    = [];
        $seen    = [];
        $skipped = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($dows->isNotEmpty() && !$dows->contains($d->dayOfWeek)) {
                continue;
            }

            foreach ($data['windows'] as $w) {
                $key = $d->toDateString() . '|' . $w['start_time'] . '|' . $w['end_time'];

                // $seen also absorbs a caller sending the same window twice.
                if (isset($existing[$key]) || isset($seen[$key])) {
                    $skipped++;
                    continue;
                }
                $seen[$key] = true;

                $rows[] = [
                    'tenant_id'     => $tenantId,
                    'type'          => 'specific',
                    'period'        => $w['period'],
                    'day_of_week'   => null,
                    'specific_date' => $d->toDateString(),
                    'start_time'    => $w['start_time'],
                    'end_time'      => $w['end_time'],
                    'max_bookings'  => $data['max_bookings'],
                    'is_active'     => $data['is_active'] ?? true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if (count($rows) > self::BULK_MAX_ROWS) {
            return response()->json([
                'message' => __('ui.reservations.bulk_err_too_many', [
                    'count' => count($rows),
                    'max'   => self::BULK_MAX_ROWS,
                ]),
            ], 422);
        }

        if ($rows === []) {
            return response()->json([
                'message' => __('ui.reservations.bulk_err_nothing'),
            ], 422);
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                AvailabilitySlot::insert($chunk);
            }
        });

        return response()->json([
            'message' => __('ui.reservations.bulk_created', ['count' => count($rows)]),
            'created' => count($rows),
            'skipped' => $skipped,
        ], 201);
    }

    public function updateSlot(Request $request, AvailabilitySlot $slot)
    {
        $this->assertEnabled();
        abort_unless($slot->tenant_id === auth()->user()->tenant_id, 403);

        $data = $request->validate([
            'period'       => 'sometimes|in:morning,afternoon',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'max_bookings' => 'required|integer|min:1|max:999',
            'is_active'    => 'boolean',
        ]);

        $slot->update($data);

        return response()->json(['message' => __('ui.controller_messages.slot_updated'), 'slot' => $slot->fresh()]);
    }

    public function destroySlot(AvailabilitySlot $slot)
    {
        $this->assertEnabled();
        abort_unless($slot->tenant_id === auth()->user()->tenant_id, 403);
        $slot->delete();
        return response()->json(['message' => __('ui.controller_messages.slot_deleted')]);
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function settings(Request $request)
    {
        $this->assertEnabled();
        $tenantId = auth()->user()->tenant_id;

        if ($request->expectsJson()) {
            return response()->json(ReservationSetting::where('tenant_id', $tenantId)->first());
        }

        $setting   = ReservationSetting::where('tenant_id', $tenantId)->first();
        $instances = WhatsAppInstance::where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->get();

        return view('admin.reservations.settings', compact('setting', 'instances'));
    }

    public function saveSettings(Request $request)
    {
        $this->assertEnabled();
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'instance_id'           => 'nullable|integer|exists:whatsapp_instances,id',
            'service_name'          => 'required|string|max:100',
            'trigger_keywords'      => 'nullable|string',
            'welcome_message'       => 'nullable|string|max:1000',
            'select_date_message'   => 'nullable|string|max:500',
            'select_slot_message'   => 'nullable|string|max:500',
            'ask_name_message'      => 'nullable|string|max:500',
            'ask_notes_message'     => 'nullable|string|max:500',
            'confirmation_message'  => 'nullable|string|max:1000',
            'cancellation_message'  => 'nullable|string|max:500',
            'no_slots_message'      => 'nullable|string|max:500',
            'collect_notes'         => 'boolean',
            'is_active'             => 'boolean',
        ]);

        // Parse comma-separated keywords
        $data['trigger_keywords'] = array_values(array_filter(
            array_map('trim', explode(',', $data['trigger_keywords'] ?? ''))
        ));

        if ($data['instance_id']) {
            abort_unless(
                WhatsAppInstance::where('id', $data['instance_id'])
                    ->where('tenant_id', $tenantId)->exists(),
                403
            );
        }

        ReservationSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            array_merge($data, ['tenant_id' => $tenantId])
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => __('ui.controller_messages.settings_saved')]);
        }

        return back()->with('success', __('ui.controller_messages.settings_saved'));
    }
}
