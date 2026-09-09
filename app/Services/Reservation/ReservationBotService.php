<?php

namespace App\Services\Reservation;

use App\Models\AvailabilitySlot;
use App\Models\Message;
use App\Models\Reservation;
use App\Models\ReservationSetting;
use App\Models\WhatsAppInstance;
use App\Services\WhatsApp\Gateway\EvolutionApiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationBotService
{
    private const STATE_TTL = 1800; // 30 minutes

    private int $conversationId = 0;
    private int $tenantId       = 0;

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * Call from ProcessIncomingMessage after a message is persisted.
     * Returns true if the message was consumed by the bot (caller should skip AI reply).
     */
    public function handle(
        ReservationSetting $settings,
        WhatsAppInstance   $instance,
        string             $phone,
        string             $messageText,
        int                $conversationId
    ): bool {
        $this->conversationId = $conversationId;
        $this->tenantId       = $settings->tenant_id;

        $text  = trim($messageText);
        $lower = mb_strtolower($text);

        // Cancellation at any step
        if (in_array($lower, ['إلغاء', 'cancel', 'annuler', 'الغاء'], true)) {
            $this->clearState($settings->tenant_id, $phone);
            $this->send($instance, $phone, $settings->cancellation_message ?: 'تم إلغاء الحجز. يمكنك البدء من جديد في أي وقت.');
            return true;
        }

        $state = $this->getState($settings->tenant_id, $phone);

        Log::info('ReservationBot: handle', [
            'phone'   => $phone,
            'text'    => $text,
            'hasState'=> $state !== null,
            'step'    => $state['step'] ?? null,
        ]);

        // No active session — check trigger keyword
        if (!$state) {
            if (!$settings->matchesTrigger($text)) {
                return false;
            }
            $this->startFlow($settings, $instance, $phone, $conversationId);
            return true;
        }

        // Active session — route to current step. Wrapped so any unexpected error clears the
        // session and replies instead of crashing (a crash leaves the cached state wedged, so
        // every later message re-crashes and the customer never gets a reply).
        try {
            return match ($state['step']) {
                'select_date'   => $this->handleSelectDate($settings, $instance, $phone, $text, $state, $conversationId),
                'select_period' => $this->handleSelectPeriod($settings, $instance, $phone, $text, $state, $conversationId),
                'select_slot'   => $this->handleSelectSlot($settings, $instance, $phone, $text, $state, $conversationId),
                'enter_name'    => $this->handleEnterName($settings, $instance, $phone, $text, $state, $conversationId),
                'enter_notes'   => $this->handleEnterNotes($settings, $instance, $phone, $text, $state, $conversationId),
                default         => false,
            };
        } catch (\Throwable $e) {
            Log::error('ReservationBot: step handler failed, clearing session', [
                'phone' => $phone, 'step' => $state['step'] ?? null, 'error' => $e->getMessage(),
            ]);
            $this->clearState($settings->tenant_id, $phone);
            $this->send($instance, $phone, 'حدث خطأ مؤقت. تم إعادة ضبط الحجز — أرسل كلمة الحجز للبدء من جديد.');
            return true;
        }
    }

    // ── Flow steps ────────────────────────────────────────────────────────────

    private function startFlow(ReservationSetting $settings, WhatsAppInstance $instance, string $phone, int $conversationId): void
    {
        $dates = $this->upcomingAvailableDates($settings->tenant_id);

        if (empty($dates)) {
            $this->send($instance, $phone, $settings->no_slots_message ?: 'عذراً، لا توجد مواعيد متاحة حالياً. يرجى التواصل معنا لاحقاً.');
            return;
        }

        // Welcome message (optional)
        if ($settings->welcome_message) {
            $this->send($instance, $phone, $settings->welcome_message);
        }

        $rows = [];
        foreach ($dates as $i => $date) {
            $rows[] = ['rowId' => (string) ($i + 1), 'title' => $this->formatDateAr($date), 'description' => ''];
        }

        $this->sendList(
            $instance, $phone,
            '🗓 ' . $settings->service_name,
            $settings->select_date_message ?: 'اختر اليوم المناسب',
            'عرض المواعيد',
            [['title' => 'التواريخ المتاحة', 'rows' => $rows]]
        );

        $this->setState($settings->tenant_id, $phone, [
            'step'           => 'select_date',
            'available_dates'=> array_map(fn($d) => $d->toDateString(), $dates),
            'conversation_id'=> $conversationId,
        ]);
    }

    private function handleSelectDate(
        ReservationSetting $settings, WhatsAppInstance $instance,
        string $phone, string $text, array $state, int $conversationId
    ): bool {
        $choice = (int) $text;
        $dates  = $state['available_dates'] ?? [];

        if ($choice < 1 || $choice > count($dates)) {
            $this->send($instance, $phone, 'يرجى إرسال رقم صحيح من القائمة.');
            return true;
        }

        $selectedDate  = Carbon::parse($dates[$choice - 1]);
        $allSlots      = $this->slotsForDate($settings->tenant_id, $selectedDate);

        if (empty($allSlots)) {
            $this->send($instance, $phone, 'لا توجد أوقات متاحة لهذا اليوم. اختر يوماً آخر.');
            $this->startFlow($settings, $instance, $phone, $conversationId);
            return true;
        }

        $morningSlots   = array_values(array_filter($allSlots, fn($s) => $s['period'] === 'morning'));
        $afternoonSlots = array_values(array_filter($allSlots, fn($s) => $s['period'] === 'afternoon'));
        $hasBoth        = !empty($morningSlots) && !empty($afternoonSlots);

        $newState = array_merge($state, [
            'selected_date' => $selectedDate->toDateString(),
            'all_slots'     => $allSlots,
        ]);

        if ($hasBoth) {
            // 2 options → tappable native-flow buttons (the gateway is patched with the native_flow
            // additionalNodes hint, so these render as tappable on both real numbers and @lid).
            // A tap returns the button id ("1"/"2"), which handleSelectPeriod parses.
            $this->sendButtons(
                $instance, $phone,
                '⏰ ' . $this->formatDateAr($selectedDate),
                'اختر الفترة المناسبة',
                [
                    ['id' => '1', 'text' => '🌅 صباحاً (' . count($morningSlots) . ')'],
                    ['id' => '2', 'text' => '🌆 مساءً (' . count($afternoonSlots) . ')'],
                ],
                'أرسل إلغاء للإلغاء'
            );
            $this->setState($settings->tenant_id, $phone, array_merge($newState, ['step' => 'select_period']));
        } else {
            $period = !empty($morningSlots) ? 'morning' : 'afternoon';
            $slots  = !empty($morningSlots) ? $morningSlots : $afternoonSlots;
            $this->sendSlotList($settings, $instance, $phone, $slots, $selectedDate, $period);
            $this->setState($settings->tenant_id, $phone, array_merge($newState, [
                'step'            => 'select_slot',
                'selected_period' => $period,
                'available_slots' => $slots,
            ]));
        }

        return true;
    }

    private function handleSelectPeriod(
        ReservationSetting $settings, WhatsAppInstance $instance,
        string $phone, string $text, array $state, int $conversationId
    ): bool {
        $choice = (int) $text;

        if (!in_array($choice, [1, 2], true)) {
            $this->send($instance, $phone, 'يرجى إرسال 1 للصباح أو 2 للمساء.');
            return true;
        }

        $period     = $choice === 1 ? 'morning' : 'afternoon';
        $allSlots   = $state['all_slots'] ?? [];
        $slots      = array_values(array_filter($allSlots, fn($s) => $s['period'] === $period));
        $date       = Carbon::parse($state['selected_date']);

        if (empty($slots)) {
            $other       = $period === 'morning' ? 'afternoon' : 'morning';
            $otherLabel  = $other === 'morning' ? 'صباحاً' : 'مساءً';
            $this->send($instance, $phone, "عذراً، لا توجد أوقات متاحة في هذه الفترة.\nهل تريد الاطلاع على الأوقات المتاحة $otherLabel؟ (أرسل 1 للصباح / 2 للمساء)");
            return true;
        }

        $this->sendSlotList($settings, $instance, $phone, $slots, $date, $period);
        $this->setState($settings->tenant_id, $phone, array_merge($state, [
            'step'            => 'select_slot',
            'selected_period' => $period,
            'available_slots' => $slots,
        ]));

        return true;
    }

    private function handleSelectSlot(
        ReservationSetting $settings, WhatsAppInstance $instance,
        string $phone, string $text, array $state, int $conversationId
    ): bool {
        $choice = (int) $text;
        $slots  = $state['available_slots'] ?? [];

        if ($choice < 1 || $choice > count($slots)) {
            $this->send($instance, $phone, 'يرجى إرسال رقم صحيح من القائمة.');
            return true;
        }

        $selectedSlot = $slots[$choice - 1];
        $date         = Carbon::parse($state['selected_date']);

        // Re-check capacity (race condition: another user may have booked this slot)
        $slotModel = AvailabilitySlot::find($selectedSlot['id']);
        if (!$slotModel || !$slotModel->hasCapacityOn($date)) {
            // Refresh the list for this period
            $period          = $state['selected_period'] ?? null;
            $freshAllSlots   = $this->slotsForDate($settings->tenant_id, $date);
            $freshSlots      = $period
                ? array_values(array_filter($freshAllSlots, fn($s) => $s['period'] === $period))
                : $freshAllSlots;

            if (empty($freshSlots)) {
                $this->clearState($settings->tenant_id, $phone);
                $this->send($instance, $phone, "عذراً، لقد امتلأت جميع المواعيد المتاحة لهذا اليوم 😔\nيمكنك المحاولة في يوم آخر.");
                return true;
            }

            $this->send($instance, $phone, "⚠️ عذراً، هذا الموعد لم يعد متاحاً — تم حجزه للتو.\n\nإليك الأوقات المتاحة الآن:");
            $this->sendSlotList($settings, $instance, $phone, $freshSlots, $date, $period ?? '');
            $this->setState($settings->tenant_id, $phone, array_merge($state, [
                'available_slots' => $freshSlots,
            ]));
            return true;
        }

        $this->send($instance, $phone, $settings->ask_name_message ?: '✏️ ما اسمك الكامل؟');

        $this->setState($settings->tenant_id, $phone, array_merge($state, [
            'step'          => 'enter_name',
            'selected_slot' => $selectedSlot,
        ]));

        return true;
    }

    private function handleEnterName(
        ReservationSetting $settings, WhatsAppInstance $instance,
        string $phone, string $text, array $state, int $conversationId
    ): bool {
        if (mb_strlen($text) < 2) {
            $this->send($instance, $phone, 'يرجى إدخال اسم صحيح.');
            return true;
        }

        if ($settings->collect_notes) {
            $this->send($instance, $phone, $settings->ask_notes_message ?: '📝 هل لديك ملاحظات إضافية؟ (أو أرسل *لا* للتخطي)');
            $this->setState($settings->tenant_id, $phone, array_merge($state, [
                'step'      => 'enter_notes',
                'temp_name' => $text,
            ]));
        } else {
            $this->completeBooking($settings, $instance, $phone, $text, null, $state);
        }

        return true;
    }

    private function handleEnterNotes(
        ReservationSetting $settings, WhatsAppInstance $instance,
        string $phone, string $text, array $state, int $conversationId
    ): bool {
        $notes = in_array(mb_strtolower($text), ['لا', 'no', 'non', 'skip', 'تخطي'], true) ? null : $text;
        $this->completeBooking($settings, $instance, $phone, $state['temp_name'], $notes, $state);
        return true;
    }

    private function completeBooking(
        ReservationSetting $settings, WhatsAppInstance $instance,
        string $phone, string $name, ?string $notes, array $state
    ): void {
        $slot = $state['selected_slot'];
        $date = Carbon::parse($state['selected_date']);

        // The cached state can carry a conversation_id that no longer exists (e.g. the WhatsApp
        // instance was re-created, which rotated conversation rows). Null it out if missing,
        // otherwise the FK constraint throws, the bot crashes, and the state stays wedged so every
        // later message re-crashes and the customer never gets a reply.
        $conversationId = $state['conversation_id'] ?? null;
        if ($conversationId && !\App\Models\Conversation::withoutGlobalScopes()->whereKey($conversationId)->exists()) {
            $conversationId = null;
        }

        // PROC-020: serialise concurrent bookings on the same slot. Two
        // customers landing on the last seat inside the same second used to
        // both pass the unlocked count-then-insert and both get confirmed.
        // Locking the slot row forces the second to wait for the first to
        // commit; the follow-up lockForUpdate count then sees the freshly
        // inserted row and bails.
        $reservation = DB::transaction(function () use ($slot, $date, $settings, $phone, $name, $notes, $conversationId) {
            $slotModel = AvailabilitySlot::whereKey($slot['id'])->lockForUpdate()->first();
            if (!$slotModel) {
                return null;
            }

            $confirmed = Reservation::where('slot_id', $slot['id'])
                ->whereDate('reservation_date', $date)
                ->whereIn('status', ['confirmed', 'pending'])
                ->lockForUpdate()
                ->count();

            if ($confirmed >= $slotModel->max_bookings) {
                return null;
            }

            return Reservation::create([
                'tenant_id'        => $settings->tenant_id,
                'slot_id'          => $slot['id'],
                'conversation_id'  => $conversationId,
                'customer_phone'   => $phone,
                'customer_name'    => $name,
                'customer_notes'   => $notes,
                'reservation_date' => $date->toDateString(),
                'start_time'       => $slot['start'],
                'end_time'         => $slot['end'],
                'status'           => 'confirmed',
                'booked_at'        => now(),
            ]);
        });

        // State cleared either way — the customer restarts the flow if the
        // slot filled up between listing and completing the booking.
        $this->clearState($settings->tenant_id, $phone);

        if (!$reservation) {
            $this->send($instance, $phone, 'عذراً، تم حجز هذا الموعد للتو. يرجى الاختيار من جديد.');
            $this->startFlow($settings, $instance, $phone, $state['conversation_id'] ?? 0);
            return;
        }

        $confirmMsg = $settings->confirmation_message
            ?: "✅ *تم تأكيد حجزك!*\n\n📅 التاريخ: {date}\n⏰ الوقت: {start} - {end}\n👤 الاسم: {name}\n🔖 رقم الحجز: #{id}";

        $msg = str_replace(
            ['{date}', '{start}', '{end}', '{name}', '{id}'],
            [$this->formatDateAr($date), $slot['start'], $slot['end'], $name, $reservation->id],
            $confirmMsg
        );

        $this->send($instance, $phone, $msg);

        Log::info('ReservationBot: booking created', ['reservation_id' => $reservation->id, 'phone' => $phone]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Return the next 7 days that have at least one available slot. */
    private function upcomingAvailableDates(int $tenantId): array
    {
        $slots = AvailabilitySlot::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $dates = [];
        for ($i = 0; $i <= 13; $i++) {
            $date = Carbon::today()->addDays($i);
            foreach ($slots as $slot) {
                if ($slot->appliesToDate($date) && $slot->hasCapacityOn($date)) {
                    $dates[] = $date;
                    break;
                }
            }
            if (count($dates) >= 7) break;
        }

        return $dates;
    }

    /** Return available slots for a specific date with period and remaining count. */
    private function slotsForDate(int $tenantId, Carbon $date): array
    {
        $slots = AvailabilitySlot::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $result = [];
        foreach ($slots as $slot) {
            if ($slot->appliesToDate($date) && $slot->hasCapacityOn($date)) {
                $result[] = [
                    'id'        => $slot->id,
                    'start'     => substr($slot->start_time, 0, 5),
                    'end'       => substr($slot->end_time, 0, 5),
                    'period'    => $slot->period ?? 'morning',
                    'remaining' => $slot->remainingOn($date),
                    'max'       => $slot->max_bookings,
                ];
            }
        }

        usort($result, fn($a, $b) => $a['start'] <=> $b['start']);
        return $result;
    }

    private function sendSlotList(
        ReservationSetting $settings, WhatsAppInstance $instance,
        string $phone, array $slots, Carbon $date, string $period
    ): void {
        $periodLabel = $period === 'morning' ? '🌅 صباحاً' : '🌆 مساءً';
        $rows = [];
        foreach ($slots as $i => $slot) {
            $spotsLabel = $slot['remaining'] === 1 ? 'مقعد واحد متبقٍ' : "{$slot['remaining']} مقاعد متبقية";
            $rows[] = [
                'rowId'       => (string) ($i + 1),
                'title'       => $slot['start'] . ' - ' . $slot['end'],
                'description' => $spotsLabel,
            ];
        }
        $this->sendList(
            $instance, $phone,
            "⏰ {$periodLabel}",
            $this->formatDateAr($date),
            'اختر الوقت',
            [['title' => 'الأوقات المتاحة', 'rows' => $rows]]
        );
    }

    private function pluralSlots(int $count): string
    {
        return match ($count) {
            1       => 'وقت متاح',
            2       => 'وقتان متاحان',
            default => 'أوقات متاحة',
        };
    }

    /**
     * Send a tappable native-flow single_select list. The gateway (patched with the native_flow
     * additionalNodes hint) renders this as a real list the customer taps; a tap returns the row id
     * (e.g. "1") which the numeric step handlers parse. Falls back to a numbered text prompt if the
     * gateway call fails.
     */
    private function sendList(WhatsAppInstance $instance, string $phone, string $title, string $description, string $buttonText, array $sections): void
    {
        try {
            $client = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );
            $client->sendList($instance->gateway_instance_id, $phone, $title, $description, $buttonText, $sections);
        } catch (\Throwable $e) {
            Log::warning('ReservationBot: sendList failed, falling back to text', ['error' => $e->getMessage()]);
            $this->send($instance, $phone, $this->renderListAsText($title, $description, $sections));
            return;
        }

        // Persist a readable copy for the agent inbox (the interactive payload isn't human-readable).
        $this->persistMessage($this->renderListAsText($title, $description, $sections));
    }

    /**
     * Send up to 3 tappable native-flow quick-reply buttons. $buttons: list of
     * ['id' => '1', 'text' => '...']. Falls back to a numbered text prompt on failure.
     */
    private function sendButtons(WhatsAppInstance $instance, string $phone, string $title, string $description, array $buttons, string $footer = ''): void
    {
        try {
            $client = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );
            $client->sendButtons($instance->gateway_instance_id, $phone, $title, $description, $buttons, $footer);
        } catch (\Throwable $e) {
            Log::warning('ReservationBot: sendButtons failed, falling back to text', ['error' => $e->getMessage()]);
            $rows = array_map(fn($b) => ['rowId' => $b['id'], 'title' => $b['text'], 'description' => ''], $buttons);
            $this->send($instance, $phone, $this->renderListAsText($title, $description, [['title' => '', 'rows' => $rows]]));
            return;
        }

        $summary = "*{$title}*\n{$description}";
        foreach ($buttons as $b) {
            $summary .= "\n• {$b['text']}";
        }
        $this->persistMessage($summary);
    }

    /** Render a list (title + numbered rows) as a plain-text prompt for reliable delivery. */
    private function renderListAsText(string $title, string $description, array $sections): string
    {
        $text = "*{$title}*\n{$description}";
        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                $text .= "\n\n*{$row['rowId']}.* {$row['title']}";
                if (!empty($row['description'])) {
                    $text .= " — {$row['description']}";
                }
            }
        }
        return $text . "\n\n_" . 'أرسل الرقم المناسب للمتابعة' . "_";
    }

    private function send(WhatsAppInstance $instance, string $phone, string $text): void
    {
        try {
            $client = new EvolutionApiClient(
                $instance->effectiveGatewayUrl(),
                $instance->effectiveGatewayApiKey()
            );
            $client->sendText($instance->gateway_instance_id, $phone, $text);
        } catch (\Throwable $e) {
            Log::warning('ReservationBot: send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return;
        }

        $this->persistMessage($text);
    }

    private function persistMessage(string $text): void
    {
        if (!$this->conversationId) return;
        try {
            Message::create([
                'conversation_id' => $this->conversationId,
                'tenant_id'       => $this->tenantId,
                'direction'       => 'out',
                'author_type'     => 'ai',
                'type'            => 'text',
                'body'            => $text,
                'status'          => 'sent',
                'sent_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ReservationBot: message persist failed', ['error' => $e->getMessage()]);
        }
    }

    private function formatDateAr(Carbon $date): string
    {
        $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        $months = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                   'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        return $days[$date->dayOfWeek] . '، ' . $date->day . ' ' . $months[$date->month];
    }

    // ── State ─────────────────────────────────────────────────────────────────

    private function stateKey(int $tenantId, string $phone): string
    {
        return "reservation_bot:{$tenantId}:{$phone}";
    }

    private function getState(int $tenantId, string $phone): ?array
    {
        return Cache::get($this->stateKey($tenantId, $phone));
    }

    private function setState(int $tenantId, string $phone, array $state): void
    {
        $key = $this->stateKey($tenantId, $phone);
        Cache::put($key, $state, self::STATE_TTL);
        Log::info('ReservationBot: state saved', ['key' => $key, 'step' => $state['step'] ?? null]);
    }

    private function clearState(int $tenantId, string $phone): void
    {
        Cache::forget($this->stateKey($tenantId, $phone));
    }
}
