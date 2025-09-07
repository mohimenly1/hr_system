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
        // --- NEW FILLABLE FIELDS ---
        'middle_name',
        'last_name',
        'mother_name',
        'nationality',
        'national_id_number',
        'fingerprint_id', // --- ADDED ---
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
    
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }
    public function leaves(): MorphMany
    {
        return $this->morphMany(Leave::class, 'leavable');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_assignments');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
    
    /**
     * Get all of the work experiences for the teacher.
     * This is now a polymorphic relationship.
     */
    public function workExperiences(): MorphMany
    {
        return $this->morphMany(WorkExperience::class, 'experienceable');
    }
    public function attendances(): MorphMany
{
    return $this->morphMany(Attendance::class, 'attendable');
}
}

