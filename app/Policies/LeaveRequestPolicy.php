<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->user_id === $user->id) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $leaveRequest->user->manager_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isEmployee() || $user->isAdmin();
    }

    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->isAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return $leaveRequest->status === LeaveRequest::STATUS_PENDING;
        }

        return $leaveRequest->user->manager_id === $user->id
            && $leaveRequest->status === LeaveRequest::STATUS_PENDING;
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->user_id === $user->id
            && $leaveRequest->status === LeaveRequest::STATUS_PENDING;
    }
}
