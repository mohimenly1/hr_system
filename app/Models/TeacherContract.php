<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'contract_type',
        'start_date',
        'end_date',
        'salary_type',
        'salary_amount',
        'hourly_rate',
        'working_hours_per_week',
        'notes',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'salary_amount' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
