<?php

namespace App\Policies;

use App\Models\PenaltyType;
use App\Models\User;

class PenaltyTypePolicy
{
    private function canManage(User $user): bool
    {
        return $user->hasPermissionTo('manage penalty settings');
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, PenaltyType $penaltyType): bool
    {
        return $this->canManage($user);
    }
}
