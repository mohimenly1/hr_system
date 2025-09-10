<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTemplateConstraint extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_template_id',
        'constraint_type',
        'value',
    ];

    protected $casts = [
        'value' => 'array', // تحويل قيمة القيد من JSON إلى مصفوفة تلقائياً
    ];

    /**
     * Get the template that owns the constraint.
     * جلب القالب الذي ينتمي إليه هذا القيد
     */
    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }
}
