<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\User;
use App\Services\HierarchyService;
use App\Services\LeaveBalanceService;
use App\Rules\PhoneNumber;
use App\Rules\StrictEmail;
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

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if ($request->query('show') === 'pending') {
            return redirect()->route('super-admin.users.approvals');
        }

        $showDeleted = $request->query('show') === 'deleted';
        $search      = $request->query('search');

        $query = User::with(['role', 'department', 'manager', 'designation']);

        if ($showDeleted) {
            $query->onlyTrashed();
        } else {
            $query->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', User::STATUS_APPROVED);
            });
        }

        if (! empty($search)) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $users = $query->orderByRaw('special_order IS NULL, special_order ASC')->latest()->paginate(20)->withQueryString();

        $departments  = Department::orderBy('name')->get();
        // All users (including SuperAdmins like CTO Saad / CEO Ammar) are candidates for "immediate boss" assignment
        $allUsers    = User::orderByRaw('special_order IS NULL, special_order ASC')
            ->orderBy('name')
            ->get();
        $designations = Designation::orderBy('name')->get();

        return view('super-admin.users.index', compact('users', 'departments', 'allUsers', 'designations', 'showDeleted', 'search'));
    }

    public function edit(User $user): View
    {
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin accounts cannot be modified.');
        }

        $departments  = Department::orderBy('name')->get();
        $designations = Designation::orderBy('name')->get();
        // All users except the one being edited (to prevent self-boss)
        $allUsers     = User::where('id', '!=', $user->id)
            ->orderByRaw('special_order IS NULL, special_order ASC')
            ->orderBy('name')
            ->get();
        $year = (int) now()->year;

        $balances = collect();
        if (! $user->isGuest()) {
            // Initialize and load leave balances for the user
            foreach (\App\Models\LeaveType::where('is_active', true)->get() as $type) {
                $this->balanceService->ensureBalanceRow($user, $type, $year);
            }

            $balances = $user->leaveBalances()
                ->whereHas('leaveType', fn ($q) => $q->where('is_active', true))
                ->with('leaveType')
                ->where('year', $year)
                ->get()
                ->map(function ($balance) {
                    $balance->remaining = $this->balanceService->leftDays($balance);
                    $balance->pending   = $this->balanceService->sumPendingDays($balance->user_id, $balance->leave_type_id, $balance->year);
                    return $balance;
                });
        }

        return view('super-admin.users.edit', compact('user', 'departments', 'designations', 'allUsers', 'balances', 'year'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['role_id' => 'Super Admin accounts cannot be modified from this page.']);
        }

        $isGuest = $user->isGuest();

        $validated = $request->validate([
            'name'                    => ['sometimes', 'required', 'string', 'max:255'],
            'email'                   => ['sometimes', 'required', 'max:255', 'unique:users,email,' . $user->id, new StrictEmail],
            'employee_code'           => ['sometimes', 'nullable', 'string', 'max:50', 'unique:users,employee_code,' . $user->id],
            'phone'                   => ['sometimes', 'required', new PhoneNumber],
            'emergency_number'        => ['sometimes', $isGuest ? 'nullable' : 'required', new PhoneNumber],
            'address'                 => ['sometimes', 'nullable', 'string', 'max:500'],
            'join_date'               => ['sometimes', 'nullable', 'date'],
            'manager_id'              => ['sometimes', 'nullable', 'exists:users,id'],
            'designation_id'          => ['sometimes', 'nullable', 'exists:designations,id'],
            'designation_name'        => ['sometimes', 'nullable', 'string', 'max:40'],
            'department_id'           => ['sometimes', 'nullable', 'exists:departments,id'],
            'expected_salary_amount'  => ['sometimes', 'nullable', 'regex:/^(0|[1-9][0-9]*)$/', 'integer', 'min:0', 'max:9999999999'],
            'sales_commission'        => ['sometimes', 'nullable', 'regex:/^(0|[1-9][0-9]*)$/', 'integer', 'min:0', 'max:9999999999'],
            'expected_hour_per_day'   => ['sometimes', 'nullable', 'integer', 'min:0', 'max:24'],
            'expected_hours_per_week' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:168'],
            'new_password'            => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.required'               => 'The phone number field is required.',
            'emergency_number.required'    => 'The emergency number field is required.',
            'expected_salary_amount.regex' => 'The expected salary amount cannot have leading zeros (e.g. 001122) and must be 0 or greater.',
            'expected_salary_amount.min'   => 'The expected salary amount cannot be less than 0.',
            'sales_commission.regex'       => 'The sales commission cannot have leading zeros (e.g. 001122) and must be 0 or greater.',
            'sales_commission.min'         => 'The sales commission cannot be less than 0.',
        ]);

        // Prevent self-boss (silently clear if somehow submitted)
        if (! empty($validated['manager_id']) && (int) $validated['manager_id'] === $user->id) {
            $validated['manager_id'] = null;
        }

        // Prevent circular references
        if (! empty($validated['manager_id'])) {
            if ($this->hierarchyService->wouldCreateCycle($user, (int) $validated['manager_id'])) {
                return back()
                    ->withInput()
                    ->withErrors(['manager_id' => 'This assignment would create a circular reporting chain. Please choose a different boss.']);
            }
        }

        $updateData = [];

        if (array_key_exists('manager_id', $validated))              { $updateData['manager_id']              = $validated['manager_id']; }
        if (array_key_exists('department_id', $validated))           { $updateData['department_id']           = $validated['department_id']; }
        if (array_key_exists('expected_salary_amount', $validated))  { $updateData['expected_salary_amount']  = $validated['expected_salary_amount']; }
        if (array_key_exists('sales_commission', $validated))        { $updateData['sales_commission']        = $validated['sales_commission']; }
        if (array_key_exists('expected_hour_per_day', $validated))   { $updateData['expected_hour_per_day']   = $validated['expected_hour_per_day']; }
        if (array_key_exists('expected_hours_per_week', $validated)) { $updateData['expected_hours_per_week'] = $validated['expected_hours_per_week']; }

        if (array_key_exists('designation_name', $validated) && $request->has('designation_name')) {
            $defaultDesig = $user->isGuest() ? 'Guest Supervisor' : 'Employee';
            $desigName = trim($validated['designation_name'] ?? '') ?: $defaultDesig;
            $desigObj  = Designation::firstOrCreate(['name' => $desigName]);
            $updateData['designation_id'] = $desigObj->id;
        } elseif (array_key_exists('designation_id', $validated)) {
            $updateData['designation_id'] = $validated['designation_id'];
        }

        if (array_key_exists('name', $validated))             { $updateData['name']             = $validated['name']; }
        if (array_key_exists('email', $validated))            { $updateData['email']            = $validated['email']; }
        if (array_key_exists('phone', $validated))            { $updateData['phone']            = $validated['phone']; }
        if (array_key_exists('emergency_number', $validated)) { $updateData['emergency_number'] = $validated['emergency_number']; }
        if (array_key_exists('address', $validated))          { $updateData['address']          = $validated['address']; }
        if (array_key_exists('employee_code', $validated))    { $updateData['employee_code']    = $validated['employee_code']; }
        if (array_key_exists('join_date', $validated))        { $updateData['join_date']        = $validated['join_date']; }
        if (! empty($validated['new_password']))              { $updateData['password']         = \Illuminate\Support\Facades\Hash::make($validated['new_password']); }

        if (! empty($updateData)) {
            $user->update($updateData);
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

        return back()->with('success', 'User restored successfully.');
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update([
            'approval_status' => User::STATUS_APPROVED,
            'approved_at'     => now(),
            'rejected_at'     => null,
        ]);

        \App\Models\AccountApprovalRequest::where('user_id', $user->id)
            ->where('status', \App\Models\AccountApprovalRequest::STATUS_PENDING)
            ->update([
                'status'    => \App\Models\AccountApprovalRequest::STATUS_APPROVED,
                'action_by' => auth()->id(),
                'action_at' => now(),
            ]);

        $user->notify(new \App\Notifications\AccountApprovedNotification());

        return back()->with('success', "User '{$user->name}' has been approved and account access is now active.");
    }

    public function reject(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['reject' => 'Super Admin accounts cannot be rejected.']);
        }

        $user->update([
            'approval_status' => User::STATUS_REJECTED,
            'rejected_at'     => now(),
        ]);

        \App\Models\AccountApprovalRequest::where('user_id', $user->id)
            ->where('status', \App\Models\AccountApprovalRequest::STATUS_PENDING)
            ->update([
                'status'    => \App\Models\AccountApprovalRequest::STATUS_REJECTED,
                'action_by' => auth()->id(),
                'action_at' => now(),
            ]);

        $user->notify(new \App\Notifications\AccountRejectedNotification());

        return back()->with('success', "User '{$user->name}' registration has been rejected.");
    }
}
