<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'is_active',
    ];

    /**
     * Get all of the constraints for the template.
     * جلب كل القيود المرتبطة بهذا القالب
     */
    public function constraints(): HasMany
    {
        return $this->hasMany(ScheduleTemplateConstraint::class);
    }
}
