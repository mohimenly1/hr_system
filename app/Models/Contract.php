<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'contract_type',
        'start_date',
        'end_date',
        'probation_end_date',
        'job_title',
        'status',
        'basic_salary',
        'housing_allowance',
        'transportation_allowance',
        'other_allowances',
        'working_hours_per_day',
        'annual_leave_days',
        'notice_period_days',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'probation_end_date' => 'date',
        'basic_salary' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'transportation_allowance' => 'decimal:2',
        'other_allowances' => 'decimal:2',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['total_salary'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Calculate the total salary.
     *
     * @return float
     */
    public function getTotalSalaryAttribute()
    {
        return $this->basic_salary + $this->housing_allowance + $this->transportation_allowance + $this->other_allowances;
    }
}

