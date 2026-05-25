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

            $request->update(['status' => LeaveRequest::STATUS_CANCELLED]);

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
}
