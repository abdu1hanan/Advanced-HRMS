<?php

namespace App\Models;

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
    'password',
    'role_id',
    'department_id',
    'manager_id',
    'employee_code',
    'phone',
    'join_date',
    'expected_salary_amount',
    'expected_hour_per_day',
    'expected_hours_per_week',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'join_date' => 'date',
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

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teamMembers(): HasMany
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

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    public function getSubordinateIdsAttribute(): array
    {
        $deptIds = $this->headedDepartments()->pluck('id');
        return self::query()
            ->where(function($query) use ($deptIds) {
                $query->where('manager_id', $this->id)
                      ->orWhereIn('department_id', $deptIds);
            })
            ->where('id', '!=', $this->id)
            ->pluck('id')
            ->toArray();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(Role::EMPLOYEE);
    }

    public function dashboardRoute(): string
    {
        return match ($this->role?->name) {
            Role::SUPER_ADMIN => 'super-admin.dashboard',
            Role::ADMIN => 'admin.dashboard',
            default => 'employee.dashboard',
        };
    }
}
