<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'year',
        'total_amount',
        'total_payslips',
        'employees_count',
        'teachers_count',
        'status',
        'notes',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
