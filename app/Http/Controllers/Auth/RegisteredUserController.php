<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\HierarchyService;
use App\Services\LeaveBalanceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected HierarchyService $hierarchyService,
        protected LeaveBalanceService $leaveBalanceService
    ) {}

    public function create(): View
    {
        $departments = Department::orderBy('name')->get();

        return view('auth.register', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'department_id' => ['required', 'exists:departments,id'],
        ]);

        $employeeRoleId = Role::idByName(Role::EMPLOYEE);
        $managerId = $this->hierarchyService->resolveManagerForDepartment((int) $request->department_id);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $employeeRoleId,
            'department_id' => $request->department_id,
            'manager_id' => $managerId,
            'join_date' => now()->toDateString(),
            'email_verified_at' => now(),
        ]);

        $this->leaveBalanceService->initializeBalancesForUser($user);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
