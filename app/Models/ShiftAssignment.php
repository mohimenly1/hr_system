<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'shiftable_id',
        'shiftable_type',
    ];

    /**
     * Get the shift that owns the assignment.
     * جلب الدوام الذي ينتمي إليه هذا التعيين
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the parent shiftable model (employee or teacher).
     * جلب النموذج الأب (موظف أو معلم)
     */
    public function shiftable()
    {
        return $this->morphTo();
    }
}