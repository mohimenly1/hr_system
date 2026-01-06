<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'hourly_id',
        'hourly_type',
        'day_of_week',
        'hours',
        'start_time',
        'end_time',
        'notes',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
    ];

    /**
     * Get the parent hourly model (employee or teacher).
     */
    public function hourly(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get day name in Arabic.
     */
    public function getDayNameAttribute(): string
    {
        $days = [
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        return $days[$this->day_of_week] ?? 'غير محدد';
    }
}
