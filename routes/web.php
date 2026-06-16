<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TeamLeaveController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\LeaveRequestController as EmployeeLeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\DepartmentController;
use App\Http\Controllers\SuperAdmin\LeaveRequestController as SuperAdminLeaveRequestController;
use App\Http\Controllers\SuperAdmin\LeaveSettingsController;
use App\Http\Controllers\SuperAdmin\LeaveTypeController;
use App\Http\Controllers\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('/webhook/slack', [App\Http\Controllers\SlackWebhookController::class, 'handle']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::middleware('role:employee,admin')->prefix('employee')->name('employee.')->group(function () {
        Route::get('/dashboard', EmployeeDashboardController::class)->name('dashboard');
        Route::get('/leaves/record', [EmployeeLeaveRequestController::class, 'recordView'])->name('leaves.record');
        Route::post('/leave-requests', [EmployeeLeaveRequestController::class, 'store'])->name('leave-requests.store');
        Route::post('/leave-requests/{leaveRequest}/cancel', [EmployeeLeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
        Route::post('/leave-requests/{leaveRequest}/comment', [EmployeeLeaveRequestController::class, 'comment'])->name('leave-requests.comment');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/leaves/apply', [TeamLeaveController::class, 'applyView'])->name('leaves.apply');
        Route::get('/leaves/record', [TeamLeaveController::class, 'recordView'])->name('leaves.record');
        Route::get('/team/directory', [TeamLeaveController::class, 'directoryView'])->name('team.directory');
        Route::get('/team/leaves', [TeamLeaveController::class, 'index'])->name('team.leaves');
        Route::get('/team/leaves/history', [TeamLeaveController::class, 'history'])->name('team.leaves.history');
        Route::post('/leave-requests/{leaveRequest}/approve', [TeamLeaveController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{leaveRequest}/reject', [TeamLeaveController::class, 'reject'])->name('leave-requests.reject');
        Route::post('/leave-requests/{leaveRequest}/comment', [TeamLeaveController::class, 'comment'])->name('leave-requests.comment');
    });

    Route::middleware('role:super_admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/dashboard', SuperAdminDashboardController::class)->name('dashboard');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::get('/users/check-head-replace', [UserController::class, 'checkHeadReplace'])->name('users.check-head-replace');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{userId}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('/departments/{department}/head', [DepartmentController::class, 'assignHead'])->name('departments.assign-head');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
        Route::post('/departments/{departmentId}/restore', [DepartmentController::class, 'restore'])->name('departments.restore');
        Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('/leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::patch('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
        Route::delete('/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');
        Route::post('/leave-types/{leaveType}/toggle-status', [LeaveTypeController::class, 'toggleStatus'])->name('leave-types.toggle-status');
        Route::get('/leave-settings', [LeaveSettingsController::class, 'index'])->name('leave-settings.index');
        Route::post('/leave-settings/apply-all', [LeaveSettingsController::class, 'applyToAll'])->name('leave-settings.apply-all');
        Route::get('/leave-settings/overrides', [LeaveSettingsController::class, 'overrides'])->name('leave-settings.overrides');
        Route::post('/leave-settings/overrides', [LeaveSettingsController::class, 'storeOverride'])->name('leave-settings.overrides.store');
        Route::delete('/leave-settings/overrides/{override}', [LeaveSettingsController::class, 'destroyOverride'])->name('leave-settings.overrides.destroy');
        Route::get('/leave-requests', [SuperAdminLeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('/leave-requests/{leaveRequest}/comment', [SuperAdminLeaveRequestController::class, 'comment'])->name('leave-requests.comment');
        Route::post('/leave-requests/{leaveRequest}/approve', [SuperAdminLeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{leaveRequest}/reject', [SuperAdminLeaveRequestController::class, 'reject'])->name('leave-requests.reject');
        Route::get('/leave-requests/history', [SuperAdminLeaveRequestController::class, 'history'])->name('leave-requests.history');

        Route::get('/session-management', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'index'])->name('session-management.index');
        Route::get('/session-management/overrides', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'overrides'])->name('session-management.overrides');
        Route::get('/session-management/mappings', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'mappings'])->name('session-management.mappings');
        Route::post('/session-management/mappings/save-mapping', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'saveMapping'])->name('session-management.save-mapping');
        Route::post('/session-management/mappings/toggle-auto-connect', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'toggleAutoConnect'])->name('session-management.toggle-auto-connect');
        Route::get('/session-management/track-record', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'trackRecord'])->name('session-management.track-record');
        Route::get('/session-management/track-record/api/report', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'getTrackRecordApi'])->name('session-management.track-record.report');
        Route::get('/session-management/track-record/export', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'exportTrackRecord'])->name('session-management.track-record.export');
        Route::get('/session-management/api/messages', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'getMessagesApi'])->name('session-management.api.messages');
        Route::post('/session-management/messages/{message}/approve', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'approveOverride'])->name('session-management.messages.approve');
        Route::post('/session-management/messages/{message}/reject', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'rejectOverride'])->name('session-management.messages.reject');
        Route::post('/session-management/messages/{message}/reset', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'resetOverride'])->name('session-management.messages.reset');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';


