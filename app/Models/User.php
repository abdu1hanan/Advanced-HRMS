<?php

namespace App\Models;

use App\Casts\EncryptedSalary;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'salary_slip_email',
    'password',
    'role_id',
    'special_order',
    'department_id',
    'manager_id',
    'designation_id',
    'employee_code',
    'phone',
    'emergency_number',
    'address',
    'join_date',
    'expected_salary_amount',
    'sales_commission',
    'expected_hour_per_day',
    'expected_hours_per_week',
    'approval_status',
    'approved_at',
    'rejected_at',
    'activation_requested_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'join_date'               => 'date',
            'special_order'           => 'integer',
            'expected_salary_amount'  => EncryptedSalary::class,
            'sales_commission'        => EncryptedSalary::class,
            'expected_hour_per_day'   => 'integer',
            'expected_hours_per_week' => 'integer',
            'approved_at'             => 'datetime',
            'rejected_at'             => 'datetime',
            'activation_requested_at' => 'datetime',
        ];
    }

    public function slackMapping(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SlackUserMapping::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function getDesignationNameAttribute(): string
    {
        if ($this->designation?->name) {
            return $this->designation->name;
        }
        if ($this->isSuperAdmin()) {
            return match ($this->special_order) {
                1 => 'CEO',
                2 => 'CTO',
                3 => 'HR Manager',
                default => 'Super Admin',
            };
        }
        if ($this->isGuest()) {
            return 'Guest Supervisor';
        }
        return 'Employee';
    }

    /**
     * The user's immediate boss (their direct manager).
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Direct reports only (one level down).
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function accountApprovalRequests(): HasMany
    {
        return $this->hasMany(AccountApprovalRequest::class);
    }

    /**
     * Recursively collect ALL subordinate user IDs (all levels below this user).
     * Uses iterative BFS to be safe against cycles.
     */
    public function getSubordinateIdsAttribute(): array
    {
        $visited = [];
        $queue   = [$this->id];

        while (! empty($queue)) {
            $current       = array_shift($queue);
            $directReports = self::query()
                ->where('manager_id', $current)
                ->pluck('id')
                ->toArray();

            foreach ($directReports as $id) {
                if (! in_array($id, $visited, true)) {
                    $visited[] = $id;
                    $queue[]   = $id;
                }
            }
        }

        return $visited;
    }

    /**
     * Whether this user has any subordinates (any level deep).
     */
    public function hasSubordinates(): bool
    {
        // Quick check — just look for at least one direct report
        return self::query()->where('manager_id', $this->id)->exists();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isStaff(): bool
    {
        return $this->hasRole(Role::STAFF);
    }

    public function isGuest(): bool
    {
        return $this->hasRole(Role::GUEST);
    }

    public function isDepartmentHead(): bool
    {
        return (bool) ($this->department && $this->department->head_id === $this->id);
    }

    public function isApproved(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ($this->approval_status ?? self::STATUS_APPROVED) === self::STATUS_APPROVED;
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED;
    }

    public function dashboardRoute(): string
    {
        return match ($this->role?->name) {
            Role::SUPER_ADMIN => 'super-admin.dashboard',
            default           => 'admin.dashboard',
        };
    }
}
