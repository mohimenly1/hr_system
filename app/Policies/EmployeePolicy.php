<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any models.
     * من يمكنه رؤية قائمة الموظفين
     */
    public function viewAny(User $user): bool
    {
        // فقط الأدوار الإدارية ومدراء الأقسام يمكنهم رؤية قائمة الموظفين
        return $user->hasAnyRole(['admin', 'hr-manager', 'department-manager']);
    }

    /**
     * Determine whether the user can view the model.
     * من يمكنه عرض ملف موظف معين
     */
    public function view(User $user, Employee $employee): bool
    {
        // الأدوار الإدارية يمكنها رؤية أي موظف
        if ($user->hasAnyRole(['admin', 'hr-manager'])) {
            return true;
        }

        // مدير القسم يمكنه رؤية الموظفين في قسمه فقط
        if ($user->hasRole('department-manager')) {
            // ابحث عن القسم الذي يديره المستخدم الحالي
            $managedDepartment = \App\Models\Department::where('manager_id', $user->id)->first();
            // تحقق إذا كان الموظف المطلوب عرضه ينتمي لهذا القسم
            return $managedDepartment && $employee->department_id === $managedDepartment->id;
        }

        // الموظف يمكنه رؤية ملفه الشخصي فقط
        return $user->id === $employee->user_id;
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
    public function update(User $user, Employee $employee): bool
    {
        // الأدوار الإدارية يمكنها تعديل أي موظف
        if ($user->hasAnyRole(['admin', 'hr-manager',])) {
            return true;
        }

        // مدير القسم يمكنه تعديل الموظفين في قسمه فقط
        if ($user->hasRole('department-manager')) {
            $managedDepartment = \App\Models\Department::where('manager_id', $user->id)->first();
            return $managedDepartment && $employee->department_id === $managedDepartment->id;
        }

        // الموظف يمكنه تعديل ملفه الشخصي فقط
        return $user->id === $employee->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasAnyRole(['admin', 'hr-manager']);
    }
    public function updateFingerprint(User $user, Employee $employee): bool
    {
        // فقط المدير العام ومدير الموارد البشرية يمكنهم تعديل رقم البصمة
        return $user->hasAnyRole(['admin', 'hr-manager']);
    }
}
