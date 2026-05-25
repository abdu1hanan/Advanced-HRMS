<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $admin = Auth::user();
        $subordinateIds = $admin->subordinate_ids;
        $year = (int) now()->year;

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

        // Ensure Line Manager's own leave balances exist for the dynamic stack
        $balanceService = app(LeaveBalanceService::class);
        foreach (LeaveType::where('is_active', true)->get() as $type) {
            $balanceService->ensureBalanceRow($admin, $type, $year);
        }

        $balances = $admin->leaveBalances()
            ->with('leaveType')
            ->where('year', $year)
            ->get()
            ->map(function ($balance) use ($balanceService) {
                $balance->remaining = $balanceService->availableDays($balance);
                return $balance;
            });

        return view('admin.dashboard', compact('admin', 'teamCount', 'pendingLeaves', 'outToday', 'balances', 'year'));
    }
}
