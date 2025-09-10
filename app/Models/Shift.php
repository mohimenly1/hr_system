<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_period_minutes',
    ];

    /**
     * Get all of the assignments for the shift.
     * جلب كل التعيينات المرتبطة بهذا الدوام
     */
    public function assignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }
}
