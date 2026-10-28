<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->load(['role', 'department', 'designation', 'manager']);
        $departments = \App\Models\Department::orderBy('name')->get();

        return view('profile.edit', compact('user', 'departments'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Accurately resolve original department name using database ID before fill
        $oldDepartmentId = $user->getOriginal('department_id') ?? $user->department_id;
        $oldDept = $oldDepartmentId ? \App\Models\Department::find($oldDepartmentId) : null;
        $oldDepartmentName = $oldDept ? $oldDept->name : 'None';

        $oldSalarySlipEmail = $user->getOriginal('salary_slip_email');

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $departmentChanged = $user->isDirty('department_id');
        $newDepartmentId = $user->department_id;
        $newDept = $newDepartmentId ? \App\Models\Department::find($newDepartmentId) : null;
        $newDepartmentName = $newDept ? $newDept->name : 'None';

        $salarySlipEmailChanged = $user->isDirty('salary_slip_email');
        $newSalarySlipEmail = $user->salary_slip_email;

        $user->save();

        if ($departmentChanged && $oldDepartmentName !== $newDepartmentName) {
            \App\Models\User::whereHas('role', fn ($q) => $q->where('name', \App\Models\Role::SUPER_ADMIN))
                ->each(fn ($admin) => $admin->notify(new \App\Notifications\DepartmentChangedNotification(
                    $user->name,
                    $oldDepartmentName,
                    $newDepartmentName,
                    $user->id
                )));
        }

        if ($salarySlipEmailChanged && $oldSalarySlipEmail !== $newSalarySlipEmail) {
            \App\Models\User::whereHas('role', fn ($q) => $q->where('name', \App\Models\Role::SUPER_ADMIN))
                ->each(fn ($admin) => $admin->notify(new \App\Notifications\SalarySlipEmailUpdatedNotification(
                    $user->name,
                    $oldSalarySlipEmail,
                    $newSalarySlipEmail,
                    $user->name,
                    false,
                    $user->id,
                    $user->email
                )));
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'redirect' => url('/'),
            ]);
        }

        return Redirect::to('/');
    }
}
