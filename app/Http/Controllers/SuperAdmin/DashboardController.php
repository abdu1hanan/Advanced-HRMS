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
            'pending_leaves' => LeaveRequest::whereIn('status', [LeaveRequest::STATUS_PENDING, 'approved_by_manager', 'rejected_by_manager'])->count(),
        ];

        return view('super-admin.dashboard', compact('stats'));
    }
}
