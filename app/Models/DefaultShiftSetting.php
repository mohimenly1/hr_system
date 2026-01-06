<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultShiftSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'work_days',
        'hours_per_week',
        'hours_per_month',
        'work_days_per_week',
        'is_active',
        'description',
        'department_id',
    ];

    protected $casts = [
        'work_days' => 'array',
        'is_active' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the department for this setting
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the active default shift setting for a specific department or organization-wide
     */
    public static function getActive($departmentId = null)
    {
        $query = static::where('is_active', true);

        if ($departmentId) {
            // First try to get department-specific setting
            $departmentSetting = $query->where('department_id', $departmentId)->first();
            if ($departmentSetting) {
                return $departmentSetting;
            }
        }

        // Fallback to organization-wide setting (department_id is null)
        return $query->whereNull('department_id')->first();
    }

    /**
     * Calculate hours per week based on work days and shift times
     */
    public function calculateHoursPerWeek(): float
    {
        if (empty($this->work_days) || !$this->start_time || !$this->end_time) {
            return 0;
        }

        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        // Handle overnight shifts
        if ($end->lt($start)) {
            $end->addDay();
        }

        $hoursPerDay = $start->diffInHours($end) + ($start->diffInMinutes($end) % 60) / 60;
        $workDaysCount = count($this->work_days);

        return round($hoursPerDay * $workDaysCount, 2);
    }

    /**
     * Calculate hours per month (assuming 4.33 weeks per month)
     */
    public function calculateHoursPerMonth(): float
    {
        return round($this->calculateHoursPerWeek() * 4.33, 2);
    }
}
