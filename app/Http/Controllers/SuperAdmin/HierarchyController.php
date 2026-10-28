<?php

namespace App\Http\Controllers\SuperAdmin;

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
     * Display the System Hierarchy / Organization Chart page.
     */
    public function index(Request $request): View
    {
        $departments = Department::withCount('users')
            ->orderBy('name')
            ->get();

        $stats = $this->hierarchyService->getHierarchyStatistics();

        // Get count of employees who have no department assigned
        $unassignedUsersCount = User::whereNull('department_id')->count();

        return view('super-admin.hierarchy.index', compact('departments', 'stats', 'unassignedUsersCount'));
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
