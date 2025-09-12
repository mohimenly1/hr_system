<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Models\Teacher;
use App\Models\User;

class PerformanceEvaluationPolicy
{
    /**
     * Determine whether the user can create a performance evaluation for a given person (employee or teacher).
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Employee|\App\Models\Teacher  $evaluable
     * @return bool
     */
    public function create(User $user, Employee|Teacher $evaluable): bool
    {
        // 1. يجب أن يمتلك المستخدم الصلاحية العامة أولاً
        if (!$user->can('manage evaluations')) {
            return false;
        }

        // 2. المدير العام ومدير الموارد البشرية يمكنهم تقييم أي شخص
        if ($user->hasAnyRole(['admin', 'hr-manager'])) {
            return true;
        }

        // 3. مدير القسم يمكنه فقط تقييم الموظفين أو المعلمين في الأقسام التي يديرها
        if ($user->hasRole('department-manager')) {
            $managedDepartmentIds = Department::where('manager_id', $user->id)->pluck('id');
            return $managedDepartmentIds->contains($evaluable->department_id);
        }

        return false;
    }

    // يمكنك إضافة دوال 'update', 'view' هنا لاحقاً بنفس المنطق
}

