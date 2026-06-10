<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilitySlot extends Model
{
    protected $fillable = [
        'tenant_id', 'type', 'period', 'day_of_week', 'specific_date',
        'start_time', 'end_time', 'max_bookings', 'is_active',
    ];

    protected $casts = [
        'day_of_week'   => 'integer',
        'max_bookings'  => 'integer',
        'is_active'     => 'boolean',
        'specific_date' => 'date',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class, 'slot_id'); }

    /** Count confirmed/pending reservations on a given date. */
    public function bookingsOnDate(Carbon $date): int
    {
        return $this->reservations()
            ->whereDate('reservation_date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();
    }

    public function hasCapacityOn(Carbon $date): bool
    {
        return $this->bookingsOnDate($date) < $this->max_bookings;
    }

    public function remainingOn(Carbon $date): int
    {
        return max(0, $this->max_bookings - $this->bookingsOnDate($date));
    }

    /** Check whether this slot applies on the given Carbon date. */
    public function appliesToDate(Carbon $date): bool
    {
        if (!$this->is_active) return false;

        if ($this->type === 'specific') {
            return $this->specific_date && $this->specific_date->isSameDay($date);
        }

        // recurring — match day of week
        return $this->day_of_week === (int) $date->dayOfWeek;
    }

    public static function dayName(int $dow): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$dow] ?? '';
    }
}
