<?php

namespace App\Models;

use App\Enums\DocumentPriorityEnum;
use App\Enums\DocumentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'serial_number', 'subject', 'content', 'document_type_id',
        'priority', 'confidentiality_level', 'status', 'created_by_user_id',
        'department_id', 'external_party_id', 'received_date', 'summary',
        'workflow_path', // <-- الإضافة هنا
    ];

    protected $casts = [
        'received_date' => 'date',
        'priority' => DocumentPriorityEnum::class,
        'status' => DocumentStatusEnum::class,
        'workflow_path' => 'array', // <-- الإضافة الأهم هنا لتحويل المصفوفة إلى JSON
    ];

    // علاقة: الوثيقة تنتمي إلى منشئ (مستخدم)
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // علاقة: الوثيقة تنتمي إلى قسم
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // علاقة: الوثيقة تنتمي إلى نوع
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
    
    // علاقة: الوثيقة تنتمي إلى جهة خارجية (اختياري)
    public function externalParty(): BelongsTo
    {
        return $this->belongsTo(ExternalParty::class);
    }
    
    // علاقة: الوثيقة لديها العديد من المرفقات
    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
    }
    
    // علاقة: الوثيقة لديها مسار عمل كامل (عدة خطوات)
    public function workflowSteps(): HasMany
    {
        return $this->hasMany(DocumentWorkflow::class);
    }
}
