<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class HierarchyService
{
    /**
     * Clear hierarchy cache across the application by bumping the cache version.
     */
    public static function clearCache(): void
    {
        $current = (int) Cache::get('org_hierarchy_cache_version', 1);
        Cache::put('org_hierarchy_cache_version', $current + 1, 86400 * 365);
    }

    /**
     * Build the hierarchical organization tree.
     * Cached efficiently and automatically invalidated when organization or attendance data changes.
     */
    public function buildFullOrganizationTree(?int $departmentId = null): array
    {
        $version = (int) Cache::get('org_hierarchy_cache_version', 1);
        $deptKey = $departmentId ? (string) $departmentId : 'all';
        $todayStr = now()->format('Y-m-d');
        $cacheKey = "org_tree_v{$version}_{$deptKey}_{$todayStr}";

        return Cache::remember($cacheKey, 86400, function () use ($departmentId) {
            return $this->computeOrganizationTree($departmentId);
        });
    }

    /**
     * Compute the organization tree from database relations.
     */
    public function computeOrganizationTree(?int $departmentId = null): array
    {
        $today = now()->startOfDay();
        $todayStr = $today->format('Y-m-d');

        // Fetch all active users with their relations, ordered by special_order ASC then name ASC
        $allUsers = User::with(['role', 'department', 'designation', 'manager.designation'])
            ->orderByRaw('special_order IS NULL, special_order ASC')
            ->orderBy('name')
            ->get();

        // Fetch today's leaves, remotes, and absents
        $activeUserIds = $allUsers->pluck('id')->toArray();

        $leavesToday = \App\Models\LeaveRequest::whereIn('user_id', $activeUserIds)
            ->where('status', \App\Models\LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->with('leaveType')
            ->get()
            ->keyBy('user_id');

        $remotesToday = \App\Models\RemoteWorkLog::whereIn('user_id', $activeUserIds)
            ->where('date', $todayStr)
            ->get()
            ->keyBy('user_id');

        $absentsToday = \App\Models\AttendanceRecord::whereIn('user_id', $activeUserIds)
            ->where('date', $todayStr)
            ->get()
            ->keyBy('user_id');

        // Identify Super Admins ordered by special_order (1: Ammar, 2: Saad, 3: HR)
        $superAdmins = $allUsers->filter(function ($u) {
            return $u->isSuperAdmin() || ($u->role && $u->role->name === \App\Models\Role::SUPER_ADMIN);
        })->sortBy(fn($u) => $u->special_order ?? 999)->values();

        $topSuperAdmin = $superAdmins->first(); // special_order 1: Ammar (CEO)
        $ctoSuperAdmin = $superAdmins->firstWhere('special_order', 2); // special_order 2: Saad (CTO)
        $hrSuperAdmin  = $superAdmins->firstWhere('special_order', 3) ?? $superAdmins->last(); // special_order 3: HR

        // Group users by manager_id for fast tree lookup
        $usersByManager = [];
        $userMap = [];

        foreach ($allUsers as $user) {
            $userMap[$user->id] = $user;
        }

        // Connect users by manager_id or by executive hierarchy
        foreach ($allUsers as $user) {
            $effectiveMgrId = $user->manager_id ? (int) $user->manager_id : 0;
            $isSuperAdmin = $user->isSuperAdmin() || ($user->role && $user->role->name === \App\Models\Role::SUPER_ADMIN);

            if ($isSuperAdmin) {
                // Executive hierarchy spine:
                // Ammar (1) is apex root (mgr: 0)
                // Saad (2) is under Ammar (mgr: Ammar)
                // HR (3) is under Saad (mgr: Saad) or under Ammar if Saad missing
                if ($user->special_order === 1 || ($topSuperAdmin && $user->id === $topSuperAdmin->id)) {
                    $effectiveMgrId = 0;
                } elseif ($user->special_order === 2) {
                    $effectiveMgrId = $topSuperAdmin ? $topSuperAdmin->id : 0;
                } elseif ($user->special_order === 3) {
                    $effectiveMgrId = $ctoSuperAdmin ? $ctoSuperAdmin->id : ($topSuperAdmin ? $topSuperAdmin->id : 0);
                } else {
                    $effectiveMgrId = $hrSuperAdmin ? $hrSuperAdmin->id : ($topSuperAdmin ? $topSuperAdmin->id : 0);
                }
            } else {
                // For non-super-admin users:
                // If user has no manager_id (or manager not found), attach under HR (special_order 3)
                if ($effectiveMgrId === 0 || ! isset($userMap[$effectiveMgrId])) {
                    $effectiveMgrId = $hrSuperAdmin ? $hrSuperAdmin->id : ($topSuperAdmin ? $topSuperAdmin->id : 0);
                }
            }

            if (! isset($usersByManager[$effectiveMgrId])) {
                $usersByManager[$effectiveMgrId] = [];
            }
            $usersByManager[$effectiveMgrId][] = $user;
        }

        // Helper to format a single user node
        $formatNode = function (User $user) use (&$formatNode, &$usersByManager, $leavesToday, $remotesToday, $absentsToday, $departmentId, $userMap, $topSuperAdmin) {
            // Determine status
            $status = 'present';
            $statusLabel = 'In Office';
            $statusColor = '#10b981'; // emerald green

            if (isset($leavesToday[$user->id])) {
                $status = 'on_leave';
                $statusLabel = 'On Leave (' . ($leavesToday[$user->id]->leaveType?->name ?? 'Leave') . ')';
                $statusColor = '#f59e0b'; // amber
            } elseif (isset($remotesToday[$user->id])) {
                $status = 'remote';
                $statusLabel = 'Remote Work';
                $statusColor = '#3b82f6'; // blue
            } elseif (isset($absentsToday[$user->id])) {
                $status = 'absent';
                $statusLabel = 'Marked Absent';
                $statusColor = '#ef4444'; // red
            }

            // Initials
            $nameParts = array_filter(explode(' ', trim($user->name)));
            $initials = '';
            foreach (array_slice($nameParts, 0, 2) as $part) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            }
            if (empty($initials)) {
                $initials = mb_strtoupper(mb_substr($user->name, 0, 2));
            }

            // Tenure calculation
            $tenure = '—';
            if ($user->join_date) {
                $diff = $user->join_date->diff(now());
                if ($diff->y > 0) {
                    $tenure = $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') . ($diff->m > 0 ? ' ' . $diff->m . ' mo' : '');
                } elseif ($diff->m > 0) {
                    $tenure = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                } else {
                    $tenure = max(1, $diff->d) . ' day' . ($diff->d > 1 ? 's' : '');
                }
            }

            // Subordinate children
            $children = [];
            $directReports = $usersByManager[$user->id] ?? [];

            foreach ($directReports as $report) {
                $children[] = $formatNode($report);
            }

            // Calculate total subordinates count recursively
            $totalSubordinates = count($children);
            foreach ($children as $child) {
                $totalSubordinates += $child['total_subordinates_count'] ?? 0;
            }

            $isSuperAdmin = $user->isSuperAdmin() || ($user->role && $user->role->name === \App\Models\Role::SUPER_ADMIN);

            // Effective Manager Name
            $mgrName = null;
            $mgrDesig = null;
            if ($user->manager) {
                $mgrName = $user->manager->name;
                $mgrDesig = $user->manager->designation?->name ?? ($user->manager->isSuperAdmin() ? 'Super Admin' : 'Employee');
            } elseif ($user->manager_id && isset($userMap[$user->manager_id])) {
                $mgr = $userMap[$user->manager_id];
                $mgrName = $mgr->name;
                $mgrDesig = $mgr->designation?->name ?? ($mgr->isSuperAdmin() ? 'Super Admin' : 'Employee');
            }

            // Designation display name
            $desigName = $user->designation?->name;
            if (empty($desigName)) {
                if ($isSuperAdmin) {
                    $desigName = match ($user->special_order) {
                        1 => 'CEO',
                        2 => 'CTO',
                        3 => 'HR Manager',
                        default => 'Super Admin',
                    };
                } elseif ($user->isGuest()) {
                    $desigName = 'Guest Supervisor';
                } else {
                    $desigName = 'Employee';
                }
            }

            // Department name & code for SuperAdmins / Guests / Users
            $deptName = $user->department?->name ?? ($isSuperAdmin ? 'Executive Management' : ($user->isGuest() ? 'External' : 'Unassigned'));
            $deptCode = $user->department?->code ?? ($isSuperAdmin ? 'EXEC' : ($user->isGuest() ? 'EXT' : '—'));

            return [
                'id'                       => $user->id,
                'name'                     => $user->name,
                'initials'                 => $initials,
                'email'                    => $user->email,
                'salary_slip_email'        => $user->salary_slip_email,
                'employee_code'            => $user->employee_code ?? ('EMP-' . str_pad($user->id, 3, '0', STR_PAD_LEFT)),
                'phone'                    => $user->phone ?? '—',
                'emergency_number'         => $user->emergency_number ?? '—',
                'address'                  => $user->address ?? '—',
                'join_date'                => $user->join_date ? $user->join_date->format('Y-m-d') : null,
                'join_date_formatted'      => $user->join_date ? $user->join_date->format('M d, Y') : '—',
                'tenure'                   => $tenure,
                'department_id'            => $user->department_id,
                'department_name'          => $deptName,
                'department_code'          => $deptCode,
                'department_head_name'     => $user->department?->head?->name ?? 'Unassigned',
                'designation_id'           => $user->designation_id,
                'designation_name'         => $desigName,
                'role_id'                  => $user->role_id,
                'role_name'                => $user->role?->name ?? 'staff',
                'role_label'               => $isSuperAdmin ? 'Super Admin' : ($user->isGuest() ? 'Guest' : ($user->role?->label ?? 'Staff')),
                'is_super_admin'           => $isSuperAdmin,
                'is_guest'                 => $user->isGuest(),
                'special_order'            => $user->special_order,
                'manager_id'               => $user->manager_id,
                'manager_name'             => $mgrName,
                'manager_designation'      => $mgrDesig,
                'status'                   => $status,
                'status_label'             => $statusLabel,
                'status_color'             => $statusColor,
                'direct_reports_count'     => count($children),
                'total_subordinates_count' => $totalSubordinates,
                'children'                 => $children,
            ];
        };

        // Determine root nodes
        $roots = [];

        if ($departmentId !== null) {
            // Find root users for this specific department:
            $deptUsers = $allUsers->where('department_id', $departmentId);
            $deptUserIds = $deptUsers->pluck('id')->toArray();

            foreach ($deptUsers as $user) {
                if (! $user->manager_id || ! in_array($user->manager_id, $deptUserIds, true)) {
                    $roots[] = $formatNode($user);
                }
            }
        } else {
            // Whole Organization: Apex is Top Super Admin (Ammar - 1)
            if ($topSuperAdmin) {
                $roots[] = $formatNode($topSuperAdmin);
            } else {
                $activeIds = $allUsers->pluck('id')->toArray();
                foreach ($allUsers as $user) {
                    if (! $user->manager_id || ! in_array($user->manager_id, $activeIds, true)) {
                        $roots[] = $formatNode($user);
                    }
                }
            }
        }

        return $roots;
    }

    /**
     * Compute summary statistics for organization hierarchy.
     */
    public function getHierarchyStatistics(): array
    {
        $allUsers = User::with(['department', 'designation'])->get();
        $totalUsers = $allUsers->count();

        // Managers: users who are set as manager_id on at least one active user
        $managerIds = User::whereNotNull('manager_id')->distinct()->pluck('manager_id')->toArray();
        $totalManagers = count($managerIds);

        // Departments count
        $totalDepartments = \App\Models\Department::count();

        $superAdminRoleId = \App\Models\Role::idByName(\App\Models\Role::SUPER_ADMIN);
        $guestRoleId      = \App\Models\Role::idByName(\App\Models\Role::GUEST);
        $unassignedManager = $allUsers->whereNull('manager_id')->where('role_id', '!=', $superAdminRoleId)->count();
        $unassignedDept    = $allUsers->whereNull('department_id')->whereNotIn('role_id', array_filter([$superAdminRoleId, $guestRoleId]))->count();

        // Calculate max depth
        $maxDepth = 1;
        foreach ($allUsers as $user) {
            $chain = $this->getBossChain($user);
            $depth = count($chain) + 1;
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        // Total designations
        $totalDesignations = \App\Models\Designation::count();

        return [
            'total_users'        => $totalUsers,
            'total_managers'     => $totalManagers,
            'total_departments'  => $totalDepartments,
            'total_designations' => $totalDesignations,
            'max_depth'          => $maxDepth,
            'unassigned_manager' => $unassignedManager,
            'unassigned_dept'    => $unassignedDept,
        ];
    }

    /**
     * Check whether setting $bossId as the immediate boss of $user
     * would create a circular reference in the hierarchy.
     *
     * Walks upward from $bossId until null; if it reaches $user->id, there's a cycle.
     */
    public function wouldCreateCycle(User $user, int $bossId): bool
    {
        $current = User::find($bossId);

        while ($current !== null) {
            if ((int) $current->id === (int) $user->id) {
                return true;
            }
            $current = $current->manager_id ? User::find($current->manager_id) : null;
        }

        return false;
    }

    /**
     * Walk upward from a user and return everyone in the boss chain
     * (not including the user themselves), ordered nearest-boss first.
     *
     * @return User[]
     */
    public function getBossChain(User $user): array
    {
        $chain     = [];
        $visited   = [$user->id];
        $currentId = $user->manager_id;

        while ($currentId && !in_array($currentId, $visited, true)) {
            $visited[] = $currentId;
            $current   = User::find($currentId);
            if (! $current) {
                break;
            }
            $chain[]   = $current;
            $currentId = $current->manager_id;
        }

        return $chain;
    }
}


