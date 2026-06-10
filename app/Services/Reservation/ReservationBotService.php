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

        // No active session — check trigger keyword
        if (!$state) {
            if (!$settings->matchesTrigger($text)) {
                return false;
            }
            $this->startFlow($settings, $instance, $phone, $conversationId);
            return true;
        }

        // Active session — route to current step
        return match ($state['step']) {
            'select_date' => $this->handleSelectDate($settings, $instance, $phone, $text, $state, $conversationId),
            'select_slot' => $this->handleSelectSlot($settings, $instance, $phone, $text, $state, $conversationId),
            'enter_name'  => $this->handleEnterName($settings, $instance, $phone, $text, $state, $conversationId),
            'enter_notes' => $this->handleEnterNotes($settings, $instance, $phone, $text, $state, $conversationId),
            default       => false,
        };
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

        $intro = $settings->select_date_message ?: "🗓 *{$settings->service_name}*\n\nاختر اليوم المناسب:";
        $list  = '';
        foreach ($dates as $i => $date) {
            $list .= "\n" . ($i + 1) . '. ' . $this->formatDateAr($date);
        }

        $this->send($instance, $phone, $intro . $list . "\n\nأرسل الرقم المناسب. (أرسل *إلغاء* في أي وقت للإلغاء)");

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

        $selectedDate = Carbon::parse($dates[$choice - 1]);
        $slots        = $this->slotsForDate($settings->tenant_id, $selectedDate);

        if (empty($slots)) {
            $this->send($instance, $phone, 'لا توجد أوقات متاحة لهذا اليوم. اختر يوماً آخر.');
            $this->startFlow($settings, $instance, $phone, $conversationId);
            return true;
        }

        $intro = $settings->select_slot_message ?: '⏰ الأوقات المتاحة ليوم ' . $this->formatDateAr($selectedDate) . ':';
        $list  = '';
        foreach ($slots as $i => $slot) {
            $list .= "\n" . ($i + 1) . '. ' . $slot['start'] . ' - ' . $slot['end'];
        }
        $this->send($instance, $phone, $intro . $list . "\n\nأرسل الرقم المناسب.");

        $this->setState($settings->tenant_id, $phone, array_merge($state, [
            'step'            => 'select_slot',
            'selected_date'   => $selectedDate->toDateString(),
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
        $slot         = $state['selected_slot'];
        $date         = Carbon::parse($state['selected_date']);

        // Guard: slot may have filled up between listing and selection
        $slotModel = AvailabilitySlot::find($slot['id']);
        if (!$slotModel || !$slotModel->hasCapacityOn($date)) {
            $this->clearState($settings->tenant_id, $phone);
            $this->send($instance, $phone, 'عذراً، تم حجز هذا الموعد للتو. يرجى الاختيار من جديد.');
            $this->startFlow($settings, $instance, $phone, $state['conversation_id'] ?? 0);
            return;
        }

        $reservation = Reservation::create([
            'tenant_id'        => $settings->tenant_id,
            'slot_id'          => $slot['id'],
            'conversation_id'  => $state['conversation_id'] ?? null,
            'customer_phone'   => $phone,
            'customer_name'    => $name,
            'customer_notes'   => $notes,
            'reservation_date' => $date->toDateString(),
            'start_time'       => $slot['start'],
            'end_time'         => $slot['end'],
            'status'           => 'confirmed',
            'booked_at'        => now(),
        ]);

        $this->clearState($settings->tenant_id, $phone);

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

    /** Return available slots for a specific date. */
    private function slotsForDate(int $tenantId, Carbon $date): array
    {
        $slots = AvailabilitySlot::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $result = [];
        foreach ($slots as $slot) {
            if ($slot->appliesToDate($date) && $slot->hasCapacityOn($date)) {
                $result[] = [
                    'id'    => $slot->id,
                    'start' => substr($slot->start_time, 0, 5),
                    'end'   => substr($slot->end_time, 0, 5),
                ];
            }
        }

        usort($result, fn($a, $b) => $a['start'] <=> $b['start']);
        return $result;
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

        if ($this->conversationId) {
            Message::create([
                'conversation_id' => $this->conversationId,
                'tenant_id'       => $this->tenantId,
                'direction'       => 'out',
                'author_type'     => 'bot',
                'type'            => 'text',
                'body'            => $text,
                'status'          => 'sent',
                'sent_at'         => now(),
            ]);
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
        Cache::put($this->stateKey($tenantId, $phone), $state, self::STATE_TTL);
    }

    private function clearState(int $tenantId, string $phone): void
    {
        Cache::forget($this->stateKey($tenantId, $phone));
    }
}
