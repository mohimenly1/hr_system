<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeacherPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr-manager', 'department-manager']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Teacher $teacher): bool
    {
        if ($user->hasAnyRole(['admin', 'hr-manager'])) {
            return true;
        }

        if ($user->hasRole('department-manager')) {
            $managedDepartment = \App\Models\Department::where('manager_id', $user->id)->first();
            return $managedDepartment && $teacher->department_id === $managedDepartment->id;
        }

        return $user->id === $teacher->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Teacher $teacher): bool
    {
        return $this->view($user, $teacher);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Teacher $teacher): bool
    {
        return $user->hasAnyRole(['admin', 'hr-manager']);
    }
    public function updateFingerprint(User $user, Teacher $teacher): bool
    {
        // فقط المدير العام ومدير الموارد البشرية يمكنهم تعديل رقم البصمة
        return $user->hasAnyRole(['admin', 'hr-manager']);
    }
}
