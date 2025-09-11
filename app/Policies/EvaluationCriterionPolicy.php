<?php

namespace App\Policies;

use App\Models\EvaluationCriterion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EvaluationCriterionPolicy
{
    /**
     * Check if the user can access evaluation settings.
     * التحقق مما إذا كان المستخدم يمكنه الوصول إلى إعدادات التقييم
     */
    private function canManage(User $user): bool
    {
        return $user->hasPermissionTo('manage evaluation settings');
    }
    
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }
    
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, EvaluationCriterion $evaluationCriterion): bool
    {
        return $this->canManage($user);
    }
}
