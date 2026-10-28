<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveRequest;
use App\Models\RemoteWorkLog;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\UserPolicy;
use App\Services\HierarchyService;
use App\Services\SalaryEncrypter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SalaryEncrypter::class, function ($app) {
            $config = $app->make('config')->get('services.salary', []);
            return new SalaryEncrypter(
                $config['encrypt_key'] ?? null,
                $config['cipher'] ?? 'AES-256-CBC'
            );
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Custom Branded Password Reset Email with Black Logo and Dynamic Year
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 15);

            return (new MailMessage)
                ->subject('Password reset request for your BeetleOps HRMS account')
                ->view('emails.reset-password', [
                    'userName'      => $notifiable->name ?? 'there',
                    'resetUrl'      => $resetUrl,
                    'expireMinutes' => $expireMinutes,
                ]);
        });

        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);

        Gate::define('manage-departments', fn (User $user) => $user->isSuperAdmin());

        // Invalidate Hierarchy Cache reactively on any organizational or attendance change
        $clearHierarchy = fn () => HierarchyService::clearCache();

        User::saved($clearHierarchy);
        User::deleted($clearHierarchy);
        User::restored($clearHierarchy);

        Department::saved($clearHierarchy);
        Department::deleted($clearHierarchy);
        Department::restored($clearHierarchy);

        Designation::saved($clearHierarchy);
        Designation::deleted($clearHierarchy);

        LeaveRequest::saved($clearHierarchy);
        LeaveRequest::deleted($clearHierarchy);

        AttendanceRecord::saved($clearHierarchy);
        AttendanceRecord::deleted($clearHierarchy);

        RemoteWorkLog::saved($clearHierarchy);
        RemoteWorkLog::deleted($clearHierarchy);
    }
}
