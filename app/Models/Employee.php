<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany; // --- IMPORT THIS ---
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Models\Department;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'employee_id',
        'job_title',
        'phone_number',
        'address',
        'hire_date',
        'date_of_birth',
        'gender',
        'employment_status',
              // --- NEW FILLABLE FIELDS ---
              'middle_name',
              'last_name',
              'mother_name',
              'marital_status',
              'nationality',
              'national_id_number',
              'fingerprint_id', // --- ADDED ---
    ];

    /**
     * Get the user that owns the employee profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department that the employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

       /**
     * Get all attachments for the employee.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function leaves(): MorphMany
    {
        return $this->morphMany(Leave::class, 'leavable');
    }
      /**
     * Get all of the work experiences for the employee.
     */
    public function workExperiences(): MorphMany
    {
        return $this->morphMany(WorkExperience::class, 'experienceable');
    }
    public function attendances(): MorphMany
{
    return $this->morphMany(Attendance::class, 'attendable');
}

public function shiftAssignment(): MorphOne
{
    return $this->morphOne(ShiftAssignment::class, 'shiftable');
}

public function constraints(): MorphMany
    {
        return $this->morphMany(SchedulingConstraint::class, 'schedulable');
    }
public function setPersonnelType($type, $modelClass)
{
    $this->personnel_type = $type;
    $this->model_class = $modelClass;
    return $this;
}



/**
 * Get the department managed by the employee (if any).
 * The foreign key 'manager_id' on the 'departments' table
 * is matched against the 'user_id' of this employee.
 */
public function managedDepartments(): HasMany
{
    return $this->hasMany(Department::class, 'manager_id', 'user_id');
}

}
