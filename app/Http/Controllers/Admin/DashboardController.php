<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;

        // Subordinate Count
        $teamCount = count($subordinateIds);

        // Pending Leaves
        $pendingLeaves = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->whereIn('user_id', $subordinateIds)
            ->count();

        // Who's Out Today
        $today = now()->startOfDay();
        $outToday = LeaveRequest::with(['user.department', 'leaveType'])
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereIn('user_id', $subordinateIds)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();

        // Team Directory with search and pagination
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

        return view('admin.dashboard', compact('teamCount', 'pendingLeaves', 'outToday', 'team'));
    }
}
