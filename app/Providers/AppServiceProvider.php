<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\UserPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);

        Gate::define('manage-departments', fn (User $user) => $user->isSuperAdmin());
    }
}
