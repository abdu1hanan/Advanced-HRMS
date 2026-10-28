<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        // Super admins can see all; staff & guest supervisors can view subordinates
        return $user->isSuperAdmin() || $user->isStaff() || $user->isGuest();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Staff and guest supervisors can view their own profile or any subordinate
        if ($user->isStaff() || $user->isGuest()) {
            return $model->manager_id === $user->id || $user->id === $model->id || in_array($model->id, $user->subordinate_ids, true);
        }

        return $user->id === $model->id;
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }
}
