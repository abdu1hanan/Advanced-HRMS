<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'users' => User::count(),
            'departments' => Department::count(),
            'pending_leaves' => LeaveRequest::where(function($q) {
                $q->whereIn('status', [LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'rejected_by_manager'])
                  ->orWhereIn('change_request_status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
            })->count(),
            'pending_registrations' => \App\Models\AccountApprovalRequest::where('status', \App\Models\AccountApprovalRequest::STATUS_PENDING)->count(),
        ];

        $pendingApprovals = \App\Models\AccountApprovalRequest::where('status', \App\Models\AccountApprovalRequest::STATUS_PENDING)
            ->with(['user.department', 'user.role', 'user.designation'])
            ->latest()
            ->get();

        $recentRequests = LeaveRequest::with(['user.department', 'leaveType'])
            ->where(function($q) {
                $q->whereIn('status', [LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'rejected_by_manager'])
                  ->orWhereIn('change_request_status', ['pending', 'approved_by_manager', 'rejected_by_manager']);
            })
            ->latest()
            ->limit(15)
            ->get();

        return view('super-admin.dashboard', compact('stats', 'recentRequests', 'pendingApprovals'));
    }
}
