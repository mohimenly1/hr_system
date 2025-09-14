<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalParty extends Model
{
    protected $fillable = ['name', 'type', 'contact_info'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}