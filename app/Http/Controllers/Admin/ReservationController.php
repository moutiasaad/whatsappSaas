<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySlot;
use App\Models\Reservation;
use App\Models\ReservationSetting;
use App\Models\WhatsAppInstance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
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
        $base = Reservation::where('tenant_id', $tenantId);
        return [
            'total'     => (clone $base)->count(),
            'today'     => (clone $base)->whereDate('reservation_date', today())->count(),
            'upcoming'  => (clone $base)->whereIn('status', ['confirmed', 'pending'])
                                        ->whereDate('reservation_date', '>=', today())->count(),
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
        $reservation->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'reservation' => $reservation->fresh()]);
    }

    public function destroy(Reservation $reservation)
    {
        $this->assertEnabled();
        abort_unless($reservation->tenant_id === auth()->user()->tenant_id, 403);
        $reservation->delete();
        return response()->json(['message' => 'Deleted.']);
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

        return response()->json(['message' => 'Slot added.', 'slot' => $slot], 201);
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

        return response()->json(['message' => 'Slot updated.', 'slot' => $slot->fresh()]);
    }

    public function destroySlot(AvailabilitySlot $slot)
    {
        $this->assertEnabled();
        abort_unless($slot->tenant_id === auth()->user()->tenant_id, 403);
        $slot->delete();
        return response()->json(['message' => 'Deleted.']);
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
            return response()->json(['message' => 'Settings saved.']);
        }

        return back()->with('success', 'Settings saved.');
    }
}
