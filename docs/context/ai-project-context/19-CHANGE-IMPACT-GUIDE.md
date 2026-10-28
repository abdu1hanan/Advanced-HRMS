# BeetleOps HRMS — Project Knowledge Pack
## 19-CHANGE-IMPACT-GUIDE.md: Developer Change Impact Guide

---

### 1. Overview

This guide maps common software modification scenarios to their exact affected files, models, services, database tables, and UI templates across the BeetleOps HRMS codebase.

---

### 2. Common Change Scenarios

#### Scenario 1: Adding a New Field to the User Profile (e.g., `national_id_number`)
When extending user metadata:
1. **Database Migration**:
   - Create migration: `database/migrations/YYYY_MM_DD_HHMMSS_add_national_id_to_users_table.php`.
2. **Model Casting & Mass Assignment**:
   - [`app/Models/User.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/User.php): Add field to `#[Fillable([...])]` attribute and define cast in `casts()` method if needed.
3. **Validation & Controller Handling**:
   - [`app/Http/Requests/ProfileUpdateRequest.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Requests/ProfileUpdateRequest.php): Add validation rules for employee self-service edits.
   - [`app/Http/Controllers/SuperAdmin/UserController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/UserController.php): Update `update()` method validation and persistence for administrative edits.
4. **Blade Templates**:
   - [`resources/views/profile/partials/update-profile-information-form.blade.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/resources/views/profile/partials/update-profile-information-form.blade.php): Add input field to employee profile.
   - [`resources/views/super-admin/users/edit.blade.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/resources/views/super-admin/users/edit.blade.php): Add input field to admin user edit view.

---

#### Scenario 2: Adding a New Leave Type (e.g., `Maternity` or `Paternity`)
When introducing a new company leave category:
1. **Database Migration / Seeder**:
   - Create seeder or migration inserting row into `leave_types` with unique `slug`.
2. **Global Default Settings**:
   - Insert corresponding row in `leave_settings` defining `default_entitled`.
3. **Quota Initialization**:
   - [`app/Services/LeaveBalanceService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/LeaveBalanceService.php): Verify `initializeBalancesForUser()` and `ensureBalanceRow()` pick up the new active leave type.
4. **Special Business Rules**:
   - If the leave type does not deduct from balances (like `unpaid`), update `availableDays()` and `leftDays()` in `LeaveBalanceService.php`.
   - If the leave type should be excluded from employee applications (like `absent`), update check in `LeaveRequestService::submit()`.
5. **UI Updates**:
   - Leave selection modals in `resources/views/employee/record.blade.php` automatically load active leave types via Blade `$leaveTypes`.

---

#### Scenario 3: Modifying Daily Break Policies or Grace Periods
When altering break limits (e.g., increasing official allowance from 60m to 90m):
1. **Storage JSON Configuration**:
   - Edit `storage/app/break_settings.json` or submit via Super Admin portal at `/super-admin/attendance-holidays/absents`.
2. **Model Constants & Fallbacks**:
   - [`app/Models/SlackMessage.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/SlackMessage.php): Update `BREAK_BALANCE_SECONDS` (fallback) and check `getBreakBalanceSeconds()`.
3. **Cache Flushing**:
   - Invalidate cache key `'hrms_break_balance_seconds'`.
4. **Historical Recalculation**:
   - Run CLI command: `php artisan breaks:recalculate --days=30` (`app/Console/Commands/RecalculateBreakBalance.php`) to re-evaluate historical break exceeded flags.
5. **Queued Delay Jobs**:
   - [`app/Http/Controllers/SlackWebhookController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SlackWebhookController.php#L770-L805): Verify delayed alert intervals for `BreakBalanceWarningJob` and `BreakGracePeriodAlertJob`.

---

#### Scenario 4: Adding a New Natural Language Slack Keyword (e.g., `lunch`)
When recognizing new presence phrases in Slack:
1. **Regex Normalization**:
   - [`app/Http/Controllers/SlackWebhookController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SlackWebhookController.php#L40-L83): Add regex pattern to `$patterns` in `getNormalizedMessageText()`.
   - Example: `'break' => '/\b(?:break|brb|brbr|lunch)\b/i'`.
2. **Typo Detection**:
   - Update `detectSessionTypo()` to support common misspellings of the new keyword.
3. **Session Reconstruction**:
   - [`app/Http/Controllers/SuperAdmin/SessionManagementController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/SessionManagementController.php): Verify `reconstructSessions()` handles the normalized token as either a session-in or session-out event.

---

#### Scenario 5: Modifying the Leave Approval Chain Escalation Behavior
When changing how leave approvals progress:
1. **Tiers & Levels**:
   - Update `SystemSetting::approvalLevels()` or use Super Admin UI on `/super-admin/settings/approval-levels`.
2. **Progression Logic**:
   - [`app/Services/LeaveApprovalChainService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/LeaveApprovalChainService.php):
     - `getNextApprover()`: Controls who receives the next action notification.
     - `recordApproval()`: Controls when status flips to `approved_by_manager`.
     - `resetChain()`: Controls reset behavior when an employee submits an in-flight change request.
3. **Notification Routing**:
   - Check `app/Notifications/LeaveRequestChainApprovalRequired.php` and `LeaveRequestManagerApproved.php`.

---

#### Scenario 6: Changing Salary Deduction Formulas
When switching deduction calculations between calendar days and working days:
1. **Deduction Settings**:
   - Update `storage/app/salary_deduction_settings.json` via Super Admin Attendance Settings form.
2. **Payroll Aggregator**:
   - [`app/Http/Controllers/SuperAdmin/SalaryEstimationController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/SalaryEstimationController.php):
     - Inspect `computeCompanyPayroll()`.
     - Daily rate formula:
       - `calendar_days`: `$grossSalary / $totalDaysInMonth`.
       - `working_days`: `$grossSalary / $workingDaysInMonth`.
3. **Salary Slip Alignment**:
   - [`app/Http/Controllers/SuperAdmin/SalarySlipController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/SalarySlipController.php):
     - Inspect `calculateSalarySlipData()`. Ensure deduction matches estimations sheet.
   - Blade Template: `resources/views/super-admin/salary-slips/preview-page.blade.php`.

---

#### Scenario 7: Creating a New In-App Notification Event
When dispatching a new system event:
1. **Notification Class**:
   - Create class under `app/Notifications/NewEventNotification.php` extending `Illuminate\Notifications\Notification`.
   - Implement `via()` returning `['database']`.
   - Implement `toArray()` returning `['type' => 'new_event', 'message' => '...', 'url' => '...']`.
2. **URL Routing Mapper**:
   - [`app/Http/Controllers/NotificationController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/NotificationController.php): Add mapping rule in `mapNotificationUrl()` to direct clicks to the appropriate portal route.
3. **Client-Side Polling**:
   - Automatically handled by `public/js/notifications.js`.
