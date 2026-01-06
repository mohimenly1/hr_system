<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DeductionRule;

class DeductionRulePolicy
{
    private function canManage(User $user): bool
    {
        return $user->hasPermissionTo('manage penalty settings');
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DeductionRule $deductionRule): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DeductionRule $deductionRule): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DeductionRule $deductionRule): bool
    {
        return $this->canManage($user);
    }
}
