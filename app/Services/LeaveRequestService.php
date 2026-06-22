<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LeaveRequestReviewed;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\LeaveRequestSubmittedNoManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        protected LeaveBalanceService $balanceService
    ) {}

    public function calculateDays(string $start, string $end): float
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after start date.',
            ]);
        }

        return (float) ($startDate->diffInDays($endDate) + 1);
    }

    public function submit(User $user, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($user, $data) {
            $days = $this->calculateDays($data['start_date'], $data['end_date']);
            $year = (int) Carbon::parse($data['start_date'])->year;

            $leaveType = LeaveType::where('id', $data['leave_type_id'])->where('is_active', true)->firstOrFail();

            $balance = $this->balanceService->ensureBalanceRow($user, $leaveType, $year);

            if ($days > $this->balanceService->availableDays($balance)) {
                throw ValidationException::withMessages([
                    'days_count' => 'Insufficient leave balance for this request.',
                ]);
            }

            $this->assertNoOverlap($user, $data['start_date'], $data['end_date']);

            $request = LeaveRequest::create([
                'user_id' => $user->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_count' => $days,
                'reason' => $data['reason'],
                'status' => LeaveRequest::STATUS_PENDING,
            ]);

            $balance->increment('pending', $days);

            $request->load(['user', 'leaveType']);

            if ($user->manager_id) {
                User::find($user->manager_id)?->notify(new LeaveRequestSubmitted($request));
                
                // Also notify Super Admins of the leave request
                User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                    ->each(fn ($admin) => $admin->notify(new LeaveRequestSubmitted($request)));
            } else {
                User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                    ->each(fn ($admin) => $admin->notify(new LeaveRequestSubmittedNoManager($request)));
            }

            return $request;
        });
    }

    public function cancel(LeaveRequest $request, User $user): LeaveRequest
    {
        return DB::transaction(function () use ($request, $user) {
            if ($request->user_id !== $user->id) {
                throw ValidationException::withMessages(['status' => 'Unauthorized.']);
            }

            if ($request->status !== LeaveRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending leave requests can be cancelled.',
                ]);
            }

            $year = (int) $request->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow(
                $request->user,
                $request->leaveType,
                $year
            );

            $balance->decrement('pending', $request->days_count);

            $request->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'change_request' => null,
                'change_request_status' => null,
            ]);

            $request->load(['user', 'leaveType']);

            if ($request->user->manager_id) {
                User::find($request->user->manager_id)?->notify(new \App\Notifications\LeaveRequestCancelled($request, $user->name));
            }

            User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                ->each(fn ($admin) => $admin->notify(new \App\Notifications\LeaveRequestCancelled($request, $user->name)));

            return $request;
        });
    }

    public function approve(LeaveRequest $request, User $reviewer, ?string $comment = null): LeaveRequest
    {
        return $this->review($request, $reviewer, LeaveRequest::STATUS_APPROVED, $comment);
    }

    public function reject(LeaveRequest $request, User $reviewer, string $comment): LeaveRequest
    {
        return $this->review($request, $reviewer, LeaveRequest::STATUS_REJECTED, $comment);
    }

    protected function review(LeaveRequest $request, User $reviewer, string $status, ?string $comment): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $status, $comment) {
            if ($request->status !== LeaveRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'This request has already been processed.',
                ]);
            }

            $year = (int) $request->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow(
                $request->user,
                $request->leaveType,
                $year
            );

            $balance->decrement('pending', $request->days_count);

            if ($status === LeaveRequest::STATUS_APPROVED) {
                $balance->increment('used', $request->days_count);
            }

            $request->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_comment' => $comment,
            ]);

            $request->user->notify(new LeaveRequestReviewed($request->fresh(['leaveType'])));

            return $request;
        });
    }

    public function addComment(LeaveRequest $leaveRequest, User $user, string $commentText): void
    {
        $leaveRequest->comments()->create([
            'user_id' => $user->id,
            'comment' => $commentText,
        ]);

        // Send notifications to all concerned parties except the commenter themselves!
        // 1. Employee
        if ($leaveRequest->user_id !== $user->id) {
            $leaveRequest->user->notify(new \App\Notifications\LeaveRequestCommented($leaveRequest, $user->name));
        }

        // 2. Line Manager
        $managerId = $leaveRequest->user->manager_id;
        if ($managerId && $managerId !== $user->id) {
            $manager = User::find($managerId);
            if ($manager) {
                $manager->notify(new \App\Notifications\LeaveRequestCommented($leaveRequest, $user->name));
            }
        }

        // 3. Super Admins
        $superAdmins = User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))->get();
        foreach ($superAdmins as $admin) {
            if ($admin->id !== $user->id) {
                $admin->notify(new \App\Notifications\LeaveRequestCommented($leaveRequest, $user->name));
            }
        }
    }

    protected function assertNoOverlap(User $user, string $start, string $end): void
    {
        $overlap = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'You already have a leave request overlapping these dates.',
            ]);
        }
    }

    public function requestChange(LeaveRequest $request, array $data, User $user): LeaveRequest
    {
        if ($request->status === LeaveRequest::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'Cannot request changes on a cancelled leave request.',
            ]);
        }

        if ($request->hasPendingChangeRequest()) {
            throw ValidationException::withMessages([
                'change_request' => 'A change request is already pending for this leave request.',
            ]);
        }

        $changePayload = [];
        if (!empty($data['request_cancellation'])) {
            $changePayload['request_cancellation'] = true;
        } else {
            if (!empty($data['start_date']) && $data['start_date'] !== $request->start_date->format('Y-m-d')) {
                $changePayload['start_date'] = $data['start_date'];
            }
            if (!empty($data['end_date']) && $data['end_date'] !== $request->end_date->format('Y-m-d')) {
                $changePayload['end_date'] = $data['end_date'];
            }
            if (!empty($data['leave_type_id']) && (int)$data['leave_type_id'] !== (int)$request->leave_type_id) {
                $changePayload['leave_type_id'] = (int)$data['leave_type_id'];
            }
            if (isset($data['reason']) && $data['reason'] !== $request->reason) {
                $changePayload['reason'] = $data['reason'];
            }
        }

        if (empty($changePayload)) {
            throw ValidationException::withMessages([
                'change_request' => 'No changes were requested.',
            ]);
        }

        if (empty($changePayload['request_cancellation'])) {
            $newStartDate = !empty($changePayload['start_date']) ? $changePayload['start_date'] : $request->start_date->format('Y-m-d');
            $newEndDate = !empty($changePayload['end_date']) ? $changePayload['end_date'] : $request->end_date->format('Y-m-d');
            $newLeaveTypeId = !empty($changePayload['leave_type_id']) ? (int)$changePayload['leave_type_id'] : $request->leave_type_id;
            
            $newDaysCount = $this->calculateDays($newStartDate, $newEndDate);
            $newYear = (int)Carbon::parse($newStartDate)->year;
            $newLeaveType = LeaveType::where('id', $newLeaveTypeId)->where('is_active', true)->firstOrFail();
            
            // Check date overlap with other requests
            if ($newStartDate !== $request->start_date->format('Y-m-d') || $newEndDate !== $request->end_date->format('Y-m-d')) {
                $overlap = LeaveRequest::query()
                    ->where('user_id', $request->user_id)
                    ->where('id', '!=', $request->id)
                    ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
                    ->where(function ($q) use ($newStartDate, $newEndDate) {
                        $q->whereBetween('start_date', [$newStartDate, $newEndDate])
                            ->orWhereBetween('end_date', [$newStartDate, $newEndDate])
                            ->orWhere(function ($q2) use ($newStartDate, $newEndDate) {
                                $q2->where('start_date', '<=', $newStartDate)
                                    ->where('end_date', '>=', $newEndDate);
                            });
                    })
                    ->exists();

                if ($overlap) {
                    throw ValidationException::withMessages([
                        'change_request' => 'The requested dates overlap with another leave request.',
                    ]);
                }
            }

            // Check hypothetical balance
            $originalBalance = $this->balanceService->ensureBalanceRow($request->user, $request->leaveType, (int)$request->start_date->year);
            $newBalance = $this->balanceService->ensureBalanceRow($request->user, $newLeaveType, $newYear);
            
            $originalImpact = 0;
            if (in_array($request->status, [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $originalImpact = $request->days_count;
            }

            $hypotheticalAvailable = $this->balanceService->availableDays($newBalance);
            if ($newLeaveTypeId === (int)$request->leave_type_id && (int)$request->start_date->year === $newYear) {
                $hypotheticalAvailable += $originalImpact;
            }

            if ($newDaysCount > $hypotheticalAvailable) {
                throw ValidationException::withMessages([
                    'change_request' => 'Insufficient leave balance for the requested change.',
                ]);
            }
        }

        $request->update([
            'change_request' => $changePayload,
            'change_request_status' => 'pending',
        ]);

        $managerId = $request->user->manager_id;
        if ($managerId) {
            User::find($managerId)?->notify(new \App\Notifications\LeaveRequestChangeRequested($request));
        }

        User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
            ->each(fn ($admin) => $admin->notify(new \App\Notifications\LeaveRequestChangeRequested($request)));

        return $request;
    }

    public function approveChangeRequest(LeaveRequest $request, User $reviewer): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reviewer) {
            if (!in_array($request->change_request_status, ['pending', 'approved_by_manager', 'rejected_by_manager']) || empty($request->change_request)) {
                throw ValidationException::withMessages([
                    'change_request_status' => 'No pending change request exists.',
                ]);
            }

            if (!$reviewer->isSuperAdmin()) {
                // If Line Manager approves, we only set change_request_status to 'approved_by_manager'
                // and do not apply/merge the payload yet.
                $request->update([
                    'change_request_status' => 'approved_by_manager',
                ]);
                return $request;
            }

            $payload = $request->change_request;
            $year = (int)$request->start_date->year;

            if (!empty($payload['request_cancellation'])) {
                $balance = $this->balanceService->ensureBalanceRow(
                    $request->user,
                    $request->leaveType,
                    $year
                );

                if ($request->status === LeaveRequest::STATUS_APPROVED) {
                    $balance->decrement('used', $request->days_count);
                } else if (in_array($request->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                    $balance->decrement('pending', $request->days_count);
                }

                $request->update([
                    'status' => LeaveRequest::STATUS_CANCELLED,
                    'change_request' => null,
                    'change_request_status' => 'approved',
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ]);

                $request->user->notify(new \App\Notifications\LeaveRequestChangeProcessed($request, 'approved', $reviewer->name));
                return $request;
            }

            $newStartDate = !empty($payload['start_date']) ? $payload['start_date'] : $request->start_date->format('Y-m-d');
            $newEndDate = !empty($payload['end_date']) ? $payload['end_date'] : $request->end_date->format('Y-m-d');
            $newLeaveTypeId = !empty($payload['leave_type_id']) ? (int)$payload['leave_type_id'] : $request->leave_type_id;
            $newReason = isset($payload['reason']) ? $payload['reason'] : $request->reason;

            $newDaysCount = $this->calculateDays($newStartDate, $newEndDate);
            $newYear = (int)Carbon::parse($newStartDate)->year;

            $newLeaveType = LeaveType::where('id', $newLeaveTypeId)->where('is_active', true)->firstOrFail();

            if ($newStartDate !== $request->start_date->format('Y-m-d') || $newEndDate !== $request->end_date->format('Y-m-d')) {
                $overlap = LeaveRequest::query()
                    ->where('user_id', $request->user_id)
                    ->where('id', '!=', $request->id)
                    ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
                    ->where(function ($q) use ($newStartDate, $newEndDate) {
                        $q->whereBetween('start_date', [$newStartDate, $newEndDate])
                            ->orWhereBetween('end_date', [$newStartDate, $newEndDate])
                            ->orWhere(function ($q2) use ($newStartDate, $newEndDate) {
                                $q2->where('start_date', '<=', $newStartDate)
                                    ->where('end_date', '>=', $newEndDate);
                            });
                    })
                    ->exists();

                if ($overlap) {
                    throw ValidationException::withMessages([
                        'change_request' => 'The requested dates overlap with another leave request.',
                    ]);
                }
            }

            $originalBalance = $this->balanceService->ensureBalanceRow($request->user, $request->leaveType, $year);
            if ($request->status === LeaveRequest::STATUS_APPROVED) {
                $originalBalance->decrement('used', $request->days_count);
            } else if (in_array($request->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $originalBalance->decrement('pending', $request->days_count);
            }

            $newBalance = $this->balanceService->ensureBalanceRow($request->user, $newLeaveType, $newYear);
            if ($newDaysCount > $this->balanceService->availableDays($newBalance)) {
                throw ValidationException::withMessages([
                    'change_request' => 'Insufficient leave balance for the requested change.',
                ]);
            }

            if ($request->status === LeaveRequest::STATUS_APPROVED) {
                $newBalance->increment('used', $newDaysCount);
            } else if (in_array($request->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $newBalance->increment('pending', $newDaysCount);
            }

            $request->update([
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'days_count' => $newDaysCount,
                'leave_type_id' => $newLeaveTypeId,
                'reason' => $newReason,
                'change_request' => null,
                'change_request_status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $request->user->notify(new \App\Notifications\LeaveRequestChangeProcessed($request, 'approved', $reviewer->name));

            return $request;
        });
    }

    public function rejectChangeRequest(LeaveRequest $request, User $reviewer): LeaveRequest
    {
        if (!in_array($request->change_request_status, ['pending', 'approved_by_manager', 'rejected_by_manager'])) {
            throw ValidationException::withMessages([
                'change_request_status' => 'No pending change request exists.',
            ]);
        }

        if (!$reviewer->isSuperAdmin()) {
            // If Line Manager rejects, we only set change_request_status to 'rejected_by_manager'
            // and do not clear the payload.
            $request->update([
                'change_request_status' => 'rejected_by_manager',
            ]);
            return $request;
        }

        $request->update([
            'change_request' => null,
            'change_request_status' => 'rejected',
        ]);

        $request->user->notify(new \App\Notifications\LeaveRequestChangeProcessed($request, 'rejected', $reviewer->name));

        return $request;
    }
}
