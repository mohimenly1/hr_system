<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Penalty;
use App\Models\Teacher;
use App\Models\User;

class PenaltyPolicy
{
    /**
     * Determine whether the user can create a penalty for a given person.
     */
    public function create(User $user, Employee|Teacher $penalizable): bool
    {
        if (!$user->can('manage penalties')) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'hr-manager'])) {
            return true;
        }

        if ($user->hasRole('department-manager')) {
            $managedDepartmentIds = Department::where('manager_id', $user->id)->pluck('id');
            return $managedDepartmentIds->contains($penalizable->department_id);
        }

        return false;
    }
}
