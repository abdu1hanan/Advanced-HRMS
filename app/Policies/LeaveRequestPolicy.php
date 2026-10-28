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
        // Requester can always view their own
        if ($leaveRequest->user_id === $user->id) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Any staff or guest manager who is a boss of the requester can view
        if ($user->isStaff() || $user->isGuest()) {
            return in_array($leaveRequest->user_id, $user->subordinate_ids, true);
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Only regular staff or super admin can submit a personal leave request (not guests)
        return $user->isStaff() || $user->isSuperAdmin();
    }

    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        // Only super admin or managers with the requester as a subordinate
        if ($user->isSuperAdmin()) {
            return $leaveRequest->status === LeaveRequest::STATUS_PENDING
                || $leaveRequest->status === 'approved_by_manager';
        }

        if ($user->isStaff() || $user->isGuest()) {
            // The reviewer must be in the requester's boss chain
            return in_array($leaveRequest->user_id, $user->subordinate_ids, true)
                && $leaveRequest->status === LeaveRequest::STATUS_PENDING;
        }

        return false;
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->user_id === $user->id;
    }
}
