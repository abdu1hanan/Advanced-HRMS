<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $model->manager_id === $user->id;
        }

        return $user->id === $model->id;
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }
}
