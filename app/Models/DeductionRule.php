<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'penalty_type_id',
        'deduction_type',
        'deduction_amount',
        'deduction_days',
        'deduction_hours',
        'min_deduction',
        'max_deduction',
        'conditions',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'deduction_amount' => 'decimal:2',
        'deduction_days' => 'integer',
        'deduction_hours' => 'decimal:2',
        'min_deduction' => 'decimal:2',
        'max_deduction' => 'decimal:2',
        'conditions' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the penalty type that owns this deduction rule.
     */
    public function penaltyType(): BelongsTo
    {
        return $this->belongsTo(PenaltyType::class);
    }

    /**
     * Get the effective deduction type (from rule or penalty type).
     */
    public function getEffectiveDeductionTypeAttribute(): ?string
    {
        return $this->deduction_type ?? $this->penaltyType?->deduction_type;
    }

    /**
     * Get the effective deduction amount (from rule or penalty type).
     */
    public function getEffectiveDeductionAmountAttribute(): ?float
    {
        return $this->deduction_amount ?? $this->penaltyType?->deduction_amount;
    }
}
