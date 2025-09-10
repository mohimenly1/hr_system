<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'leavable_id',
        'leavable_type',
        'leave_type_id', // <-- الحقل الجديد
        'start_date',
        'end_date',
        'reason',
        'status',
        'leave_type', // <-- سنجعله قديماً لاحقاً
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function leavable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the type of the leave.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}

