<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


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
}
