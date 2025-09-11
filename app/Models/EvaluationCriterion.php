<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
