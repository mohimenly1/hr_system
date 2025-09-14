<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'uploaded_by_user_id', 'file_name',
        'file_path', 'file_type', 'file_size',
    ];
    
    // علاقة: المرفق ينتمي إلى وثيقة
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
    
    // علاقة: المرفق تم رفعه بواسطة مستخدم
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
    
    // Accessor لجلب الرابط العام للملف مباشرة
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::url($this->file_path),
        );
    }
}