<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Department;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamLeaveController extends Controller
{
    public function __construct(protected LeaveBalanceService $balanceService) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $subordinateIds = Auth::user()->subordinate_ids;

        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user'])
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->whereIn('user_id', $subordinateIds);

        if (! empty($validated['department_id'])) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $validated['department_id']));
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        $requests = $query->latest()->paginate(25)->withQueryString();
        
        // Get only departments headed by this admin and subordinates for filters
        $departments = Auth::user()->headedDepartments()->orderBy('name')->get();
        $allUsers = User::whereIn('id', $subordinateIds)->orderBy('name')->get();

        return view('admin.team.leaves', compact('requests', 'departments', 'allUsers'));
    }

    public function history(Request $request): View
    {
        $subordinateIds = Auth::user()->subordinate_ids;
        $year = (int) now()->year;

        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user'])
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED, 'approved_by_manager', 'rejected_by_manager'])
            ->whereIn('user_id', $subordinateIds);

        $user = null;
        $balances = collect();

        if ($request->filled('user_id')) {
            $user = User::whereIn('id', $subordinateIds)->find($request->user_id);
            if ($user) {
                $query->where('user_id', $user->id);
                foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
                    $this->balanceService->ensureBalanceRow($user, $type, $year);
                }
                $balances = $user->leaveBalances()->with('leaveType')->where('year', $year)->get();
            }
        }

        if ($request->filled('department_id')) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('status')) {
            if ($request->status === LeaveRequest::STATUS_REJECTED) {
                $query->whereIn('status', [LeaveRequest::STATUS_REJECTED, 'rejected_by_manager']);
            } else {
                $query->where('status', $request->status);
            }
        }

        $history = $query->latest()->paginate(20)->withQueryString();
        $allUsers = User::whereIn('id', $subordinateIds)->orderBy('name')->get();
        $departments = Auth::user()->headedDepartments()->orderBy('name')->get();

        return view('admin.team.history', compact('user', 'balances', 'history', 'year', 'allUsers', 'departments'));
    }

    public function comment(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeAccess($leaveRequest);
        
        $request->validate(['comment' => 'required|string']);
        
        app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment);

        return back()->with('success', 'Comment added successfully.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeAccess($leaveRequest);
        $this->assertNotOverriddenBySuperAdmin($leaveRequest);

        if ($leaveRequest->status === 'approved_by_manager' || $leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
            return back()->with('error', 'Request is already approved.');
        }

        if ($request->filled('comment')) {
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment);
        }

        DB::transaction(function () use ($leaveRequest) {
            $year = (int) $leaveRequest->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $year);

            if (in_array($leaveRequest->status, [LeaveRequest::STATUS_REJECTED, 'rejected_by_manager'])) {
                $balance->increment('pending', $leaveRequest->days_count);
            }

            $leaveRequest->update([
                'status' => 'approved_by_manager',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });
        
        $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed($leaveRequest));

        // Notify all Super Admins of manager recommendation
        $superAdmins = User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))->get();
        foreach ($superAdmins as $admin) {
            $admin->notify(new \App\Notifications\LeaveRequestManagerApproved($leaveRequest, auth()->user()->name));
        }

        return back()->with('success', 'Leave request approved by you. Sent to CEO/Super Admin for final approval.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeAccess($leaveRequest);
        $this->assertNotOverriddenBySuperAdmin($leaveRequest);

        if ($leaveRequest->status === 'rejected_by_manager' || $leaveRequest->status === LeaveRequest::STATUS_REJECTED) {
            return back()->with('error', 'Request is already rejected.');
        }

        if ($request->filled('comment')) {
            app(\App\Services\LeaveRequestService::class)->addComment($leaveRequest, auth()->user(), $request->comment);
        }

        DB::transaction(function () use ($leaveRequest) {
            $year = (int) $leaveRequest->start_date->year;
            $balance = $this->balanceService->ensureBalanceRow($leaveRequest->user, $leaveRequest->leaveType, $year);

            if (in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING, 'approved_by_manager'])) {
                $balance->decrement('pending', $leaveRequest->days_count);
            }

            $leaveRequest->update([
                'status' => 'rejected_by_manager',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });

        $leaveRequest->user->notify(new \App\Notifications\LeaveRequestReviewed($leaveRequest));

        // Notify all Super Admins of manager rejection
        $superAdmins = User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))->get();
        foreach ($superAdmins as $admin) {
            $admin->notify(new \App\Notifications\LeaveRequestManagerRejected($leaveRequest, auth()->user()->name));
        }

        return back()->with('success', 'Leave request rejected.');
    }

    protected function authorizeAccess(LeaveRequest $leaveRequest): void
    {
        $subordinateIds = Auth::user()->subordinate_ids;
        if (! in_array($leaveRequest->user_id, $subordinateIds)) {
            abort(403, 'Unauthorized access to this leave request.');
        }
    }

    protected function assertNotOverriddenBySuperAdmin(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->reviewer && $leaveRequest->reviewer->isSuperAdmin()) {
            abort(403, 'This request has been resolved by Super Admin and cannot be modified.');
        }
    }
}
