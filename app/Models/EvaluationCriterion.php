<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class EvaluationCriterion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'max_score',
        'affects_salary',
        'is_active',
    ];

    protected $casts = [
        'affects_salary' => 'boolean',
        'is_active' => 'boolean',
    ];


    /**
     * The results that belong to this criterion.
     */
    public function results(): HasMany
    {
        return $this->hasMany(EvaluationResult::class, 'evaluation_criterion_id');
    }

    public function penaltyTypes(): BelongsToMany
{
    return $this->belongsToMany(PenaltyType::class, 'penalty_type_evaluation_criterion')
                ->withPivot('deduction_points') // لجلب نقاط الخصم
                ->withTimestamps();
}

    /**
     * The penalty types that are linked to this evaluation criterion.
     */

}
