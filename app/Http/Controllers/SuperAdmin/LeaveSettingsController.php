<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalanceOverride;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveSettingsController extends Controller
{
    public function __construct(protected LeaveBalanceService $balanceService) {}

    public function index(): View
    {
        $leaveTypes            = LeaveType::with('settings')->where('is_active', true)->get();
        $year                  = (int) now()->year;
        $currentApprovalLevels = (int) \App\Models\SystemSetting::approvalLevels();

        return view('super-admin.leave-settings.index', compact('leaveTypes', 'year', 'currentApprovalLevels'));
    }

    public function applyToAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int) $validated['year'];
        $this->balanceService->applyDefaultsToAll($year);

        return back()->with('success', "Default leave balances applied to all employees for {$year}.");
    }

    public function overrides(Request $request): View
    {
        $users = User::whereHas('role', fn ($q) => $q->where('name', '!=', \App\Models\Role::SUPER_ADMIN))
            ->orderBy('name')
            ->get();
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();
        $year = (int) now()->year;

        // Build overrides query
        $query = LeaveBalanceOverride::with(['user', 'leaveType'])->where('year', $year);

        // Apply Status Filter (Soft Deletes)
        $status = $request->input('filter_status', 'active');
        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->withTrashed();
        }

        // Apply Employee Filter
        if ($request->filled('filter_user_id')) {
            $query->where('user_id', $request->filter_user_id);
        }

        // Apply Leave Type Filter
        if ($request->filled('filter_leave_type_id')) {
            $query->where('leave_type_id', $request->filter_leave_type_id);
        }

        $overrides = $query->latest()->paginate(20, ['*'], 'overrides_page')->withQueryString();

        return view('super-admin.leave-settings.overrides', compact('users', 'leaveTypes', 'year', 'overrides'));
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entitled' => ['required', 'numeric', 'min:0'],
        ]);

        $override = LeaveBalanceOverride::withTrashed()->where([
            'user_id' => $validated['user_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'year' => $validated['year'],
        ])->first();

        if ($override) {
            $override->restore();
            $override->update(['entitled' => $validated['entitled']]);
        } else {
            LeaveBalanceOverride::create([
                'user_id' => $validated['user_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'year' => $validated['year'],
                'entitled' => $validated['entitled'],
            ]);
        }

        $user = User::find($validated['user_id']);
        $type = LeaveType::find($validated['leave_type_id']);
        $balance = $this->balanceService->ensureBalanceRow($user, $type, (int) $validated['year']);
        $balance->update(['entitled' => $validated['entitled']]);

        return back()->with('success', 'Per-employee leave entitlement updated.');
    }

    public function destroyOverride(LeaveBalanceOverride $override): RedirectResponse
    {
        $user = $override->user;
        $type = $override->leaveType;
        $year = $override->year;

        $override->delete();

        // Re-evaluate and restore default balance for this employee
        $balance = $this->balanceService->ensureBalanceRow($user, $type, $year);
        $entitled = $this->balanceService->resolveEntitlement($user, $type, $year);
        $pending = $this->balanceService->sumPendingDays($user->id, $type->id, $year);

        $balance->update([
            'entitled' => $entitled,
            'pending' => $pending,
        ]);

        return back()->with('success', 'Manual override deleted. Default leave balance has been restored.');
    }
}
