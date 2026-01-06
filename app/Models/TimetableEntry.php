<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;


class TimetableEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedulable_id',
        'schedulable_type',
        'day_of_week',
        'start_time',
        'end_time',
        'subject_id',
        'section_id',
        'shift_id',
        'work_type',
        'work_minutes',
        'is_break',
        'entry_type',
        'title',
        'order_in_day',
    ];

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the subject associated with the timetable entry.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the section associated with the timetable entry.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the shift associated with the timetable entry.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Calculate work minutes from start and end time.
     */
    public function calculateWorkMinutes(): int
    {
        if ($this->work_minutes !== null) {
            return $this->work_minutes;
        }

        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        // Handle overnight shifts
        if ($end->lt($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }
}
