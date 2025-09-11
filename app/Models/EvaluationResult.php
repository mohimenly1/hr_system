<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_evaluation_id',
        'evaluation_criterion_id',
        'manager_score',
        'admin_score',
        'notes',
    ];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }

    public function performanceEvaluation(): BelongsTo
    {
        return $this->belongsTo(PerformanceEvaluation::class);
    }
}
