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
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'status' => ['nullable', 'string'],
        ]);

        $subordinateIds = Auth::user()->subordinate_ids;

        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user'])
            ->whereIn('user_id', $subordinateIds);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            $query->where('status', LeaveRequest::STATUS_PENDING);
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
        
        // Get only departments headed by this admin and subordinates for filters
        $departments = Auth::user()->headedDepartments()->orderBy('name')->get();
        $allUsers = User::whereIn('id', $subordinateIds)->orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('admin.team.leaves', compact('requests', 'departments', 'allUsers', 'leaveTypes'));
    }

    public function history(Request $request): View
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'string'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
        ]);

        $subordinateIds = Auth::user()->subordinate_ids;
        $year = (int) now()->year;

        $query = LeaveRequest::with(['user.department', 'leaveType', 'reviewer', 'comments.user'])
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED, 'approved_by_manager', 'rejected_by_manager'])
            ->whereIn('user_id', $subordinateIds);

        $user = null;
        $balances = collect();

        if (! empty($validated['user_id'])) {
            $user = User::whereIn('id', $subordinateIds)->find($validated['user_id']);
            if ($user) {
                $query->where('user_id', $user->id);
                foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
                    $this->balanceService->ensureBalanceRow($user, $type, $year);
                }
                $balances = $user->leaveBalances()->with('leaveType')->where('year', $year)->get();
            }
        }

        if (! empty($validated['department_id'])) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $validated['department_id']));
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] === LeaveRequest::STATUS_REJECTED) {
                $query->whereIn('status', [LeaveRequest::STATUS_REJECTED, 'rejected_by_manager']);
            } else {
                $query->where('status', $validated['status']);
            }
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        $history = $query->latest()->paginate(20)->withQueryString();
        $allUsers = User::whereIn('id', $subordinateIds)->orderBy('name')->get();
        $departments = Auth::user()->headedDepartments()->orderBy('name')->get();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('admin.team.history', compact('user', 'balances', 'history', 'year', 'allUsers', 'departments', 'leaveTypes'));
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

    public function applyView(Request $request): View
    {
        $user = Auth::user();
        $year = (int) now()->year;

        foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
            $this->balanceService->ensureBalanceRow($user, $type, $year);
        }

        $balances = $user->leaveBalances()
            ->with('leaveType')
            ->where('year', $year)
            ->get()
            ->map(function ($balance) {
                $balance->remaining = $this->balanceService->leftDays($balance);
                return $balance;
            });

        $recentRequests = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with(['leaveType', 'comments.user'])
            ->latest()
            ->limit(25)
            ->get();

        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('admin.team.apply', compact('user', 'balances', 'leaveTypes', 'year'));
    }

    public function recordView(Request $request): View
    {
        $user = Auth::user();
        $year = (int) now()->year;

        $validated = $request->validate([
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'status' => ['nullable', 'string'],
        ]);

        $query = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with(['leaveType', 'comments.user']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['leave_type_id'])) {
            $query->where('leave_type_id', $validated['leave_type_id']);
        }

        $recentRequests = $query->latest()->paginate(15)->withQueryString();
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('admin.team.record', compact('user', 'recentRequests', 'leaveTypes', 'year'));
    }

    public function directoryView(Request $request): View
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;
        $year = (int) now()->year;

        $query = User::with(['department', 'role', 'leaveBalances.leaveType'])
            ->whereIn('id', $subordinateIds);

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('employee_code', 'like', $search);
            });
        }

        $team = $query->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.team.directory', compact('team', 'year'));
    }
}
