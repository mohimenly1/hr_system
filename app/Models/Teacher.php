<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'phone_number',
        'address',
        'date_of_birth',
        'gender',
        'marital_status',
        'specialization',
        'hire_date',
        'employment_status',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(TeacherContract::class);
    }
    
    /**
     * Get all of the assignments for the Teacher.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    // This is a convenient way to get all subjects a teacher teaches,
    // but the `assignments` relationship is more precise.
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_assignments');
    }

        /**
     * Get all of the teacher's attachments.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}

