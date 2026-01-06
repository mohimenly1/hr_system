<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenaltyType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'affects_evaluation', 'affects_salary',
        'deduction_type', 'deduction_amount', 'is_active',
    ];

    protected $casts = [
        'affects_evaluation' => 'boolean',
        'affects_salary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function criteria(): BelongsToMany
    {
        return $this->belongsToMany(EvaluationCriterion::class, 'penalty_type_evaluation_criterion')
                    ->withPivot('deduction_points')
                    ->withTimestamps();
    }

    /**
     * Get the deduction rules for this penalty type.
     */
    public function deductionRules(): HasMany
    {
        return $this->hasMany(DeductionRule::class);
    }
}
