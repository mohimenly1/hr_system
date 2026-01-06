<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchedulingConstraint extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedulable_id',
        'schedulable_type',
        'constraint_type',
        'value',
        'employment_type',
        'specific_work_days',
        'break_periods',
    ];

    protected $casts = [
        'value' => 'array',
        'specific_work_days' => 'array',
        'break_periods' => 'array',
    ];

    public function schedulable()
    {
        return $this->morphTo();
    }
}
