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
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled,approved_by_manager,rejected_by_manager'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
        ]);

        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user']);

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'pending') {
                $query->whereIn('status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
            } else {
                $query->where('status', $validated['status']);
            }
        } else {
            $query->whereIn('status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
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

        $requests = $query->latest()->paginate(25)->withQueryString();
        $departments = Department::orderBy('name')->get();
        $allUsers = User::orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('super-admin.leave-requests.index', compact('requests', 'departments', 'allUsers', 'leaveTypes'));
    }

    public function history(Request $request): View
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
        ]);

        $year = (int) now()->year;
        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user'])
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED]);

        $user = null;
        $balances = collect();

        if (! empty($validated['user_id'])) {
            $user = User::find($validated['user_id']);
            if ($user) {
                $query->where('user_id', $user->id);
                foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
                    $this->balanceService->ensureBalanceRow($user, $type, $year);
                }
                $balances = $user->leaveBalances()->with('leaveType')->where('year', $year)->get();
            }
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        $history = $query->latest()->paginate(20)->withQueryString();
        $allUsers = User::orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('super-admin.leave-requests.history', compact('user', 'balances', 'history', 'year', 'allUsers', 'leaveTypes'));
    }

    public function comment(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate(['comment' => 'required|string']);
        
        app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment);

        return back()->with('success', 'Comment added successfully.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
            return back()->with('error', 'Request is already approved.');
        }

        if ($request->filled('comment')) {
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment);
        }

        $previousStatus = $leaveRequest->status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($leaveRequest) {
            $year = (int) $leaveRequest->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $year);

            if (in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $balance->decrement('pending', $leaveRequest->days_count);
                $balance->increment('used', $leaveRequest->days_count);
            } elseif (in_array($leaveRequest->status, ['rejected_by_manager', LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED])) {
                $balance->increment('used', $leaveRequest->days_count);
            }

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });
        
        $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed($leaveRequest));

        // Notify direct Line Manager of CEO approval
        $managerId = $leaveRequest->user->manager_id;
        if ($managerId) {
            $manager = User::find($managerId);
            if ($manager) {
                $manager->notify(new \App\Notifications\LeaveRequestAdminApprovedToManager($leaveRequest, auth()->user()->name, $previousStatus));
            }
        }

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status === LeaveRequest::STATUS_REJECTED) {
            return back()->with('error', 'Request is already rejected.');
        }

        if ($request->filled('comment')) {
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment);
        }

        $previousStatus = $leaveRequest->status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($leaveRequest) {
            $year = (int) $leaveRequest->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $year);

            if (in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $balance->decrement('pending', $leaveRequest->days_count);
            } elseif ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                $balance->decrement('used', $leaveRequest->days_count);
            }

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });

        $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed($leaveRequest));

        // Notify direct Line Manager of CEO rejection
        $managerId = $leaveRequest->user->manager_id;
        if ($managerId) {
            $manager = User::find($managerId);
            if ($manager) {
                $manager->notify(new \App\Notifications\LeaveRequestOverridden($leaveRequest, auth()->user()->name, $previousStatus));
            }
        }

        return back()->with('success', 'Leave request rejected.');
    }
}
