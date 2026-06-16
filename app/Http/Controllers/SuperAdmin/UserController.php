<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\HierarchyService;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected HierarchyService $hierarchyService,
        protected LeaveBalanceService $balanceService
    ) {}

    public function index(Request $request): View
    {
        $showDeleted = $request->query('show') === 'deleted';

        $query = User::with(['role', 'department', 'manager', 'headedDepartments']);

        if ($showDeleted) {
            $query->onlyTrashed();
        }

        $users = $query->latest()->paginate(20)->withQueryString();
        $roles = Role::where('name', '!=', Role::SUPER_ADMIN)->get();
        $departments = Department::orderBy('name')->get();

        return view('super-admin.users.index', compact('users', 'roles', 'departments', 'showDeleted'));
    }

    public function edit(User $user): View
    {
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin accounts cannot be modified.');
        }

        $roles = Role::where('name', '!=', Role::SUPER_ADMIN)->get();
        $departments = Department::orderBy('name')->get();
        $year = (int) now()->year;

        // Initialize and load leave balances for the user
        foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
            $this->balanceService->ensureBalanceRow($user, $type, $year);
        }

        $balances = $user->leaveBalances()
            ->with('leaveType')
            ->where('year', $year)
            ->get()
            ->map(function ($balance) {
                $balance->remaining = $this->balanceService->leftDays($balance);
                $balance->pending = $this->balanceService->sumPendingDays($balance->user_id, $balance->leave_type_id, $balance->year);
                return $balance;
            });

        return view('super-admin.users.edit', compact('user', 'roles', 'departments', 'balances', 'year'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['role_id' => 'Super Admin accounts cannot be modified from this page.']);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'employee_code' => ['nullable', 'string', 'max:50', 'unique:users,employee_code,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'join_date' => ['nullable', 'date'],
            'role_id' => ['required', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'confirm_head_replace' => ['nullable', 'boolean'],
            'expected_salary_amount' => ['nullable', 'numeric', 'min:0'],
            'expected_hour_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'expected_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:168'],
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if ($role->name === Role::SUPER_ADMIN) {
            return back()->withErrors(['role_id' => 'Super Admin role cannot be assigned from the UI.']);
        }

        if ($role->name === Role::ADMIN && empty($validated['department_id'])) {
            return back()->withErrors(['department_id' => 'Department is required for Admin (Line Manager).']);
        }

        $updateData = [
            'role_id' => $validated['role_id'],
            'department_id' => $validated['department_id'] ?? null,
            'expected_salary_amount' => $validated['expected_salary_amount'] ?? null,
            'expected_hour_per_day' => $validated['expected_hour_per_day'] ?? null,
            'expected_hours_per_week' => $validated['expected_hours_per_week'] ?? null,
        ];

        if ($request->has('name')) {
            $updateData['name'] = $validated['name'];
        }
        if ($request->has('email')) {
            $updateData['email'] = $validated['email'];
        }
        if ($request->has('phone')) {
            $updateData['phone'] = $validated['phone'];
        }
        if ($request->has('employee_code')) {
            $updateData['employee_code'] = $validated['employee_code'];
        }
        if ($request->has('join_date')) {
            $updateData['join_date'] = $validated['join_date'];
        }

        $user->update($updateData);

        $user->refresh()->load('role');

        $this->hierarchyService->handleRoleChange($user, (int) $validated['role_id']);

        if ($user->isAdmin() && $user->department_id) {
            $confirmed = $request->boolean('confirm_head_replace');
            $needsConfirm = $this->hierarchyService->assignAdminAsDepartmentHead($user, $confirmed);

            if ($needsConfirm) {
                return back()
                    ->withInput()
                    ->with('head_replace_prompt', $needsConfirm)
                    ->with('pending_user_id', $user->id);
            }
        }

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', 'User updated successfully.');
        }

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['delete' => 'Super Admin accounts cannot be deleted.']);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        // Removed clearHeadIfUserWasHead so the head_user_id stays intact for restoration
        $user->delete();

        return back()->with('success', 'User removed from HRMS. You can restore them from the deleted users list.');
    }

    public function restore(int $userId): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);

        if ($user->department_id) {
            $department = Department::withTrashed()->find($user->department_id);
            if (! $department || $department->trashed()) {
                $user->department_id = null;
            }
        }

        $user->restore();
        $user->refresh()->load(['role', 'department']);

        if ($user->isEmployee()) {
            $this->hierarchyService->syncManagerFromDepartment($user);
        }

        return back()->with('success', 'User restored successfully.');
    }

    public function checkHeadReplace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $department = Department::findOrFail($validated['department_id']);
        $newHead = User::findOrFail($validated['user_id']);

        $check = $this->hierarchyService->checkHeadReplacement($department, $newHead);

        return response()->json([
            'needs_confirmation' => $check !== null,
            'previous_head_name' => $check['previous_head_name'] ?? null,
        ]);
    }
}
