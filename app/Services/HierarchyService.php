<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;

class HierarchyService
{
    public function resolveManagerForDepartment(?int $departmentId): ?int
    {
        if (! $departmentId) {
            return null;
        }

        $department = Department::find($departmentId);
        
        if ($department && $department->head_user_id) {
            $head = User::find($department->head_user_id);
            if ($head) {
                return $head->id;
            }
        }

        return null;
    }

    public function assignManagerOnRegistration(User $user): void
    {
        if (! $user->isEmployee()) {
            return;
        }

        $managerId = $this->resolveManagerForDepartment($user->department_id);

        if ($managerId) {
            $user->update(['manager_id' => $managerId]);
        }
    }

    public function syncManagerFromDepartment(User $user): void
    {
        if ($user->isEmployee()) {
            $user->update([
                'manager_id' => $this->resolveManagerForDepartment($user->department_id),
            ]);

            return;
        }

        if ($user->isAdmin()) {
            $user->update(['manager_id' => null]);
        }
    }

    /**
     * Check if replacing department head needs confirmation.
     *
     * @return array{needs_confirmation: bool, previous_head_name?: string, previous_head_id?: int}|null
     */
    public function checkHeadReplacement(Department $department, User $newHead): ?array
    {
        if ($department->head_user_id && (int) $department->head_user_id !== (int) $newHead->id) {
            $previous = User::find($department->head_user_id);

            return [
                'needs_confirmation' => true,
                'previous_head_name' => $previous?->name ?? 'Unknown',
                'previous_head_id' => $department->head_user_id,
            ];
        }

        return null;
    }

    /**
     * Assign or replace department head; previous head in same dept reports to new head.
     */
    public function replaceDepartmentHead(Department $department, User $newHead): void
    {
        if (! $newHead->isAdmin()) {
            return;
        }

        $previousHeadId = $department->head_user_id;

        if ($previousHeadId && (int) $previousHeadId !== (int) $newHead->id) {
            $previousHead = User::find($previousHeadId);
            if ($previousHead) {
                $employeeRoleId = Role::idByName(Role::EMPLOYEE);
                $updateData = ['role_id' => $employeeRoleId];
                
                if ((int) $previousHead->department_id === (int) $department->id) {
                    $updateData['manager_id'] = $newHead->id;
                }
                
                $previousHead->update($updateData);
            }
        }

        $department->update(['head_user_id' => $newHead->id]);
        $this->syncEmployeesToDepartmentHead($department);
    }

    /**
     * Remove department head, clean up managers, and revert role if not heading other depts.
     */
    public function removeDepartmentHead(Department $department): void
    {
        $previousHeadId = $department->head_user_id;
        if (! $previousHeadId) {
            return;
        }

        $previousHead = User::find($previousHeadId);

        // 1. Set department head_user_id to null
        $department->update(['head_user_id' => null]);

        // 2. Clear manager_id for employees managed by this head in this department
        User::where('department_id', $department->id)
            ->where('manager_id', $previousHeadId)
            ->update(['manager_id' => null]);

        if ($previousHead) {
            // 3. Revert previous head's role to Employee if they head NO other departments
            $stillHeadingOther = Department::where('head_user_id', $previousHead->id)->exists();
            if (! $stillHeadingOther) {
                $employeeRoleId = Role::idByName(Role::EMPLOYEE);
                $previousHead->update([
                    'role_id' => $employeeRoleId,
                    'manager_id' => $this->resolveManagerForDepartment($previousHead->department_id),
                ]);
            }
        }
    }

    public function assignAdminAsDepartmentHead(User $admin, bool $confirmed = false): ?array
    {
        if (! $admin->isAdmin() || ! $admin->department_id) {
            return null;
        }

        $department = Department::find($admin->department_id);

        if (! $department) {
            return null;
        }

        if (! $confirmed) {
            $check = $this->checkHeadReplacement($department, $admin);
            if ($check) {
                return $check;
            }
        }

        $this->replaceDepartmentHead($department, $admin);

        return null;
    }

    public function clearHeadIfUserWasHead(User $user): void
    {
        Department::where('head_user_id', $user->id)->update(['head_user_id' => null]);
        User::where('manager_id', $user->id)->update(['manager_id' => null]);
    }

    public function handleRoleChange(User $user, int $newRoleId): void
    {
        $newRole = Role::find($newRoleId);
        if (! $newRole) {
            return;
        }

        if ($newRole->name !== Role::ADMIN) {
            $this->clearHeadIfUserWasHead($user);
        }

        if ($newRole->name === Role::EMPLOYEE) {
            $this->syncManagerFromDepartment($user);
        } elseif ($newRole->name === Role::ADMIN) {
            $user->update(['manager_id' => null]);
        }
    }

    public function syncEmployeesToDepartmentHead(Department $department): void
    {
        if (! $department->head_user_id) {
            return;
        }

        $head = User::find($department->head_user_id);
        if (!$head) {
            return;
        }

        User::query()
            ->where('department_id', $department->id)
            ->whereHas('role', fn ($q) => $q->where('name', Role::EMPLOYEE))
            ->update(['manager_id' => $department->head_user_id]);
    }

    /** @deprecated Use syncEmployeesToDepartmentHead */
    public function backfillManagersForDepartment(Department $department): void
    {
        $this->syncEmployeesToDepartmentHead($department);
    }
}
