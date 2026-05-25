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
        $leaveTypes = LeaveType::with('settings')->where('is_active', true)->get();
        $year = (int) now()->year;

        return view('super-admin.leave-settings.index', compact('leaveTypes', 'year'));
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

    public function overrides(): View
    {
        $users = User::with('role')->orderBy('name')->paginate(20);
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();
        $year = (int) now()->year;
        $overrides = LeaveBalanceOverride::with(['user', 'leaveType'])
            ->where('year', $year)
            ->latest()
            ->paginate(20, ['*'], 'overrides_page');

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

        LeaveBalanceOverride::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'year' => $validated['year'],
            ],
            ['entitled' => $validated['entitled']]
        );

        $user = User::find($validated['user_id']);
        $type = LeaveType::find($validated['leave_type_id']);
        $balance = $this->balanceService->ensureBalanceRow($user, $type, (int) $validated['year']);
        $balance->update(['entitled' => $validated['entitled']]);

        return back()->with('success', 'Per-employee leave entitlement updated.');
    }
}
