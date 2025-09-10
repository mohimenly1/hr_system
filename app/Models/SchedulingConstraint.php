<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchedulingConstraint extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedulable_id',
        'schedulable_type',
        'constraint_type',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function schedulable()
    {
        return $this->morphTo();
    }
}
