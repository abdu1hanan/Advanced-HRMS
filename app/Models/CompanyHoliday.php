<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CompanyHoliday extends Model
{
    public const TARGET_ALL = 'all';
    public const TARGET_DEPARTMENT = 'department';
    public const TARGET_EMPLOYEE = 'employee';

    public const ACTION_INCLUDE = 'include';
    public const ACTION_EXCLUDE = 'exclude';

    protected $fillable = [
        'name',
        'date',
        'target_type',
        'target_action',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'company_holiday_departments');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_holiday_users');
    }

    /**
     * Get all active non-super-admin users who are eligible for this holiday.
     */
    public function getApplicableUsers(): Collection
    {
        $baseQuery = User::whereHas('role', fn($q) => $q->whereNotIn('name', [Role::SUPER_ADMIN, Role::GUEST]));

        if ($this->target_type === self::TARGET_DEPARTMENT) {
            $deptIds = $this->departments->pluck('id')->toArray();
            if ($this->target_action === self::ACTION_EXCLUDE) {
                return $baseQuery->where(function($q) use ($deptIds) {
                    $q->whereNotIn('department_id', $deptIds)
                      ->orWhereNull('department_id');
                })->get();
            }
            return $baseQuery->whereIn('department_id', $deptIds)->get();
        }

        if ($this->target_type === self::TARGET_EMPLOYEE) {
            $userIds = $this->users->pluck('id')->toArray();
            if ($this->target_action === self::ACTION_EXCLUDE) {
                return $baseQuery->whereNotIn('id', $userIds)->get();
            }
            return $baseQuery->whereIn('id', $userIds)->get();
        }

        // Default: TARGET_ALL
        return $baseQuery->get();
    }

    /**
     * Check if this holiday applies to a given User model or User ID.
     */
    public function appliesToUser(User|int $user): bool
    {
        if (is_int($user)) {
            $userObj = User::find($user);
            if (!$userObj) return false;
            $user = $userObj;
        }

        if ($this->target_type === self::TARGET_DEPARTMENT) {
            $deptIds = $this->relationLoaded('departments')
                ? $this->departments->pluck('id')->toArray()
                : $this->departments()->pluck('departments.id')->toArray();

            $userDeptId = $user->department_id;

            if ($this->target_action === self::ACTION_EXCLUDE) {
                return !$userDeptId || !in_array($userDeptId, $deptIds);
            }
            return $userDeptId && in_array($userDeptId, $deptIds);
        }

        if ($this->target_type === self::TARGET_EMPLOYEE) {
            $userIds = $this->relationLoaded('users')
                ? $this->users->pluck('id')->toArray()
                : $this->users()->pluck('users.id')->toArray();

            if ($this->target_action === self::ACTION_EXCLUDE) {
                return !in_array($user->id, $userIds);
            }
            return in_array($user->id, $userIds);
        }

        return true;
    }

    /**
     * Static helper to find if a specific date is an applicable holiday for a given user.
     */
    public static function isHolidayForUser(User|int $user, string $dateStr): ?self
    {
        $holidays = self::with(['departments', 'users'])
            ->where('date', $dateStr)
            ->get();

        foreach ($holidays as $holiday) {
            if ($holiday->appliesToUser($user)) {
                return $holiday;
            }
        }

        return null;
    }

    /**
     * Get UI summary badge data for this holiday's target scope.
     */
    public function getTargetBadgeData(): array
    {
        if ($this->target_type === self::TARGET_DEPARTMENT) {
            $depts = $this->departments;
            $deptNames = $depts->pluck('name')->toArray();
            $count = count($deptNames);
            $totalDepts = Department::count();

            // Auto-promote to Whole Company if all departments are included
            if ($this->target_action === self::ACTION_INCLUDE && $totalDepts > 0 && $count >= $totalDepts) {
                return [
                    'type'        => 'all',
                    'badge_class' => 'row-badge-green',
                    'icon'        => 'bi-info-circle',
                    'label'       => 'Whole Company',
                    'details'     => 'All departments included (Whole Company)',
                    'items'       => ['All Employees'],
                ];
            }

            return [
                'type'        => 'department',
                'badge_class' => 'row-badge-warning',
                'icon'        => 'bi-info-circle',
                'label'       => 'Some Departments',
                'details'     => ($this->target_action === self::ACTION_EXCLUDE ? "All departments except: " : "Specific Departments: ") . implode(', ', $deptNames),
                'items'       => $deptNames,
            ];
        }

        if ($this->target_type === self::TARGET_EMPLOYEE) {
            $users = $this->users;
            $userNames = $users->pluck('name')->toArray();
            $count = count($userNames);
            $totalUsers = User::whereHas('role', fn($q) => $q->whereNotIn('name', [Role::SUPER_ADMIN, Role::GUEST]))->count();

            // Auto-promote to Whole Company if all employees are included
            if ($this->target_action === self::ACTION_INCLUDE && $totalUsers > 0 && $count >= $totalUsers) {
                return [
                    'type'        => 'all',
                    'badge_class' => 'row-badge-green',
                    'icon'        => 'bi-info-circle',
                    'label'       => 'Whole Company',
                    'details'     => 'All employees included (Whole Company)',
                    'items'       => ['All Employees'],
                ];
            }

            return [
                'type'        => 'employee',
                'badge_class' => 'row-badge-purple',
                'icon'        => 'bi-info-circle',
                'label'       => 'Some Employees',
                'details'     => ($this->target_action === self::ACTION_EXCLUDE ? "All employees except: " : "Specific Employees: ") . implode(', ', $userNames),
                'items'       => $userNames,
            ];
        }

        return [
            'type'        => 'all',
            'badge_class' => 'row-badge-green',
            'icon'        => 'bi-info-circle',
            'label'       => 'Whole Company',
            'details'     => 'All employees across company',
            'items'       => ['All Employees'],
        ];
    }

    protected static function booted(): void
    {
        $clearAllCacheForDate = static function (self $holiday) {
            if ($holiday->date) {
                $dateStr = $holiday->date->format('Y-m-d');
                $slackUserIds = SlackUserMapping::pluck('slack_user_id')->filter();
                foreach ($slackUserIds as $slackUserId) {
                    Cache::forget("healed_{$slackUserId}_{$dateStr}");
                }
            }
        };

        static::saved($clearAllCacheForDate);
        static::deleted($clearAllCacheForDate);
    }
}
