<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\HierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HierarchyController extends Controller
{
    public function __construct(
        protected HierarchyService $hierarchyService
    ) {}

    /**
     * Display the Organization Hierarchy / Org Chart page for staff.
     */
    public function index(Request $request): View
    {
        $departments = Department::withCount('users')
            ->orderBy('name')
            ->get();

        $stats = $this->hierarchyService->getHierarchyStatistics();
        $currentUser = auth()->user();
        $userDeptId = $currentUser->department_id;
        $userDept = $userDeptId ? $departments->firstWhere('id', $userDeptId) : null;
        $unassignedUsersCount = User::whereNull('department_id')->count();

        return view('admin.hierarchy.index', compact(
            'departments',
            'stats',
            'currentUser',
            'userDeptId',
            'userDept',
            'unassignedUsersCount'
        ));
    }

    /**
     * Return structured JSON tree data for dynamic rendering and filtering.
     */
    public function getTreeData(Request $request): JsonResponse
    {
        $departmentId = $request->filled('department_id') && $request->department_id !== 'all'
            ? (int) $request->department_id
            : null;

        $roots = $this->hierarchyService->buildFullOrganizationTree($departmentId);
        $stats = $this->hierarchyService->getHierarchyStatistics();

        return response()->json([
            'status'        => 'success',
            'department_id' => $departmentId,
            'stats'         => $stats,
            'roots'         => $roots,
        ]);
    }
}
