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
            ->whereHas('leaveType', fn ($q) => $q->where('is_active', true))
            ->with('leaveType')
            ->where('year', $year)
            ->get()
            ->map(function ($balance) {
                $balance->remaining = $this->balanceService->leftDays($balance);

                return $balance;
            });

        $recentRequests = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with(['leaveType', 'comments' => function ($q) {
                $q->where('is_hidden_from_employee', false);
            }, 'comments.user'])
            ->latest()
            ->limit(25)
            ->get();

        $slackMapping = \App\Models\SlackUserMapping::where('user_id', $user->id)->first();
        $todayBreakBalance = null;
        if ($slackMapping) {
            $todayStr = now('Asia/Karachi')->format('Y-m-d');
            $todayBreakBalance = \App\Models\SlackMessage::computeDayBreakBalance($slackMapping->slack_user_id, $todayStr);
        }

        return view('employee.dashboard', compact('user', 'balances', 'leaveTypes', 'year', 'isRemoteToday', 'todayBreakBalance', 'slackMapping'));
    }
}
