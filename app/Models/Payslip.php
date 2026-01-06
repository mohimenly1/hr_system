<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payslip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'teacher_id',
        'contract_id',
        'teacher_contract_id',
        'month',
        'year',
        'issue_date',
        'gross_salary',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'status',
        'notes',
        'is_manual',
        'deductions_overridden',
        'override_reason',
        'payroll_expense_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'gross_salary' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'is_manual' => 'boolean',
        'deductions_overridden' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function teacherContract(): BelongsTo
    {
        return $this->belongsTo(TeacherContract::class, 'teacher_contract_id');
    }

    public function payrollExpense(): BelongsTo
    {
        return $this->belongsTo(PayrollExpense::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }

    /**
     * Get the person (employee or teacher) associated with this payslip
     */
    public function getPersonAttribute()
    {
        if ($this->employee_id) {
            return $this->employee;
        }
        return $this->teacher;
    }

    /**
     * Get person type
     */
    public function getPersonTypeAttribute(): string
    {
        return $this->employee_id ? 'employee' : 'teacher';
    }
}
