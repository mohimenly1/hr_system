<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    public $timestamps = false; // لا نحتاج لحقول الوقت هنا
    protected $fillable = ['name', 'description'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}