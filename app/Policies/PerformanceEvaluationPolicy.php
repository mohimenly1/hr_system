<?php

namespace App\Policies;

use App\Models\PerformanceEvaluation;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Department;

class PerformanceEvaluationPolicy
{
    public function create(User $user, Teacher $teacher): bool
    {
        if (!$user->can('manage evaluations')) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'hr-manager'])) {
            return true;
        }

        if ($user->hasRole('department-manager')) {
            $managedDepartments = Department::where('manager_id', $user->id)->pluck('id');
            return $managedDepartments->contains($teacher->department_id);
        }

        return false;
    }
}

