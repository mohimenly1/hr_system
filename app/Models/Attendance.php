<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendable_id',
        'attendable_type',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attendance_date' => 'date',
    ];

    /**
     * Get the parent attendable model (employee or teacher).
     */
    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Calculate expected hours for this attendance date.
     */
    public function getExpectedHours(): float
    {
        /** @var Carbon $date */
        $date = $this->attendance_date instanceof Carbon ? $this->attendance_date : Carbon::parse($this->attendance_date);
        $dayOfWeek = $date->dayOfWeek;
        $dayOfWeek = $dayOfWeek === 0 ? 6 : ($dayOfWeek === 6 ? 0 : $dayOfWeek);

        $expectedEntries = TimetableEntry::where('schedulable_id', $this->attendable_id)
            ->where('schedulable_type', $this->attendable_type)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_break', false)
            ->get();

        if ($expectedEntries->isEmpty()) {
            // Check shift assignment
            $shiftAssignment = $this->attendable->shiftAssignment;
            if ($shiftAssignment && $shiftAssignment->shift) {
                $start = \Carbon\Carbon::parse($shiftAssignment->shift->start_time);
                $end = \Carbon\Carbon::parse($shiftAssignment->shift->end_time);
                if ($end->lt($start)) {
                    $end->addDay();
                }
                return $start->diffInHours($end);
            }
            return 0;
        }

        $totalMinutes = 0;
        foreach ($expectedEntries as $entry) {
            $totalMinutes += $entry->work_minutes ?? 0;
        }

        return $totalMinutes / 60;
    }

    /**
     * Calculate actual hours worked.
     */
    public function getActualHours(): float
    {
        if (!$this->check_in_time) {
            return 0;
        }

        /** @var Carbon $date */
        $date = $this->attendance_date instanceof Carbon ? $this->attendance_date : Carbon::parse($this->attendance_date);
        $dateString = $date->format('Y-m-d');
        $checkIn = Carbon::parse($dateString . ' ' . $this->check_in_time);
        $checkOut = $this->check_out_time
            ? Carbon::parse($dateString . ' ' . $this->check_out_time)
            : $checkIn->copy()->addHours($this->getExpectedHours());

        return $checkIn->diffInHours($checkOut);
    }
}
