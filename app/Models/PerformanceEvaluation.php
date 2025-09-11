<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PerformanceEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluable_id',
        'evaluable_type',
        'title',
        'evaluation_date',
        'final_score_percentage',
        'overall_notes',
    ];

    public function evaluable(): MorphTo
    {
        return $this->morphTo();
    }

    public function results(): HasMany
    {
        return $this->hasMany(EvaluationResult::class);
    }
}
