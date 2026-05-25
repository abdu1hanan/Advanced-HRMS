<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\HierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(protected HierarchyService $hierarchyService) {}

    public function index(Request $request): View
    {
        $showDeleted = $request->query('show') === 'deleted';

        $query = Department::with('head')->withCount('users')->orderBy('name');

        if ($showDeleted) {
            $query->onlyTrashed();
        }

        $departments = $query->get();
        $admins = User::whereHas('role', fn ($q) => $q->where('name', Role::ADMIN))->orderBy('name')->get();

        return view('super-admin.departments.index', compact('departments', 'admins', 'showDeleted'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
        ]);

        Department::create($validated);

        return back()->with('success', 'Department created.');
    }

    public function assignHead(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'head_user_id' => ['nullable', 'exists:users,id'],
            'confirm_head_replace' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['head_user_id'])) {
            $this->hierarchyService->removeDepartmentHead($department);

            return back()->with('success', 'Department head removed.');
        }

        $head = User::findOrFail($validated['head_user_id']);
        if (! $head->hasRole(Role::ADMIN)) {
            return back()->withErrors(['head_user_id' => 'Department head must have Admin role.']);
        }

        $confirmed = $request->boolean('confirm_head_replace');
        if (! $confirmed) {
            $check = $this->hierarchyService->checkHeadReplacement($department, $head);
            if ($check) {
                return back()
                    ->withInput()
                    ->with('head_replace_prompt', $check)
                    ->with('pending_department_id', $department->id)
                    ->with('pending_head_user_id', $head->id);
            }
        }

        $this->hierarchyService->replaceDepartmentHead($department, $head);

        return back()->with('success', 'Department head assigned.');
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
