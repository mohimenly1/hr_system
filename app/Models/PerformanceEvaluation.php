<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

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

    protected static function booted(): void
    {
        static::created(function ($model) {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'subject_id' => $model->id,
                'subject_type' => get_class($model),
                'action' => 'created',
                'details' => ['name' => $model->title ?? $model->penaltyType->name],
            ]);
        });
    }
}
