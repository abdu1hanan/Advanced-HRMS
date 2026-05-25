<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected LeaveBalanceService $balanceService) {}

    public function __invoke(): View
    {
        $user = Auth::user();
        $year = (int) now()->year;

        foreach (LeaveType::where('is_active', true)->get() as $type) {
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

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('employee.dashboard', compact('user', 'balances', 'leaveTypes', 'year'));
    }
}
