<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function __construct(protected LeaveBalanceService $balanceService) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:all,pending,approved_by_manager,rejected_by_manager,changes_requested,pending_cancellation'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user', 'approvalChain.approver.designation']);

        $status = $request->query('status');
        if ($status === null && !$request->has('status')) {
            $status = 'all';
        }

        if ($status === 'all') {
            $query->where(function($q) {
                $q->whereIn('status', [LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'rejected_by_manager'])
                  ->orWhereIn('change_request_status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
            });
        } elseif ($status === 'pending') {
            // All pending manager approvals (Level 1, Level 2, etc.), excluding pending cancellation
            $query->where('status', LeaveRequest::STATUS_PENDING)
                  ->where(function($q) {
                      $q->whereNull('change_request->request_cancellation')
                        ->orWhere('change_request->request_cancellation', false);
                  });
        } elseif ($status === 'approved_by_manager') {
            // Pending CEO approval (either initial request or change request completed manager chain)
            $query->where(function($q) {
                $q->where('status', 'approved_by_manager')
                  ->orWhere('change_request_status', 'approved_by_manager');
            })->where(function($q) {
                $q->whereNull('change_request->request_cancellation')
                  ->orWhere('change_request->request_cancellation', false);
            });
        } elseif ($status === 'rejected_by_manager') {
            $query->where('status', 'rejected_by_manager');
        } elseif ($status === 'changes_requested') {
            // Outstanding change requests (shows with changes badge)
            $query->whereIn('change_request_status', ['pending', 'approved_by_manager'])
                  ->whereNotIn('status', [LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_REJECTED_BY_MANAGER, LeaveRequest::STATUS_CANCELLED])
                  ->where(function($q) {
                      $q->whereNull('change_request->request_cancellation')
                        ->orWhere('change_request->request_cancellation', false);
                  });
        } elseif ($status === 'pending_cancellation') {
            $query->where('change_request_status', 'pending')
                  ->where('change_request->request_cancellation', true);
        }

        if (! empty($validated['department_id'])) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $validated['department_id']));
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $query->where('start_date', '<=', $validated['end_date'])
                  ->where('end_date', '>=', $validated['start_date']);
        } elseif (! empty($validated['start_date'])) {
            $query->where('start_date', '>=', $validated['start_date']);
        } elseif (! empty($validated['end_date'])) {
            $query->where('end_date', '<=', $validated['end_date']);
        }

        $requests = $query->latest()->paginate(25)->withQueryString();
        $departments = Department::orderBy('name')->get();
        $allUsers = User::whereHas('role', fn ($q) => $q->where('name', '!=', 'super_admin'))->orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('super-admin.leave-requests.index', compact('requests', 'departments', 'allUsers', 'leaveTypes'));
    }

    public function history(Request $request): View
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'status' => ['nullable', 'in:approved,rejected,cancelled,all'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $year = (int) now()->year;
        
        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'approvalChain.approver.designation', 'comments.user'])
            // Only show finalized CEO decided statuses: approved, rejected, cancelled
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED])
            // BUT exclude if there is a pending/active change request (as it's still being decided in requests)
            ->where(function($q) {
                $q->whereNull('change_request_status')
                  ->orWhereNotIn('change_request_status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
            });

        $status = $request->query('status');
        if ($status === null && !$request->has('status')) {
            $status = 'all';
        }

        if ($status !== 'all' && !empty($status)) {
            $query->where('status', $status);
        }

        $user = null;
        $balances = collect();

        if (! empty($validated['user_id'])) {
            $user = User::find($validated['user_id']);
            if ($user) {
                $query->where('user_id', $user->id);
                foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
                    $this->balanceService->ensureBalanceRow($user, $type, $year);
                }
                $balances = $user->leaveBalances()
                    ->whereHas('leaveType', fn ($q) => $q->where('is_active', true))
                    ->with('leaveType')
                    ->where('year', $year)
                    ->get();
            }
        }

        if (! empty($validated['department_id'])) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $validated['department_id']));
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $query->where('start_date', '<=', $validated['end_date'])
                  ->where('end_date', '>=', $validated['start_date']);
        } elseif (! empty($validated['start_date'])) {
            $query->where('start_date', '>=', $validated['start_date']);
        } elseif (! empty($validated['end_date'])) {
            $query->where('end_date', '<=', $validated['end_date']);
        }

        $history = $query->latest()->paginate(20)->withQueryString();
        $allUsers = User::whereHas('role', fn ($q) => $q->where('name', '!=', 'super_admin'))->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::orderBy('name')->get();

        return view('super-admin.leave-requests.history', compact('user', 'balances', 'history', 'year', 'allUsers', 'departments', 'leaveTypes'));
    }

    public function comment(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate(['comment' => 'required|string']);
        
        $isHidden = $request->boolean('is_hidden_from_employee');
        app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);

        return back()->with('success', 'Comment added successfully.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->is_admin_marked 
            && $leaveRequest->change_request_status === 'pending' 
            && !empty($leaveRequest->change_request['original'])) {
            return $this->approveChange($request, $leaveRequest);
        }

        if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
            return back()->with('error', 'Request is already approved.');
        }

        if ($request->filled('comment')) {
            $isHidden = $request->boolean('is_hidden_from_employee');
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);
        }

        $previousStatus = $leaveRequest->status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($leaveRequest) {
            $year = (int) $leaveRequest->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $year);

            if (in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $balance->update([
                    'pending' => max(0.0, $balance->pending - $leaveRequest->days_count),
                    'used' => $balance->used + $leaveRequest->days_count
                ]);
            } elseif (in_array($leaveRequest->status, ['rejected_by_manager', LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED])) {
                $balance->increment('used', $leaveRequest->days_count);
            }

            $updateData = [
                'status' => LeaveRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ];
            if ($leaveRequest->approval_chain_target > 0) {
                $updateData['approval_chain_progress'] = $leaveRequest->approval_chain_target;
            }
            if ($leaveRequest->change_request_status || !empty($leaveRequest->change_request)) {
                $updateData['change_request_status'] = 'approved';
            }
            $leaveRequest->update($updateData);
        });
        
        $admin = auth()->user();
        $adminDesig = $admin->designation?->name 
            ?? ($admin->special_order === 1 ? 'CEO' : ($admin->special_order === 2 ? 'CTO' : ($admin->special_order === 3 ? 'HR Manager' : null)));

        $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed($leaveRequest, $admin->name, $adminDesig));

        // Notify all Line Managers in the employee's hierarchy chain
        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($leaveRequest->user);
        $target = (int) $leaveRequest->approval_chain_target;
        $targetBosses = $target > 0 ? array_slice($bossChain, 0, $target) : $bossChain;

        foreach ($targetBosses as $boss) {
            $bossAction = $leaveRequest->approvalChain->firstWhere('approver_id', $boss->id)?->action;
            $bossStatus = match($bossAction) {
                'approved' => 'approved_by_manager',
                'rejected' => 'rejected_by_manager',
                default    => 'pending',
            };
            $boss->notify(new \App\Notifications\LeaveRequestAdminApprovedToManager($leaveRequest, $admin->name, $bossStatus, $adminDesig));
        }

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->is_admin_marked 
            && $leaveRequest->change_request_status === 'pending' 
            && !empty($leaveRequest->change_request['original'])) {
            return $this->rejectChange($request, $leaveRequest);
        }

        if ($leaveRequest->status === LeaveRequest::STATUS_REJECTED) {
            return back()->with('error', 'Request is already rejected.');
        }

        if ($request->filled('comment')) {
            $isHidden = $request->boolean('is_hidden_from_employee');
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);
        }

        $previousStatus = $leaveRequest->status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($leaveRequest) {
            $year = (int) $leaveRequest->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $year);

            if (in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $balance->update(['pending' => max(0.0, $balance->pending - $leaveRequest->days_count)]);
            } elseif ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                $balance->update(['used' => max(0.0, $balance->used - $leaveRequest->days_count)]);
            }

            $updateData = [
                'status' => LeaveRequest::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ];
            if ($leaveRequest->change_request_status) {
                $updateData['change_request_status'] = 'rejected';
            }
            $leaveRequest->update($updateData);
        });

        $admin = auth()->user();
        $adminDesig = $admin->designation?->name 
            ?? ($admin->special_order === 1 ? 'CEO' : ($admin->special_order === 2 ? 'CTO' : ($admin->special_order === 3 ? 'HR Manager' : null)));

        $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed($leaveRequest, $admin->name, $adminDesig));

        // Notify all Line Managers in the employee's hierarchy chain
        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($leaveRequest->user);
        $target = (int) $leaveRequest->approval_chain_target;
        $targetBosses = $target > 0 ? array_slice($bossChain, 0, $target) : $bossChain;

        foreach ($targetBosses as $boss) {
            $bossAction = $leaveRequest->approvalChain->firstWhere('approver_id', $boss->id)?->action;
            $bossStatus = match($bossAction) {
                'approved' => 'approved_by_manager',
                'rejected' => 'rejected_by_manager',
                default    => $previousStatus,
            };
            $boss->notify(new \App\Notifications\LeaveRequestOverridden($leaveRequest, $admin->name, $bossStatus, $adminDesig));
        }

        return back()->with('success', 'Leave request rejected.');
    }

    public function approveChange(Request $request, LeaveRequest $leaveRequest)
    {
        // For admin-marked leaves: the new values are already applied in-place.
        // We need to reconcile balance: release old used, add new used.
        if ($leaveRequest->is_admin_marked
            && $leaveRequest->change_request_status === 'pending'
            && !empty($leaveRequest->change_request['original'])) {

            if ($request->filled('comment')) {
                $isHidden = $request->boolean('is_hidden_from_employee');
                app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);
            }

            $original = $leaveRequest->change_request['original'];

            \Illuminate\Support\Facades\DB::transaction(function () use ($leaveRequest, $original) {
                $oldTypeId  = (int) $original['leave_type_id'];
                $oldDays    = (float) $original['days_count'];
                $oldYear    = (int) \Carbon\Carbon::parse($original['start_date'])->year;
                $newYear    = (int) $leaveRequest->start_date->year;

                $oldType = \App\Models\LeaveType::find($oldTypeId);

                // Release old used balance
                if ($oldType) {
                    $oldBalance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $oldType, $oldYear);
                    $oldBalance->update(['used' => max(0.0, $oldBalance->used - $oldDays)]);
                }

                // Add new used balance (current type/days on the leave request)
                $newBalance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $newYear);
                $newBalance->increment('used', (float) $leaveRequest->days_count);

                $leaveRequest->update([
                    'change_request_status' => 'approved',
                    'reviewed_by'           => auth()->id(),
                    'reviewed_at'           => now(),
                ]);
            });

            $admin = auth()->user();
            $adminDesig = $admin->designation?->name 
                ?? ($admin->special_order === 1 ? 'CEO' : ($admin->special_order === 2 ? 'CTO' : ($admin->special_order === 3 ? 'HR Manager' : null)));

            $leaveRequest->user->notify(new \App\Notifications\LeaveRequestChangeProcessed($leaveRequest, 'approved', $admin->name, $adminDesig));
            return back()->with('success', 'Change request approved.');
        }

        // For normal (non-admin-marked) change requests: delegate to standard approve
        return $this->approve($request, $leaveRequest);
    }

    public function rejectChange(Request $request, LeaveRequest $leaveRequest)
    {
        // For admin-marked leaves: revert to original values from snapshot, keep balance as-is.
        if ($leaveRequest->is_admin_marked
            && $leaveRequest->change_request_status === 'pending'
            && !empty($leaveRequest->change_request['original'])) {

            if ($request->filled('comment')) {
                $isHidden = $request->boolean('is_hidden_from_employee');
                app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);
            }

            $original = $leaveRequest->change_request['original'];

            \Illuminate\Support\Facades\DB::transaction(function () use ($leaveRequest, $original) {
                // Restore original values and guarantee status remains fully approved (no balance change — balance stayed as used for original values)
                $leaveRequest->update([
                    'start_date'            => $original['start_date'],
                    'end_date'              => $original['end_date'],
                    'days_count'            => (float) $original['days_count'],
                    'leave_type_id'         => (int) $original['leave_type_id'],
                    'reason'                => $original['reason'],
                    'status'                => LeaveRequest::STATUS_APPROVED,
                    'change_request'        => null,
                    'change_request_status' => 'rejected',
                    'reviewed_by'           => auth()->id(),
                    'reviewed_at'           => now(),
                ]);
            });

            $admin = auth()->user();
            $adminDesig = $admin->designation?->name 
                ?? ($admin->special_order === 1 ? 'CEO' : ($admin->special_order === 2 ? 'CTO' : ($admin->special_order === 3 ? 'HR Manager' : null)));

            $leaveRequest->user->notify(new \App\Notifications\LeaveRequestChangeProcessed($leaveRequest, 'rejected', $admin->name, $adminDesig));
            return back()->with('success', 'Change request rejected and original leave restored.');
        }

        // For normal change requests: delegate to standard reject
        return $this->reject($request, $leaveRequest);
    }


    public function approveCancellation(Request $request, LeaveRequest $leaveRequest)
    {
        if ($request->filled('comment')) {
            $isHidden = $request->boolean('is_hidden_from_employee');
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);
        }

        app(\App\Services\LeaveRequestService::class)->approveCancellationRequest($leaveRequest, auth()->user());

        return back()->with('success', 'Leave cancellation request approved.');
    }

    public function rejectCancellation(Request $request, LeaveRequest $leaveRequest)
    {
        if ($request->filled('comment')) {
            $isHidden = $request->boolean('is_hidden_from_employee');
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment, $isHidden);
        }

        app(\App\Services\LeaveRequestService::class)->rejectCancellationRequest($leaveRequest, auth()->user());

        return back()->with('success', 'Leave cancellation request rejected.');
    }
}
