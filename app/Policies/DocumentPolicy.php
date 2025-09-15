<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DocumentPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin') || $user->can('manage all documents')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view documents');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Document $document): bool
    {
        // 1. السماح للمنشئ برؤية وثيقته دائماً
        if ($document->created_by_user_id === $user->id) {
            return true;
        }

        // --- التعديل هنا: التحقق بناءً على الأقسام ---
        // 2. جلب ID الأقسام التي يديرها المستخدم
        $managedDeptIds = Department::where('manager_id', $user->id)->pluck('id');

        // 3. جلب ID الأقسام الموجودة في مسار العمل
        $workflowDeptIds = collect($document->workflow_path)->pluck('department_id');

        // 4. هل يدير المستخدم أياً من الأقسام في مسار العمل؟
        return $managedDeptIds->intersect($workflowDeptIds)->isNotEmpty();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create outgoing documents') || $user->can('register incoming documents');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Document $document): bool
    {
        return $document->created_by_user_id === $user->id && $document->status->value === 'draft';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        return $document->created_by_user_id === $user->id && $document->status->value === 'draft';
    }

    /**
     * Determine whether the user can process a workflow action on the model.
     */
    public function process(User $user, Document $document): bool
    {
        // البحث عن الخطوة الحالية النشطة (التي لم تكتمل بعد) في مسار العمل
        $currentStep = $document->workflowSteps()->whereNull('completed_at')->first();

        if (!$currentStep) {
            return false;
        }
        
        // --- التعديل هنا: التحقق بناءً على مدير القسم ---
        $targetDepartment = $currentStep->toDepartment;

        if (!$targetDepartment) {
            return false;
        }
        
        // الشرط الجديد: هل المستخدم الحالي هو مدير القسم المستهدف بالإجراء؟
        return $targetDepartment->manager_id === $user->id;
    }
}

