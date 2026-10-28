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
        $user            = Auth::user();
        $subordinateIds  = $user->subordinate_ids;
        $hasSubordinates = ! empty($subordinateIds);
        $year            = (int) now()->year;

        $isGuest         = $user->isGuest();
        $balances        = collect();

        if (! $isGuest) {
            // Ensure this user's own leave balances exist
            $balanceService = app(LeaveBalanceService::class);
            foreach (LeaveType::where('is_active', true)->get() as $type) {
                $balanceService->ensureBalanceRow($user, $type, $year);
            }

            $balances = $user->leaveBalances()
                ->whereHas('leaveType', fn ($q) => $q->where('is_active', true))
                ->with('leaveType')
                ->where('year', $year)
                ->get()
                ->map(function ($balance) use ($balanceService) {
                    $balance->remaining = $balanceService->leftDays($balance);
                    return $balance;
                });
        }

        $isRemoteToday = ! $isGuest && \App\Models\RemoteWorkLog::where('user_id', $user->id)
            ->where('date', date('Y-m-d'))
            ->exists();

        $slackMapping = \App\Models\SlackUserMapping::where('user_id', $user->id)->first();
        $todayBreakBalance = null;
        if ($slackMapping) {
            $todayStr = now('Asia/Karachi')->format('Y-m-d');
            $todayBreakBalance = \App\Models\SlackMessage::computeDayBreakBalance($slackMapping->slack_user_id, $todayStr);
        }

        // --- Leaf node (no subordinates): employee-style data only ---
        if (! $hasSubordinates) {
            return view('admin.dashboard', compact(
                'user',
                'hasSubordinates',
                'balances',
                'year',
                'isRemoteToday',
                'todayBreakBalance',
                'slackMapping'
            ));
        }

        // --- Has subordinates: full manager-style data ---
        $teamCount    = count($subordinateIds);
        $today        = now()->startOfDay();
        $todayStr     = $today->format('Y-m-d');

        // Pending Leaves (including pending change requests)
        $pendingLeaves = LeaveRequest::query()
            ->whereIn('user_id', $subordinateIds)
            ->where(function ($q) {
                $q->where('status', LeaveRequest::STATUS_PENDING)
                  ->orWhere('change_request_status', 'pending');
            })
            ->count();

        // Who's Out & Today's Team Status
        $leavesToday = LeaveRequest::with(['user.department', 'leaveType'])
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereIn('user_id', $subordinateIds)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get()
            ->map(fn ($req) => [
                'user'  => $req->user,
                'type'  => 'leave',
                'label' => $req->leaveType->name,
            ]);

        $remotesToday = \App\Models\RemoteWorkLog::with('user.department')
            ->whereIn('user_id', $subordinateIds)
            ->where('date', $todayStr)
            ->get()
            ->map(fn ($log) => [
                'user'  => $log->user,
                'type'  => 'remote',
                'label' => 'Remote',
            ]);

        $absentsToday = \App\Models\AttendanceRecord::with('user.department')
            ->whereIn('user_id', $subordinateIds)
            ->where('date', $todayStr)
            ->get()
            ->map(fn ($rec) => [
                'user'  => $rec->user,
                'type'  => 'absent',
                'label' => 'Absent',
            ]);

        $teamStatusToday = $leavesToday->concat($remotesToday)->concat($absentsToday);

        return view('admin.dashboard', compact(
            'user',
            'hasSubordinates',
            'teamCount',
            'pendingLeaves',
            'teamStatusToday',
            'balances',
            'year',
            'isRemoteToday',
            'todayBreakBalance',
            'slackMapping'
        ));
    }

    public function requestActivation(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if ($user->isApproved()) {
            return redirect()->route('admin.dashboard')->with('info', 'Your account is already active.');
        }

        $user->update([
            'approval_status'         => \App\Models\User::STATUS_PENDING,
            'activation_requested_at' => now(),
        ]);

        $hasPendingRequest = \App\Models\AccountApprovalRequest::where('user_id', $user->id)
            ->where('status', \App\Models\AccountApprovalRequest::STATUS_PENDING)
            ->exists();

        if (! $hasPendingRequest) {
            \App\Models\AccountApprovalRequest::create([
                'user_id' => $user->id,
                'status'  => \App\Models\AccountApprovalRequest::STATUS_PENDING,
            ]);
        }

        $superAdmins = \App\Models\User::whereHas('role', fn ($q) => $q->where('name', \App\Models\Role::SUPER_ADMIN))->get();
        if ($superAdmins->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($superAdmins, new \App\Notifications\AccountActivationRequestedNotification($user));
        }

        return redirect()->route('admin.dashboard')->with('success', 'Account activation request has been submitted to HR. You will be notified once reviewed.');
    }
}
