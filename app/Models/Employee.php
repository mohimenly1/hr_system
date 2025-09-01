<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
