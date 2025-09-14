<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'from_user_id', 'to_user_id', 'action',
        'notes', 'signature_image_path', 'due_date', 'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    // علاقة: هذه الخطوة تنتمي إلى وثيقة
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // علاقة: هذه الخطوة جاءت من مستخدم
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    // علاقة: هذه الخطوة موجهة إلى مستخدم
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}