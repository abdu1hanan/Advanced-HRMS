<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\CompanyHoliday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\RemoteWorkLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LeaveCancellationRequested;
use App\Notifications\LeaveRequestCancelled;
use App\Notifications\LeaveRequestChangeProcessed;
use App\Notifications\LeaveRequestChangeRequested;
use App\Notifications\LeaveRequestReviewed;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\LeaveRequestSubmittedNoManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        protected LeaveBalanceService $balanceService,
        protected LeaveApprovalChainService $chainService,
        protected HierarchyService $hierarchyService
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

        $days = 0.0;
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            if (!$current->isSaturday() && !$current->isSunday()) {
                $days += 1.0;
            }
            $current->addDay();
        }

        if ($days === 0.0) {
            throw ValidationException::withMessages([
                'start_date' => 'Selected date range contains only weekend days (Saturday and Sunday).',
            ]);
        }

        return $days;
    }

    public function submit(User $user, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($user, $data) {
            $leaveType = LeaveType::where('id', $data['leave_type_id'])->where('is_active', true)->firstOrFail();

            if ($leaveType->slug === 'absent') {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'You cannot apply for Absent leave type.',
                ]);
            }

            $days = $this->calculateDays($data['start_date'], $data['end_date']);
            if ($leaveType->is_half_day) {
                $days = $days * 0.5;
            }
            $year = (int) Carbon::parse($data['start_date'])->year;

            $balance = $this->balanceService->ensureBalanceRow($user, $leaveType, $year);

            if ($leaveType->slug !== 'unpaid' && $leaveType->slug !== 'emergency' && $days > $this->balanceService->availableDays($balance)) {
                throw ValidationException::withMessages([
                    'start_date' => 'Insufficient leave balance for this request.',
                ]);
            }

            $this->assertNoOverlap($user, $data['start_date'], $data['end_date']);
            $this->assertNotHoliday($user, $data['start_date'], $data['end_date']);
            $this->assertNotAbsent($user, $data['start_date'], $data['end_date']);
            $this->assertNotRemote($user, $data['start_date'], $data['end_date']);

            $balance->increment('pending', $days);

            $leaveRequest = LeaveRequest::create([
                'user_id' => $user->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_count' => $days,
                'reason' => $data['reason'],
                'status' => LeaveRequest::STATUS_PENDING,
            ]);

            $target = $this->chainService->initialise($leaveRequest);

            $immediateBoss = $this->chainService->getNextApprover($leaveRequest);

            if ($immediateBoss) {
                $immediateBoss->notify(new LeaveRequestSubmitted($leaveRequest));
                
                // Super Admin always stays notified of all new leave submissions
                User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                    ->each(fn ($admin) => $admin->notify(new LeaveRequestSubmitted($leaveRequest)));
            } else {
                User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                    ->each(fn ($admin) => $admin->notify(new LeaveRequestSubmittedNoManager($leaveRequest)));
            }

            return $leaveRequest;
        });
    }

    /**
     * Cancel a leave request.
     * - Future leaves (start_date > today): cancelled immediately, balance refunded, managers & SA notified.
     * - Past/Active leaves (start_date <= today): submitted as a cancellation request directly to Super Admin.
     */
    public function cancel(LeaveRequest $request, User $user, ?string $reason = null): array
    {
        return DB::transaction(function () use ($request, $user, $reason) {
            if ($request->user_id !== $user->id) {
                throw ValidationException::withMessages(['status' => 'Unauthorized.']);
            }

            if (in_array($request->status, [LeaveRequest::STATUS_CANCELLED, LeaveRequest::STATUS_REJECTED])) {
                throw ValidationException::withMessages([
                    'status' => 'This leave request is already closed or cancelled.',
                ]);
            }

            if ($request->change_request_status === 'pending' && !empty($request->change_request['request_cancellation'])) {
                throw ValidationException::withMessages([
                    'status' => 'A cancellation request is already pending review for this leave.',
                ]);
            }

            if (empty(trim((string)$reason))) {
                throw ValidationException::withMessages([
                    'reason' => 'A reason for cancellation is required.',
                ]);
            }

            $requiresApproval = Carbon::parse($request->start_date)->lte(Carbon::today()) && $request->status === LeaveRequest::STATUS_APPROVED;
            $year = (int) $request->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($request->user, $request->leaveType, $year);

            if (!$requiresApproval) {
                // Direct Instant Cancellation
                $oldStatus = $request->status;
                $target = (int) $request->approval_chain_target;
                $progress = (int) $request->approval_chain_progress;
                $hasReachedSA = ($target === 0 || $progress >= $target || $oldStatus === LeaveRequest::STATUS_APPROVED || !empty($request->reviewed_by));

                if ($request->status === LeaveRequest::STATUS_APPROVED) {
                    $balance->update(['used' => max(0.0, $balance->used - $request->days_count)]);
                } elseif (in_array($request->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                    $balance->update(['pending' => max(0.0, $balance->pending - $request->days_count)]);
                }

                $existingChangeReq = $request->change_request ?? [];
                unset($existingChangeReq['request_cancellation']);

                $request->update([
                    'status'                => LeaveRequest::STATUS_CANCELLED,
                    'change_request_status' => null,
                    'change_request'        => array_merge($existingChangeReq, [
                        'cancel_reason' => $reason,
                        'cancelled_by'  => $user->id,
                        'cancelled_at'  => now()->toDateTimeString(),
                        'old_status'    => $oldStatus,
                    ]),
                ]);

                $request->load(['user', 'leaveType']);

                // 1. Notify only line managers up the allowed chain who were in the loop
                if ($target > 0) {
                    $bossChain = $this->hierarchyService->getBossChain($request->user);
                    $targetBosses = array_slice($bossChain, 0, $target);

                    $inLoopCount = 1;
                    if ($oldStatus === LeaveRequest::STATUS_APPROVED || $progress >= $target) {
                        $inLoopCount = count($targetBosses);
                    } elseif ($oldStatus === 'rejected_by_manager') {
                        $inLoopCount = min(count($targetBosses), max(1, $request->rejectionLevel()));
                    } else {
                        $inLoopCount = min(count($targetBosses), $progress + 1);
                    }

                    $managersToNotify = array_slice($targetBosses, 0, $inLoopCount);
                    foreach ($managersToNotify as $boss) {
                        $boss->notify(new LeaveRequestCancelled($request, $user->name, $reason));
                    }
                }

                // 2. Super Admin ALWAYS gets cancellation notification
                User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                    ->each(fn ($admin) => $admin->notify(new LeaveRequestCancelled($request, $user->name, $reason)));

                return ['instant' => true, 'request' => $request];
            } else {
                // Past / Active + Fully Approved Leave — Request cancellation to Super Admin
                $request->update([
                    'change_request' => [
                        'request_cancellation' => true,
                        'cancel_reason'        => $reason,
                        'requested_by'         => $user->id,
                        'requested_at'         => now()->toDateTimeString(),
                    ],
                    'change_request_status' => 'pending',
                ]);

                $request->load(['user', 'leaveType']);

                // Super Admin gets actionable notification
                User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                    ->each(fn ($admin) => $admin->notify(new LeaveCancellationRequested($request, $user->name, $reason, true)));

                // Chain managers get informational notification
                $bossChain = $this->hierarchyService->getBossChain($request->user);
                $target = (int) $request->approval_chain_target;
                $targetBosses = $target > 0 ? array_slice($bossChain, 0, $target) : $bossChain;
                foreach ($targetBosses as $boss) {
                    $boss->notify(new LeaveCancellationRequested($request, $user->name, $reason, false));
                }

                return ['instant' => false, 'request' => $request];
            }
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
            if ($request->status !== LeaveRequest::STATUS_PENDING && $request->status !== 'approved_by_manager') {
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

            $balance->update(['pending' => max(0.0, $balance->pending - $request->days_count)]);

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

            if ($reviewer->isSuperAdmin()) {
                $bossChain = $this->hierarchyService->getBossChain($request->user);
                foreach ($bossChain as $boss) {
                    $boss->notify(new LeaveRequestReviewed($request->fresh(['leaveType'])));
                }
            }

            return $request;
        });
    }

    public function addComment(LeaveRequest $leaveRequest, User $user, string $commentText, bool $isHiddenFromEmployee = false): void
    {
        $leaveRequest->comments()->create([
            'user_id' => $user->id,
            'comment' => $commentText,
            'is_hidden_from_employee' => $isHiddenFromEmployee,
        ]);

        $userDesig = $user->designation?->name 
            ?? ($user->special_order === 1 ? 'CEO' : ($user->special_order === 2 ? 'CTO' : ($user->special_order === 3 ? 'HR Manager' : null)));

        if ($leaveRequest->user_id !== $user->id && !$isHiddenFromEmployee) {
            $leaveRequest->user->notify(new \App\Notifications\LeaveRequestCommented($leaveRequest, $user->name, $userDesig));
        }

        $target = (int) $leaveRequest->approval_chain_target;
        $progress = (int) $leaveRequest->approval_chain_progress;
        $status = $leaveRequest->status;

        if ($target > 0) {
            $bossChain = $this->hierarchyService->getBossChain($leaveRequest->user);
            $targetBosses = array_slice($bossChain, 0, $target);

            $inLoopCount = 1;
            if ($status === LeaveRequest::STATUS_APPROVED || $progress >= $target) {
                $inLoopCount = count($targetBosses);
            } elseif ($status === 'rejected_by_manager') {
                $inLoopCount = min(count($targetBosses), max(1, $leaveRequest->rejectionLevel()));
            } else {
                $inLoopCount = min(count($targetBosses), $progress + 1);
            }

            $inLoopBosses = array_slice($targetBosses, 0, $inLoopCount);
            foreach ($inLoopBosses as $boss) {
                if ($boss->id !== $user->id) {
                    $boss->notify(new \App\Notifications\LeaveRequestCommented($leaveRequest, $user->name, $userDesig));
                }
            }
        }

        $superAdmins = User::whereHas('role', fn($q) => $q->where('name', Role::SUPER_ADMIN))->get();
        foreach ($superAdmins as $admin) {
            if ($admin->id !== $user->id) {
                $admin->notify(new \App\Notifications\LeaveRequestCommented($leaveRequest, $user->name, $userDesig));
            }
        }
    }

    protected function assertNoOverlap(User $user, string $start, string $end, ?int $ignoreId = null): void
    {
        $query = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                LeaveRequest::STATUS_REJECTED,
                LeaveRequest::STATUS_REJECTED_BY_MANAGER,
                LeaveRequest::STATUS_CANCELLED
            ]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $overlap = $query->where(function ($q) use ($start, $end) {
            $q->where('start_date', '<=', $end)
              ->where('end_date', '>=', $start);
        })->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'You already have an active, pending, or under-review leave request overlapping these dates.',
            ]);
        }
    }

    protected function assertNotHoliday(User $user, string $start, string $end): void
    {
        $holidays = CompanyHoliday::with(['departments', 'users'])
            ->whereBetween('date', [$start, $end])
            ->get();

        $userHolidays = $holidays->filter(function ($h) use ($user) {
            $dt = Carbon::parse($h->date);
            return !$dt->isSaturday() && !$dt->isSunday() && $h->appliesToUser($user);
        });

        if ($userHolidays->isNotEmpty()) {
            $names = $userHolidays
                ->map(fn($h) => Carbon::parse($h->date)->format('M d') . ' (' . $h->name . ')')
                ->join(', ');
            throw ValidationException::withMessages([
                'start_date' => "The following date(s) are official holidays for you: {$names}. You do not need to apply for leave on holidays.",
            ]);
        }
    }

    protected function assertNotAbsent(User $user, string $start, string $end): void
    {
        $absentRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->first();

        if ($absentRecord) {
            $dateStr = Carbon::parse($absentRecord->date)->format('M d, Y');
            throw ValidationException::withMessages([
                'start_date' => "You have been marked absent by HR on {$dateStr}. You cannot apply for leave on a day you are already marked absent. Please contact HR to remove the absent record first.",
            ]);
        }
    }

    protected function assertNotRemote(User $user, string $start, string $end): void
    {
        $remoteRecord = RemoteWorkLog::where('user_id', $user->id)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->first();

        if ($remoteRecord) {
            $dateStr = Carbon::parse($remoteRecord->date)->format('M d, Y');
            throw ValidationException::withMessages([
                'start_date' => "You are already marked as working remotely on {$dateStr}. You cannot apply for leave on a remote work day. Please contact HR to resolve this first.",
            ]);
        }
    }

    /**
     * Request changes on an in-flight leave request.
     *
     * Admin-marked leaves (is_admin_marked = true):
     *   - No chain reset, balance stays `used`, only SuperAdmin notified.
     *
     * Normal leaves (including fully-approved non-admin-marked):
     *   - Updates in-place, preserves original snapshot, restarts Approval Chain from Level 1.
     */
    public function requestChange(LeaveRequest $request, array $data, User $user): LeaveRequest
    {
        return DB::transaction(function () use ($request, $data, $user) {
            if ($request->user_id !== $user->id) {
                throw ValidationException::withMessages(['status' => 'Unauthorized.']);
            }

            if (in_array($request->status, [LeaveRequest::STATUS_CANCELLED, LeaveRequest::STATUS_REJECTED])) {
                throw ValidationException::withMessages([
                    'status' => 'Cannot request changes on a cancelled or rejected leave request.',
                ]);
            }

            $newStartDate = !empty($data['start_date']) ? $data['start_date'] : $request->start_date->format('Y-m-d');
            $newEndDate   = !empty($data['end_date']) ? $data['end_date'] : $request->end_date->format('Y-m-d');
            $newTypeId    = !empty($data['leave_type_id']) ? (int)$data['leave_type_id'] : (int)$request->leave_type_id;
            $newReason    = isset($data['reason']) ? $data['reason'] : $request->reason;

            // Check if any change actually made
            $isSameDate = ($newStartDate === $request->start_date->format('Y-m-d') && $newEndDate === $request->end_date->format('Y-m-d'));
            $isSameType = ($newTypeId === (int)$request->leave_type_id);
            $isSameReason = ($newReason === $request->reason);

            if ($isSameDate && $isSameType && $isSameReason) {
                throw ValidationException::withMessages([
                    'change_request' => 'No changes were detected in your request.',
                ]);
            }

            $newLeaveType = LeaveType::where('id', $newTypeId)->where('is_active', true)->firstOrFail();

            if ($newLeaveType->slug === 'absent') {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'You cannot change the leave type to Absent.',
                ]);
            }

            $newDaysCount = $this->calculateDays($newStartDate, $newEndDate);
            if ($newLeaveType->is_half_day) {
                $newDaysCount = $newDaysCount * 0.5;
            }
            $newYear      = (int) Carbon::parse($newStartDate)->year;

            $this->assertNoOverlap($user, $newStartDate, $newEndDate, $request->id);
            $this->assertNotHoliday($user, $newStartDate, $newEndDate);
            $this->assertNotAbsent($user, $newStartDate, $newEndDate);
            $this->assertNotRemote($user, $newStartDate, $newEndDate);

            // Capture original values for side-by-side comparison in UI
            $originalSnapshot = [
                'start_date'      => $request->start_date->format('Y-m-d'),
                'end_date'        => $request->end_date->format('Y-m-d'),
                'days_count'      => (float) $request->days_count,
                'leave_type_id'   => (int) $request->leave_type_id,
                'leave_type_name' => $request->leaveType->name,
                'reason'          => $request->reason,
            ];

            $oldStatus   = $request->status;
            $target      = (int) $request->approval_chain_target;
            $oldProgress = (int) $request->approval_chain_progress;

            // ── ADMIN-MARKED LEAVE PATH ─────────────────────────────────────────────
            // Leave was directly granted by Super Admin. Skip chain reset entirely.
            // Balance stays `used`. Only SuperAdmin gets notified (actionable).
            if ($request->is_admin_marked) {
                $request->update([
                    'start_date'            => $newStartDate,
                    'end_date'              => $newEndDate,
                    'days_count'            => $newDaysCount,
                    'leave_type_id'         => $newTypeId,
                    'reason'                => $newReason,
                    'change_request'        => [
                        'original'   => $originalSnapshot,
                        'updated_at' => now()->toDateTimeString(),
                    ],
                    'change_request_status' => 'pending',
                ]);

                $request->comments()->create([
                    'user_id'                 => $user->id,
                    'comment'                 => '[System] Changes were requested by employee on an admin-marked leave. Pending review.',
                    'is_hidden_from_employee' => false,
                ]);

                User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                    ->each(fn ($admin) => $admin->notify(new LeaveRequestChangeRequested($request, true, 0, 0)));

                // Also notify all managers up in hierarchy (informational - chain bypassed)
                $bossChain = $this->hierarchyService->getBossChain($request->user);
                foreach ($bossChain as $boss) {
                    $boss->notify(new LeaveRequestChangeRequested($request, false, 0, 0));
                }

                return $request;
            }

            // ── NORMAL LEAVE PATH ───────────────────────────────────────────────────
            // Reconcile balance: for approved leaves move used→available; for pending release pending
            $oldYear    = (int) $request->start_date->year;
            $oldBalance = $this->balanceService->ensureBalanceRow($user, $request->leaveType, $oldYear);
            $newBalance = $this->balanceService->ensureBalanceRow($user, $newLeaveType, $newYear);

            if ($oldStatus === LeaveRequest::STATUS_APPROVED) {
                $oldBalance->update(['used' => max(0.0, $oldBalance->used - $request->days_count)]);
            } else {
                $oldBalance->update(['pending' => max(0.0, $oldBalance->pending - $request->days_count)]);
            }

            if ($newLeaveType->slug !== 'unpaid' && $newLeaveType->slug !== 'emergency' && $newDaysCount > $this->balanceService->availableDays($newBalance->fresh())) {
                // Restore old balance before throwing
                if ($oldStatus === LeaveRequest::STATUS_APPROVED) {
                    $oldBalance->increment('used', $request->days_count);
                } else {
                    $oldBalance->increment('pending', $request->days_count);
                }
                throw ValidationException::withMessages([
                    'start_date' => 'Insufficient leave balance for the requested change.',
                ]);
            }

            $newBalance->increment('pending', $newDaysCount);

            $bossChain    = $this->hierarchyService->getBossChain($request->user);
            $targetBosses = array_slice($bossChain, 0, $target);

            $inLoopCount = 1;
            if ($oldStatus === LeaveRequest::STATUS_APPROVED || $oldProgress >= $target) {
                $inLoopCount = count($targetBosses);
            } elseif ($oldStatus === 'rejected_by_manager') {
                $inLoopCount = min(count($targetBosses), max(1, $request->rejectionLevel()));
            } else {
                $inLoopCount = min(count($targetBosses), $oldProgress + 1);
            }
            $inLoopBosses = array_slice($targetBosses, 0, $inLoopCount);

            // In-place update of parameters
            $request->update([
                'start_date'            => $newStartDate,
                'end_date'              => $newEndDate,
                'days_count'            => $newDaysCount,
                'leave_type_id'         => $newTypeId,
                'reason'                => $newReason,
                'change_request'        => [
                    'original'   => $originalSnapshot,
                    'updated_at' => now()->toDateTimeString(),
                ],
                'change_request_status' => 'pending',
            ]);

            // Construct contextual system comment prior to chain reset
            $chainEntries = $request->approvalChain;
            $lastActionEntry = $chainEntries->sortBy('level')->last(fn($e) => in_array($e->action, ['approved', 'rejected']));

            if ($lastActionEntry) {
                $approverName = $lastActionEntry->approver?->name ?? 'Line Manager';
                $level = (int) $lastActionEntry->level;
                if ($lastActionEntry->action === 'approved') {
                    $commentText = "Changes were requested by employee after {$approverName} approved [Level {$level} of {$target}]. Approval chain has been reset to Level 1.";
                } else {
                    $commentText = "Changes were requested by employee after {$approverName} rejected [Level {$level} of {$target}]. Approval chain has been reset to Level 1.";
                }
            } else {
                $l1Boss = $targetBosses[0] ?? null;
                if ($target > 0 && $l1Boss) {
                    $commentText = "Changes were requested by employee before {$l1Boss->name} made a decision. Approval chain has been reset to Level 1.";
                } else {
                    $commentText = "Changes were requested by employee before any decision was made. Approval chain has been reset to Level 1.";
                }
            }

            // Restart Approval Chain from Level 1
            $this->chainService->resetChain($request);

            // Insert system comment in conversation
            $request->comments()->create([
                'user_id'                 => $user->id,
                'comment'                 => $commentText,
                'is_hidden_from_employee' => false,
            ]);

            $immediateBoss = $this->chainService->getNextApprover($request);

            // 1. Notify Level 1 boss (Actionable)
            if ($immediateBoss) {
                $immediateBoss->notify(new LeaveRequestChangeRequested($request, true, 1, $target));
            }

            // 2. Super Admin ALWAYS gets notified of change requests
            User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                ->each(fn ($admin) => $admin->notify(new LeaveRequestChangeRequested($request, empty($immediateBoss), 1, $target)));

            // 3. Notify higher bosses in chain who were ALREADY in the loop prior to reset (Informational)
            foreach ($inLoopBosses as $boss) {
                if ($immediateBoss && $boss->id === $immediateBoss->id) {
                    continue;
                }
                $boss->notify(new LeaveRequestChangeRequested($request, false, 1, $target));
            }

            return $request;
        });
    }

    /**
     * Super Admin approves a past/active leave cancellation request.
     */
    public function approveCancellationRequest(LeaveRequest $request, User $reviewer): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reviewer) {
            if ($request->change_request_status !== 'pending' || empty($request->change_request['request_cancellation'])) {
                throw ValidationException::withMessages([
                    'status' => 'No pending cancellation request exists for this leave.',
                ]);
            }

            $year = (int) $request->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($request->user, $request->leaveType, $year);

            if ($request->status === LeaveRequest::STATUS_APPROVED) {
                $balance->update(['used' => max(0.0, $balance->used - $request->days_count)]);
            } elseif (in_array($request->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $balance->update(['pending' => max(0.0, $balance->pending - $request->days_count)]);
            }

            $cancelReason = $request->change_request['cancel_reason'] ?? 'Cancellation approved by Super Admin';

            $target = (int) $request->approval_chain_target;
            $progress = (int) $request->approval_chain_progress;
            $oldStatus = $request->status;

            $request->update([
                'status'                => LeaveRequest::STATUS_CANCELLED,
                'change_request'        => [
                    'cancel_reason' => $cancelReason,
                    'approved_by'   => $reviewer->id,
                    'approved_at'   => now()->toDateTimeString(),
                ],
                'change_request_status' => 'approved',
                'reviewed_by'           => $reviewer->id,
                'reviewed_at'           => now(),
            ]);

            $request->load(['user', 'leaveType']);

            $reviewerDesig = $reviewer->designation?->name 
                ?? ($reviewer->special_order === 1 ? 'CEO' : ($reviewer->special_order === 2 ? 'CTO' : ($reviewer->special_order === 3 ? 'HR Manager' : null)));

            // Notify employee
            $request->user->notify(new LeaveRequestChangeProcessed($request, 'approved', $reviewer->name, $reviewerDesig));

            // Notify chain managers who were in the loop
            if ($target > 0) {
                $bossChain = $this->hierarchyService->getBossChain($request->user);
                $targetBosses = array_slice($bossChain, 0, $target);

                $inLoopCount = 1;
                if ($oldStatus === LeaveRequest::STATUS_APPROVED || $progress >= $target) {
                    $inLoopCount = count($targetBosses);
                } elseif ($oldStatus === 'rejected_by_manager') {
                    $inLoopCount = min(count($targetBosses), max(1, $request->rejectionLevel()));
                } else {
                    $inLoopCount = min(count($targetBosses), $progress + 1);
                }

                $managersToNotify = array_slice($targetBosses, 0, $inLoopCount);
                foreach ($managersToNotify as $boss) {
                    $boss->notify(new LeaveRequestCancelled($request, $reviewer->name, $cancelReason));
                }
            }

            return $request;
        });
    }

    /**
     * Super Admin rejects a past/active leave cancellation request.
     */
    public function rejectCancellationRequest(LeaveRequest $request, User $reviewer): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reviewer) {
            if ($request->change_request_status !== 'pending' || empty($request->change_request['request_cancellation'])) {
                throw ValidationException::withMessages([
                    'status' => 'No pending cancellation request exists for this leave.',
                ]);
            }

            $cancelReason = $request->change_request['cancel_reason'] ?? '';

            $request->update([
                'change_request_status' => 'rejected',
            ]);

            $request->load(['user', 'leaveType']);

            $reviewerDesig = $reviewer->designation?->name 
                ?? ($reviewer->special_order === 1 ? 'CEO' : ($reviewer->special_order === 2 ? 'CTO' : ($reviewer->special_order === 3 ? 'HR Manager' : null)));

            // Notify employee
            $request->user->notify(new LeaveRequestChangeProcessed($request, 'rejected', $reviewer->name, $reviewerDesig));

            // Notify all chain managers that cancellation request was rejected and leave remains Fully Approved
            $target = (int) $request->approval_chain_target;
            $bossChain = $this->hierarchyService->getBossChain($request->user);
            $targetBosses = $target > 0 ? array_slice($bossChain, 0, $target) : $bossChain;

            foreach ($targetBosses as $boss) {
                $boss->notify(new \App\Notifications\LeaveCancellationRejectedNotification($request, $reviewer->name, $cancelReason, $reviewerDesig));
            }

            return $request;
        });
    }
}
