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
use App\Rules\PhoneNumber;
use App\Rules\StrictEmail;
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
            'email' => ['required', 'string', 'lowercase', 'max:255', 'unique:'.User::class, new StrictEmail],
            'phone' => ['required', new PhoneNumber],
            'emergency_number' => ['required', new PhoneNumber],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'department_id' => ['required', 'exists:departments,id'],
        ]);

        $staffRoleId = Role::idByName(Role::STAFF);

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'role_id'           => $staffRoleId,
            'department_id'     => $request->department_id,
            'manager_id'        => null, // Super admin assigns boss after registration
            'phone'             => $request->phone,
            'emergency_number'  => $request->emergency_number,
            'join_date'         => now()->toDateString(),
            'email_verified_at' => now(),
            'approval_status'   => User::STATUS_PENDING,
        ]);

        $this->leaveBalanceService->initializeBalancesForUser($user);

        // Create Account Approval Request record
        \App\Models\AccountApprovalRequest::create([
            'user_id' => $user->id,
            'status'  => \App\Models\AccountApprovalRequest::STATUS_PENDING,
        ]);

        // Notify Super Admins of new registration requiring approval
        $superAdmins = User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))->get();
        if ($superAdmins->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($superAdmins, new \App\Notifications\NewUserRegisteredNotification($user));
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    public function createGuest(): View
    {
        return view('auth.register-guest');
    }

    public function storeGuest(Request $request): RedirectResponse
    {
        $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'string', 'lowercase', 'max:255', 'unique:'.User::class, new StrictEmail],
            'phone'            => ['required', new PhoneNumber],
            'emergency_number' => ['nullable', new PhoneNumber],
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $guestRoleId = Role::idByName(Role::GUEST);
        $guestDesignation = \App\Models\Designation::firstOrCreate(['name' => 'Guest Supervisor']);

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'role_id'           => $guestRoleId,
            'designation_id'    => $guestDesignation->id,
            'department_id'     => null,
            'manager_id'        => null,
            'phone'             => $request->phone,
            'emergency_number'  => $request->emergency_number,
            'join_date'         => now()->toDateString(),
            'email_verified_at' => now(),
            'approval_status'   => User::STATUS_PENDING,
        ]);

        // Create Account Approval Request record
        \App\Models\AccountApprovalRequest::create([
            'user_id' => $user->id,
            'status'  => \App\Models\AccountApprovalRequest::STATUS_PENDING,
        ]);

        // Notify Super Admins of new guest registration requiring approval
        $superAdmins = User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))->get();
        if ($superAdmins->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($superAdmins, new \App\Notifications\NewUserRegisteredNotification($user));
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
