<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Penalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'penalizable_id', 'penalizable_type', 'penalty_type_id',
        'issued_by_user_id', 'reason', 'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
    ];

    public function penalizable(): MorphTo
    {
        return $this->morphTo();
    }

    public function penaltyType(): BelongsTo
    {
        return $this->belongsTo(PenaltyType::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
