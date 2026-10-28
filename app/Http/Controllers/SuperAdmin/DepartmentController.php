<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $showDeleted = $request->query('show') === 'deleted';

        $query = Department::with(['head.designation'])->withCount('users')->orderBy('name');

        if ($showDeleted) {
            $query->onlyTrashed();
        }

        $departments = $query->get();
        $users       = User::with(['designation', 'role'])->orderBy('name')->get();

        return view('super-admin.departments.index', compact('departments', 'showDeleted', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:30', 'unique:departments,name'],
            'code'    => ['required', 'string', 'max:5', 'unique:departments,code'],
            'head_id' => ['nullable', 'exists:users,id'],
        ]);

        Department::create($validated);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:30', 'unique:departments,name,' . $department->id],
            'code'    => ['required', 'string', 'max:5', 'unique:departments,code,' . $department->id],
            'head_id' => ['nullable', 'exists:users,id'],
        ]);

        $department->update($validated);

        return back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $activeUsers = User::where('department_id', $department->id)->count();

        if ($activeUsers > 0) {
            return back()->withErrors([
                'delete' => 'Cannot delete: active employees are assigned to this department.',
            ]);
        }

        $department->delete();

        return back()->with('success', 'Department removed. You can restore it from the deleted list.');
    }

    public function restore(int $departmentId): RedirectResponse
    {
        $department = Department::onlyTrashed()->findOrFail($departmentId);
        $department->restore();

        return back()->with('success', 'Department restored successfully.');
    }
}
