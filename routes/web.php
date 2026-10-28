<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TeamLeaveController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\LeaveRequestController as EmployeeLeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\DepartmentController;
use App\Http\Controllers\SuperAdmin\DesignationController;
use App\Http\Controllers\SuperAdmin\HierarchyController;
use App\Http\Controllers\SuperAdmin\LeaveRequestController as SuperAdminLeaveRequestController;
use App\Http\Controllers\SuperAdmin\LeaveSettingsController;
use App\Http\Controllers\SuperAdmin\LeaveTypeController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdmin\AttendanceHolidayController;
use App\Http\Controllers\SuperAdmin\SystemSettingController;
use App\Http\Controllers\SuperAdmin\AccountApprovalController;
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
    Route::post('/notifications/{id}/toggle-read', [NotificationController::class, 'toggleRead'])->name('notifications.toggle-read');
    Route::post('/notifications/{id}/toggle-mark', [NotificationController::class, 'toggleMark'])->name('notifications.toggle-mark');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/bulk', [NotificationController::class, 'bulkAction'])->name('notifications.bulk');
    Route::get('/activity-log', [NotificationController::class, 'activityLog'])->name('activity-log');

    // -------------------------------------------------------------------------
    // Staff Portal — accessible to all staff & guest supervisors (role:staff,guest)
    // Replaces the old separate admin + employee portals.
    // Pages related to "team" are hidden in the nav for leaf nodes but the
    // routes still exist so notification links continue to work.
    // -------------------------------------------------------------------------
    Route::middleware('role:staff,guest')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::post('/account/request-activation', [AdminDashboardController::class, 'requestActivation'])->name('account.request-activation');

        Route::middleware('account.approved')->group(function () {
            // Personal leave record & track record (everyone sees their own)
            Route::get('/leaves/record', [EmployeeLeaveRequestController::class, 'recordView'])->name('leaves.record');
            Route::get('/track-record', [EmployeeLeaveRequestController::class, 'trackRecord'])->name('track-record');
            Route::get('/track-record/api', [EmployeeLeaveRequestController::class, 'getTrackRecordApi'])->name('track-record.api');
            Route::get('/track-record/export', [EmployeeLeaveRequestController::class, 'exportTrackRecord'])->name('track-record.export');

            // Organization Chart / Hierarchy (view-only for all staff)
            Route::get('/hierarchy', [\App\Http\Controllers\Admin\HierarchyController::class, 'index'])->name('hierarchy.index');
            Route::get('/hierarchy/data', [\App\Http\Controllers\Admin\HierarchyController::class, 'getTreeData'])->name('hierarchy.data');

            // Submit / cancel / comment on own leave requests
            Route::post('/leave-requests', [EmployeeLeaveRequestController::class, 'store'])->name('leave-requests.store');
            Route::post('/leave-requests/{leaveRequest}/cancel', [EmployeeLeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
            Route::post('/leave-requests/{leaveRequest}/comment', [EmployeeLeaveRequestController::class, 'comment'])->name('leave-requests.comment');
            Route::post('/leave-requests/{leaveRequest}/change-request', [EmployeeLeaveRequestController::class, 'changeRequest'])->name('leave-requests.change-request');

            // Team management (visible in nav only when user has subordinates)
            Route::get('/team/directory', [TeamLeaveController::class, 'directoryView'])->name('team.directory');
            Route::get('/team/directory/{user}/attendance-logs', [TeamLeaveController::class, 'subordinateLogs'])->name('team.directory.logs');
            Route::get('/team/attendance-logs', [TeamLeaveController::class, 'attendanceLogs'])->name('team.attendance-logs');
            Route::get('/team/track-record', [TeamLeaveController::class, 'trackRecord'])->name('team.track-record');
            Route::get('/team/track-record/api', [TeamLeaveController::class, 'getTrackRecordApi'])->name('team.track-record.api');
            Route::get('/team/track-record/export', [TeamLeaveController::class, 'exportTrackRecord'])->name('team.track-record.export');
            Route::get('/team/leaves', [TeamLeaveController::class, 'index'])->name('team.leaves');
            Route::get('/team/leaves/history', [TeamLeaveController::class, 'history'])->name('team.leaves.history');
            Route::post('/leave-requests/{leaveRequest}/approve', [TeamLeaveController::class, 'approve'])->name('leave-requests.approve');
            Route::post('/leave-requests/{leaveRequest}/reject', [TeamLeaveController::class, 'reject'])->name('leave-requests.reject');
            Route::post('/leave-requests/{leaveRequest}/approve-change', [TeamLeaveController::class, 'approveChange'])->name('leave-requests.approve-change');
            Route::post('/leave-requests/{leaveRequest}/reject-change', [TeamLeaveController::class, 'rejectChange'])->name('leave-requests.reject-change');
        });
    });

    // -------------------------------------------------------------------------
    // Super Admin Portal
    // -------------------------------------------------------------------------
    Route::middleware('role:super_admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/dashboard', SuperAdminDashboardController::class)->name('dashboard');

        // Users & Account Approvals
        Route::get('/users/approvals', [AccountApprovalController::class, 'index'])->name('users.approvals');
        Route::post('/users/approvals/{accountApprovalRequest}/approve', [AccountApprovalController::class, 'approve'])->name('users.approvals.approve');
        Route::post('/users/approvals/{accountApprovalRequest}/reject', [AccountApprovalController::class, 'reject'])->name('users.approvals.reject');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{userId}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::post('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
        Route::post('/users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');

        // System Hierarchy / Org Chart
        Route::get('/hierarchy', [HierarchyController::class, 'index'])->name('hierarchy.index');
        Route::get('/hierarchy/data', [HierarchyController::class, 'getTreeData'])->name('hierarchy.data');

        // Designations (inline from user forms)
        Route::post('/designations', [DesignationController::class, 'store'])->name('designations.store');
        Route::delete('/designations/{designation}', [DesignationController::class, 'destroy'])->name('designations.destroy');

        // Departments
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
        Route::post('/departments/{departmentId}/restore', [DepartmentController::class, 'restore'])->name('departments.restore');

        // Leave types
        Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('/leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::patch('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
        Route::delete('/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');
        Route::post('/leave-types/{leaveType}/toggle-status', [LeaveTypeController::class, 'toggleStatus'])->name('leave-types.toggle-status');

        // Leave settings
        Route::get('/leave-settings', [LeaveSettingsController::class, 'index'])->name('leave-settings.index');
        Route::post('/leave-settings/apply-all', [LeaveSettingsController::class, 'applyToAll'])->name('leave-settings.apply-all');
        Route::get('/leave-settings/overrides', [LeaveSettingsController::class, 'overrides'])->name('leave-settings.overrides');
        Route::post('/leave-settings/overrides', [LeaveSettingsController::class, 'storeOverride'])->name('leave-settings.overrides.store');
        Route::delete('/leave-settings/overrides/{override}', [LeaveSettingsController::class, 'destroyOverride'])->name('leave-settings.overrides.destroy');

        // System Settings
        Route::post('/settings/approval-levels', [SystemSettingController::class, 'saveApprovalLevels'])->name('settings.approval-levels');

        // Leave requests
        Route::get('/leave-requests', [SuperAdminLeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('/leave-requests/{leaveRequest}/comment', [SuperAdminLeaveRequestController::class, 'comment'])->name('leave-requests.comment');
        Route::post('/leave-requests/{leaveRequest}/approve', [SuperAdminLeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{leaveRequest}/reject', [SuperAdminLeaveRequestController::class, 'reject'])->name('leave-requests.reject');
        Route::post('/leave-requests/{leaveRequest}/approve-change', [SuperAdminLeaveRequestController::class, 'approveChange'])->name('leave-requests.approve-change');
        Route::post('/leave-requests/{leaveRequest}/reject-change', [SuperAdminLeaveRequestController::class, 'rejectChange'])->name('leave-requests.reject-change');
        Route::post('/leave-requests/{leaveRequest}/approve-cancellation', [SuperAdminLeaveRequestController::class, 'approveCancellation'])->name('leave-requests.approve-cancellation');
        Route::post('/leave-requests/{leaveRequest}/reject-cancellation', [SuperAdminLeaveRequestController::class, 'rejectCancellation'])->name('leave-requests.reject-cancellation');
        Route::get('/leave-requests/history', [SuperAdminLeaveRequestController::class, 'history'])->name('leave-requests.history');

        // Attendance & Holidays
        Route::get('/attendance-holidays', function () {
            return redirect()->route('super-admin.attendance-holidays.absents');
        })->name('attendance-holidays.index');

        Route::get('/attendance-holidays/absents', [AttendanceHolidayController::class, 'absents'])->name('attendance-holidays.absents');
        Route::get('/attendance-holidays/absents/logs', [AttendanceHolidayController::class, 'absentLogs'])->name('attendance-holidays.absents.logs');
        Route::get('/attendance-holidays/holidays', [AttendanceHolidayController::class, 'holidays'])->name('attendance-holidays.holidays');
        Route::post('/attendance-holidays/settings', [AttendanceHolidayController::class, 'updateDeductionRule'])->name('attendance-holidays.settings.update');
        Route::post('/attendance-holidays/break-settings', [AttendanceHolidayController::class, 'updateBreakSettings'])->name('attendance-holidays.break-settings.update');
        Route::post('/attendance-holidays/absents', [AttendanceHolidayController::class, 'storeAbsent'])->name('attendance-holidays.absents.store');
        Route::delete('/attendance-holidays/absents/{record}', [AttendanceHolidayController::class, 'destroyAbsent'])->name('attendance-holidays.absents.destroy');
        Route::post('/attendance-holidays/absents/{record}/convert', [AttendanceHolidayController::class, 'convertAbsent'])->name('attendance-holidays.absents.convert');
        Route::post('/attendance-holidays/holidays', [AttendanceHolidayController::class, 'storeHoliday'])->name('attendance-holidays.holidays.store');
        Route::delete('/attendance-holidays/holidays/{holiday}', [AttendanceHolidayController::class, 'destroyHoliday'])->name('attendance-holidays.holidays.destroy');
        Route::post('/attendance-holidays/remote', [AttendanceHolidayController::class, 'storeRemote'])->name('attendance-holidays.remote.store');
        Route::delete('/attendance-holidays/remote/{log}', [AttendanceHolidayController::class, 'destroyRemote'])->name('attendance-holidays.remote.destroy');
        Route::post('/attendance-holidays/leave', [AttendanceHolidayController::class, 'storeLeave'])->name('attendance-holidays.leave.store');
        Route::delete('/attendance-holidays/leave/{leaveRequest}', [AttendanceHolidayController::class, 'destroyLeave'])->name('attendance-holidays.leave.destroy');
        Route::post('/attendance-holidays/leave/{leaveRequest}/update', [AttendanceHolidayController::class, 'updateAdminMarkedLeave'])->name('attendance-holidays.leave.update');
        Route::post('/attendance-holidays/leave/{leaveRequest}/change-type', [AttendanceHolidayController::class, 'updateAdminMarkedLeave'])->name('attendance-holidays.leave.change-type');

        // Session Management
        Route::get('/session-management', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'index'])->name('session-management.index');
        Route::get('/session-management/overrides', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'overrides'])->name('session-management.overrides');
        Route::get('/session-management/mappings', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'mappings'])->name('session-management.mappings');
        Route::post('/session-management/mappings/save-mapping', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'saveMapping'])->name('session-management.save-mapping');
        Route::post('/session-management/mappings/toggle-auto-connect', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'toggleAutoConnect'])->name('session-management.toggle-auto-connect');
        Route::get('/session-management/track-record', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'trackRecord'])->name('session-management.track-record');
        Route::get('/session-management/track-record/api/report', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'getTrackRecordApi'])->name('session-management.track-record.report');
        Route::get('/session-management/track-record/export', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'exportTrackRecord'])->name('session-management.track-record.export');
        Route::post('/session-management/track-record/save-overrides', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'saveOverrides'])->name('session-management.track-record.save-overrides');
        Route::get('/session-management/track-record/day-raw-events', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'getDayRawEvents'])->name('session-management.track-record.day-raw-events');
        Route::get('/session-management/api/messages', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'getMessagesApi'])->name('session-management.api.messages');
        Route::post('/session-management/messages/{message}/approve', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'approveOverride'])->name('session-management.messages.approve');
        Route::post('/session-management/messages/{message}/reject', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'rejectOverride'])->name('session-management.messages.reject');
        Route::post('/session-management/messages/{message}/reset', [App\Http\Controllers\SuperAdmin\SessionManagementController::class, 'resetOverride'])->name('session-management.messages.reset');

        // Salary & Payroll Management
        Route::get('/salary-estimations', [App\Http\Controllers\SuperAdmin\SalaryEstimationController::class, 'index'])->name('salary.estimations');
        Route::get('/salary-estimations/api/data', [App\Http\Controllers\SuperAdmin\SalaryEstimationController::class, 'getDataApi'])->name('salary.estimations.api');
        Route::post('/salary-estimations/update-salary', [App\Http\Controllers\SuperAdmin\SalaryEstimationController::class, 'updateSalary'])->name('salary.estimations.update-salary');
        Route::post('/salary-estimations/update-commission', [App\Http\Controllers\SuperAdmin\SalaryEstimationController::class, 'updateCommission'])->name('salary.estimations.update-commission');
        Route::get('/salary-estimations/export', [App\Http\Controllers\SuperAdmin\SalaryEstimationController::class, 'exportExcel'])->name('salary.estimations.export');
        Route::get('/salary-slips', [App\Http\Controllers\SuperAdmin\SalarySlipController::class, 'index'])->name('salary-slips.index');
        Route::get('/salary-slips/api/details', [App\Http\Controllers\SuperAdmin\SalarySlipController::class, 'getDetailsApi'])->name('salary-slips.api.details');
        Route::post('/salary-slips/save-bank-details', [App\Http\Controllers\SuperAdmin\SalarySlipController::class, 'saveBankDetails'])->name('salary-slips.save-bank-details');
        Route::post('/salary-slips/save-recipient-email', [App\Http\Controllers\SuperAdmin\SalarySlipController::class, 'saveRecipientEmail'])->name('salary-slips.save-recipient-email');
        Route::post('/salary-slips/upload-letterhead', [App\Http\Controllers\SuperAdmin\SalarySlipController::class, 'uploadLetterhead'])->name('salary-slips.upload-letterhead');
        Route::get('/salary-slips/preview', [App\Http\Controllers\SuperAdmin\SalarySlipController::class, 'previewPdf'])->name('salary-slips.preview');
        Route::post('/salary-slips/send-email', [App\Http\Controllers\SuperAdmin\SalarySlipController::class, 'sendEmail'])->name('salary-slips.send-email');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

