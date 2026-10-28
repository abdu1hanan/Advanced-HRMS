# BeetleOps HRMS — Project Knowledge Pack
## 15-IMPORTANT-FLOWS.md: Detailed End-to-End System Workflows

---

### Flow 1: User Self-Registration & Account Activation

1. **Trigger**: New employee registers on the HRMS portal via web browser.
2. **Entry Point**: `POST /register` or `POST /register/guest`
3. **Authentication**: Public / Guest route (`middleware('guest')`).
4. **Authorization**: None required for submission.
5. **Validation**: `RegisteredUserController@store` validates `name`, `email` (unique), `password`, `department_id`, `phone`, `emergency_number`.
6. **Controller**: `App\Http\Controllers\Auth\RegisteredUserController@store`
7. **Services Involved**: None directly.
8. **Database Operations**:
   - `User::create([... 'approval_status' => 'pending'])`
   - `AccountApprovalRequest::create(['user_id' => $user->id, 'status' => 'pending'])`
9. **Side Effects**:
   - Logs user into session.
   - Dispatches `NewUserRegisteredNotification` to all Super Admins.
   - User is intercepted on subsequent page loads by `EnsureAccountApproved` middleware.
10. **External Integrations**: None.
11. **Returned Result**: Redirects to `dashboard` -> intercepted and redirected to `admin.dashboard` with an approval warning banner.
12. **Important Files**:
    - `app/Http/Controllers/Auth/RegisteredUserController.php`
    - `app/Http/Middleware/EnsureAccountApproved.php`
    - `app/Http/Controllers/SuperAdmin/AccountApprovalController.php`

---

### Flow 2: Slack Touchless Check-In (`signin`) Ingestion

1. **Trigger**: Employee posts `"signin"` in the designated Slack attendance channel.
2. **Entry Point**: `POST /webhook/slack`
3. **Authentication**: Cryptographic signature validation (`X-Slack-Signature` and `X-Slack-Request-Timestamp`).
4. **Authorization**: None (Public webhook).
5. **Validation**: `SlackWebhookController::getNormalizedMessageText()` matches regex `/\b(?:sign(?:ed|ing)?\s*[-_]?\s*in|signin)\b/i`.
6. **Controller**: `App\Http\Controllers\SlackWebhookController@handle`
7. **Services Involved**: `SlackAlertService` (if anomaly or auto-heal detected).
8. **Database Operations**:
   - Checks `SlackMessage::where('event_id', $eventId)->exists()` for deduplication.
   - `SlackMessage::create(['slack_user_id' => $uid, 'message_text' => 'sign in', 'slack_ts' => $ts, 'message_at' => Carbon::createFromTimestamp($ts)])`.
   - `SlackMessage::ensureDayAutoAppends($slackUserId, $dateStr, true)` checks if previous day shift was left open.
9. **Side Effects**:
   - If user left previous day unclosed, generates virtual sign-out (`is_auto_appended = true`) and sends Slack DM warning.
   - Bumps break balance cache for user and date.
10. **External Integrations**: Slack Events API.
11. **Returned Result**: `{"status": "success"}` (HTTP 200).
12. **Important Files**:
    - `app/Http/Controllers/SlackWebhookController.php`
    - `app/Models/SlackMessage.php`

---

### Flow 3: Retroactive Slack Session Override & Super Admin Approval

1. **Trigger**: Employee sends `"signin at 10:00 am"` on Slack; later, Super Admin approves in web portal.
2. **Entry Point**: Ingestion: `POST /webhook/slack`; Approval: `POST /super-admin/session-management/messages/{id}/approve`
3. **Authentication**: Webhook: Slack HMAC signature; Portal: `auth:web`.
4. **Authorization**: Portal requires `role:super_admin`.
5. **Validation**: Regex extracts `hour`, `minute`, and `am/pm`; verifies AM/PM boundaries ($1 \le hour \le 12$).
6. **Controller**: `SlackWebhookController@handle` -> `SessionManagementController@approveOverride`
7. **Services Involved**: `SessionManagementController`.
8. **Database Operations**:
   - Ingestion: Updates `slack_messages` with `approval_status = 'pending'`, `override_at = $targetUtc`, `original_message_at = $message_at`.
   - Ingestion: Dispatches `SlackOverrideAwaitingApproval` to all Super Admins.
   - Approval: Updates `slack_messages` setting `approval_status = 'approved'`.
   - Approval: Re-runs `ensureDayAutoAppends()` and `recalculateBreakExceeded()` for affected dates.
9. **Side Effects**:
   - Dispatches `SessionOverrideDecisionNotification` to the employee's in-app notification drawer.
   - Shifts calculated in monthly Track Record update dynamically.
10. **External Integrations**: Slack API.
11. **Returned Result**: JSON `{"status": "success", "message": "Override approved successfully."}`.
12. **Important Files**:
    - `app/Http/Controllers/SlackWebhookController.php::processOverrideApproval()`
    - `app/Http/Controllers/SuperAdmin/SessionManagementController.php::approveOverride()`

---

### Flow 4: Real-Time Break Tracking & Balance Exhaustion Alert

1. **Trigger**: Employee posts `"break"` in Slack.
2. **Entry Point**: `POST /webhook/slack`
3. **Authentication**: Slack HMAC signature.
4. **Authorization**: None.
5. **Validation**: Keyword matched via `/\b(?:break|brb|brbr)\b/i`.
6. **Controller**: `SlackWebhookController@handle`
7. **Services Involved**: `SlackAlertService`, `Queue`.
8. **Database Operations**:
   - `SlackMessage::create(['message_text' => 'break', ...])`
   - `SlackMessage::computeDayBreakBalance()` evaluates current accumulated break seconds today.
9. **Side Effects**:
   - If balance already 0: sends instant Slack DM and logs `BreakExceededNotification`.
   - If balance > 0:
     - Dispatches `BreakGracePeriodAlertJob` delayed by remaining official seconds.
     - Dispatches `BreakBalanceWarningJob` delayed by ($remaining - 600s) for 10m warning.
     - Dispatches `BreakBalanceWarningJob` delayed by ($remaining - 300s) for 5m warning.
     - Dispatches `BreakBalanceAlertJob` delayed by exact remaining allowance (exhaustion).
10. **External Integrations**: Slack Events API, Slack Bot REST API (`chat.postMessage`).
11. **Returned Result**: `{"status": "success"}`.
12. **Important Files**:
    - `app/Http/Controllers/SlackWebhookController.php::checkRealTimeSlackAlerts()`
    - `app/Models/SlackMessage.php::computeDayBreakBalance()`
    - `app/Jobs/BreakBalanceAlertJob.php`

---

### Flow 5: Forgotten Sign-Out Auto-Healing & 12:00 PM Reminder

1. **Trigger**: Daily cron triggers at 12:00 PM `Asia/Karachi`.
2. **Entry Point**: CLI `php artisan attendance:incomplete-reminders` (`routes/console.php`).
3. **Authentication**: CLI / System process.
4. **Authorization**: None (Cron).
5. **Validation**: Checks if yesterday was weekend, company holiday, approved leave, or has pending override.
6. **Controller / Command**: `App\Console\Commands\SendIncompleteCycleReminders`
7. **Services Involved**: `SlackAlertService`, `SessionManagementController`.
8. **Database Operations**:
   - Queries `slack_messages` for all active employees for yesterday's UTC window + 24h forward lookahead.
   - Calls `SessionManagementController::reconstructSessions()`.
   - Identifies any session where `out === null` or virtual signout was auto-appended.
9. **Side Effects**:
   - Sends direct Slack message to employee warning them about missing signout.
   - Dispatches in-app `ForgotToSignOutNotification`.
10. **External Integrations**: Slack Bot REST API (`chat.postMessage`).
11. **Returned Result**: Command exits with `0` (Success).
12. **Important Files**:
    - `app/Console/Commands/SendIncompleteCycleReminders.php`
    - `routes/console.php`

---

### Flow 6: Leave Request Submission & Pre-Validation

1. **Trigger**: Employee submits leave form via modal on Staff Portal.
2. **Entry Point**: `POST /admin/leave-requests`
3. **Authentication**: `auth:web` (`role:staff,guest` + `account.approved`).
4. **Authorization**: `LeaveRequestPolicy::create()` verifies user is not `guest`.
5. **Validation**: Validates `leave_type_id`, `start_date`, `end_date`, `reason`. `LeaveRequestService::calculateDays()` verifies non-weekend days.
6. **Controller**: `App\Http\Controllers\Employee\LeaveRequestController@store`
7. **Services Involved**: `LeaveRequestService`, `LeaveBalanceService`, `LeaveApprovalChainService`.
8. **Database Operations**:
   - Inside `DB::transaction`:
     - Checks available days quota: `days > availableDays()` throws `ValidationException`.
     - Conflict assertions: verifies no overlapping leaves, holidays, absents, or remotes.
     - `leave_balances->increment('pending', $days)`.
     - `LeaveRequest::create([... 'status' => 'pending'])`.
     - `LeaveApprovalChainService::initialise()` snapshots `approval_chain_target`.
9. **Side Effects**:
   - Dispatches `LeaveRequestSubmitted` to Level 1 line manager and Super Admins.
10. **External Integrations**: None.
11. **Returned Result**: JSON `{"status": "success", "message": "Leave request submitted successfully."}`.
12. **Important Files**:
    - `app/Services/LeaveRequestService.php::submit()`
    - `app/Http/Controllers/Employee/LeaveRequestController.php`

---

### Flow 7: Multi-Tier Leave Approval Chain Progression

1. **Trigger**: Line Manager clicks "Approve" on subordinate leave card.
2. **Entry Point**: `POST /admin/leave-requests/{leaveRequest}/approve`
3. **Authentication**: `auth:web` (`role:staff,guest`).
4. **Authorization**: `LeaveApprovalChainService::canApprove($request, $manager)` verifies manager is next expected approver in boss chain.
5. **Validation**: Verifies status is `pending` or `rejected_by_manager`.
6. **Controller**: `App\Http\Controllers\Admin\TeamLeaveController@approve`
7. **Services Involved**: `LeaveApprovalChainService`, `HierarchyService`.
8. **Database Operations**:
   - `LeaveApprovalChain::updateOrCreate([... 'approver_id' => $mgr->id], ['level' => $level, 'action' => 'approved'])`.
   - Advances `approval_chain_progress` to `$level`.
   - If progress reaches `approval_chain_target` or bosses exhausted: sets status to `'approved_by_manager'`.
9. **Side Effects**:
   - If more levels exist: dispatches `LeaveRequestChainApprovalRequired` to Level N+1 boss.
   - If complete: dispatches `LeaveRequestAdminApprovedToManager` to Super Admins for final sign-off.
10. **External Integrations**: None.
11. **Returned Result**: JSON `{"status": "success", "message": "Leave request approved successfully."}`.
12. **Important Files**:
    - `app/Services/LeaveApprovalChainService.php::recordApproval()`
    - `app/Http/Controllers/Admin/TeamLeaveController.php`

---

### Flow 8: In-Flight Leave Change Request & Chain Reset

1. **Trigger**: Employee modifies dates on an in-flight or approved leave request.
2. **Entry Point**: `POST /admin/leave-requests/{leaveRequest}/change-request`
3. **Authentication**: `auth:web` (`role:staff,guest` + `account.approved`).
4. **Authorization**: Requester must be applicant (`$request->user_id === $user->id`).
5. **Validation**: Verifies changes were detected; re-validates new dates, weekends, conflicts, and balances.
6. **Controller**: `App\Http\Controllers\Employee\LeaveRequestController@changeRequest`
7. **Services Involved**: `LeaveRequestService`, `LeaveApprovalChainService`, `LeaveBalanceService`.
8. **Database Operations**:
   - Snapshots original parameters into `change_request['original']`.
   - Refunds old quota balance, deducts new quota into `pending`.
   - Updates `leave_requests` with new parameters, `change_request_status = 'pending'`.
   - Calls `LeaveApprovalChainService::resetChain()`: deletes existing `leave_approval_chains` records and resets `approval_chain_progress = 0`.
   - Injects automated system audit comment into `leave_request_comments`.
9. **Side Effects**:
   - Dispatches `LeaveRequestChangeRequested` to Level 1 line manager (actionable) and Super Admins.
10. **External Integrations**: None.
11. **Returned Result**: JSON `{"status": "success", "message": "Change request submitted successfully."}`.
12. **Important Files**:
    - `app/Services/LeaveRequestService.php::requestChange()`
    - `app/Services/LeaveApprovalChainService.php::resetChain()`

---

### Flow 9: Active / Past Leave Cancellation Approval

1. **Trigger**: Employee requests cancellation of an active or past leave (`start_date <= today`).
2. **Entry Point**: Submission: `POST /admin/leave-requests/{id}/cancel`; Approval: `POST /super-admin/leave-requests/{id}/approve-cancellation`
3. **Authentication**: `auth:web`.
4. **Authorization**: Requester for cancel; `role:super_admin` for approval.
5. **Validation**: Reason is mandatory.
6. **Controller**: `Employee\LeaveRequestController@cancel` -> `SuperAdmin\LeaveRequestController@approveCancellation`
7. **Services Involved**: `LeaveRequestService`, `LeaveBalanceService`.
8. **Database Operations**:
   - Submission: flags `change_request['request_cancellation'] = true`, `change_request_status = 'pending'`.
   - Approval:
     - `leave_balances->decrement('used', $days)` (refunds quota).
     - `leave_requests->update(['status' => 'cancelled', 'change_request_status' => 'approved', 'reviewed_by' => $admin->id])`.
9. **Side Effects**:
   - Dispatches `LeaveRequestChangeProcessed` to employee.
   - Dispatches `LeaveRequestCancelled` to all chain line managers.
10. **External Integrations**: None.
11. **Returned Result**: JSON `{"status": "success", "message": "Leave cancellation approved."}`.
12. **Important Files**:
    - `app/Services/LeaveRequestService.php::cancel()`
    - `app/Services/LeaveRequestService.php::approveCancellationRequest()`

---

### Flow 10: Absent Marking & Conversion to Approved Leave

1. **Trigger**: HR marks an employee absent; later, decides to convert it to an approved Casual Leave.
2. **Entry Point**: Mark: `POST /super-admin/attendance-holidays/absents`; Convert: `POST /super-admin/attendance-holidays/absents/{id}/convert`
3. **Authentication**: `auth:web` (`role:super_admin`).
4. **Authorization**: Super Admin only.
5. **Validation**: Validates `user_id`, `date`, `leave_type_id`.
6. **Controller**: `App\Http\Controllers\SuperAdmin\AttendanceHolidayController`
7. **Services Involved**: `LeaveBalanceService`, `LeaveRequestService`.
8. **Database Operations**:
   - Marking: `AttendanceRecord::create(['user_id' => $uid, 'date' => $date])`; increments `used` on `absent` leave balance.
   - Conversion:
     - Inside `DB::transaction`:
       - Deletes `AttendanceRecord`.
       - Decrements `used` on `absent` leave balance.
       - Creates approved `LeaveRequest` (`is_admin_marked = true`, `status = 'approved'`).
       - Increments `used` on the newly selected `LeaveType` balance.
9. **Side Effects**:
   - Dispatches `AbsentDeletedNotification` and `LeaveGrantedNotification` to employee.
10. **External Integrations**: None.
11. **Returned Result**: JSON `{"status": "success", "message": "Absent record converted to leave successfully."}`.
12. **Important Files**:
    - `app/Http/Controllers/SuperAdmin/AttendanceHolidayController.php::convertAbsent()`

---

### Flow 11: Company Holiday Declaration & Granular Targeting

1. **Trigger**: Super Admin declares a national or company holiday.
2. **Entry Point**: `POST /super-admin/attendance-holidays/holidays`
3. **Authentication**: `auth:web` (`role:super_admin`).
4. **Authorization**: Super Admin only.
5. **Validation**: Validates `name`, `date` (unique), `target_type` (`all`, `departments`, `users`).
6. **Controller**: `App\Http\Controllers\SuperAdmin\AttendanceHolidayController@storeHoliday`
7. **Services Involved**: None.
8. **Database Operations**:
   - `CompanyHoliday::create(['name' => $name, 'date' => $date, 'target_type' => $type, ...])`.
   - If targeting departments: syncs `company_holiday_departments`.
   - If targeting users: syncs `company_holiday_users`.
9. **Side Effects**:
   - Dispatches `HolidayDeclaredNotification` to all targeted employees.
   - Reconstructed sessions on that date automatically exclude expected work hours.
10. **External Integrations**: None.
11. **Returned Result**: Redirects back with success message.
12. **Important Files**:
    - `app/Http/Controllers/SuperAdmin/AttendanceHolidayController.php::storeHoliday()`
    - `app/Models/CompanyHoliday.php::appliesToUser()`

---

### Flow 12: Remote Work Day Logging (Full vs Half-Day)

1. **Trigger**: Super Admin logs a remote work approval for an employee.
2. **Entry Point**: `POST /super-admin/attendance-holidays/remote`
3. **Authentication**: `auth:web` (`role:super_admin`).
4. **Authorization**: Super Admin only.
5. **Validation**: Validates `user_id`, `date`, `notes` (`Full Day` or `Half Day`).
6. **Controller**: `App\Http\Controllers\SuperAdmin\AttendanceHolidayController@storeRemote`
7. **Services Involved**: None.
8. **Database Operations**:
   - `RemoteWorkLog::create(['user_id' => $uid, 'date' => $date, 'notes' => $notes, 'marked_by' => auth()->id()])`.
9. **Side Effects**:
   - Dispatches `RemoteMarkedNotification` to employee.
   - Clears hierarchy cache.
   - Adjusts remote indicators on track record and monthly payroll sheets.
10. **External Integrations**: None.
11. **Returned Result**: Redirects back with success flash message.
12. **Important Files**:
    - `app/Http/Controllers/SuperAdmin/AttendanceHolidayController.php::storeRemote()`
    - `app/Models/RemoteWorkLog.php`

---

### Flow 13: Monthly Shift Reconstruction & Track Record Report

1. **Trigger**: Super Admin or employee opens monthly Track Record page.
2. **Entry Point**: `GET /super-admin/session-management/track-record/api/report` or `GET /admin/track-record/api`
3. **Authentication**: `auth:web`.
4. **Authorization**: Super Admin can view any; Staff can only view self or direct reports.
5. **Validation**: Validates `user_id`, `month`, `year`.
6. **Controller**: `App\Http\Controllers\SuperAdmin\SessionManagementController@getTrackRecordApi`
7. **Services Involved**: `SessionManagementController`.
8. **Database Operations**:
   - Queries `slack_messages` for the user for the entire month with 24h forward lookahead.
   - Queries `leave_requests`, `company_holidays`, `attendance_records`, and `remote_work_logs`.
   - Iterates every calendar day of the month; reconstructs shift segments, break deductions, and expected vs logged hours.
9. **Side Effects**: None (Read-only).
10. **External Integrations**: None.
11. **Returned Result**: JSON containing daily records array, summary totals (expected hours, actual hours, difference, total leaves, total absents).
12. **Important Files**:
    - `app/Http/Controllers/SuperAdmin/SessionManagementController.php::calculateMonthlyTrackRecordData()`

---

### Flow 14: Monthly Salary Estimation & Deduction Calculation

1. **Trigger**: Super Admin views Salary Estimation overview or toggles month/year dropdown.
2. **Entry Point**: `GET /super-admin/salary-estimations/api/data`
3. **Authentication**: `auth:web` (`role:super_admin`).
4. **Authorization**: Super Admin only.
5. **Validation**: Validates `month`, `year`, `salary_status`.
6. **Controller**: `App\Http\Controllers\SuperAdmin\SalaryEstimationController@getDataApi`
7. **Services Involved**: `SessionManagementController`.
8. **Database Operations**:
   - Fetches all non-super-admin users.
   - For each employee, queries track record metrics via `SessionManagementController::calculateMonthlyTrackRecordData()`.
   - Reads `storage/app/salary_deduction_settings.json`.
   - Computes daily deduction rate, deductible days (absents + unpaid leaves + days prior to joining date), gross pay, sales commission, and net take away.
9. **Side Effects**: None (Read-only calculation).
10. **External Integrations**: None.
11. **Returned Result**: JSON with employee payroll rows and company aggregate totals.
12. **Important Files**:
    - `app/Http/Controllers/SuperAdmin/SalaryEstimationController.php::computeCompanyPayroll()`

---

### Flow 15: Headless Chrome Salary Slip PDF Generation & Email Dispatch

1. **Trigger**: Super Admin clicks "Send Email" in the Salary Slip modal.
2. **Entry Point**: `POST /super-admin/salary-slips/send-email`
3. **Authentication**: `auth:web` (`role:super_admin`).
4. **Authorization**: Super Admin only.
5. **Validation**: Validates `user_id`, `month`, `year`, `sales_commission`, bank details completeness.
6. **Controller**: `App\Http\Controllers\SuperAdmin\SalarySlipController@sendEmail`
7. **Services Involved**: `Spatie\Browsershot`, `Mail`.
8. **Database Operations**:
   - Queries `users`, `employee_salary_slip_details`, and track record metrics.
   - Verifies `user->expected_salary_amount` is configured.
9. **Side Effects**:
   - Renders `super-admin.salary-slips.preview-page` Blade view to HTML.
   - Spawns Headless Chrome via `Browsershot::html($html)->noSandbox()->format('A4')->pdf()` to create binary PDF.
   - Dispatches `SalarySlipMail` with attached PDF via SMTP to `user->salary_slip_email ?: user->email` and CCs `config('services.salary_slip.cc_emails')`.
   - Dispatches in-app `SalarySlipIssuedNotification` to employee.
10. **External Integrations**: Puppeteer / Headless Chrome, SMTP Mail Server.
11. **Returned Result**: JSON `{"status": "success", "message": "Salary slip email dispatched successfully..."}`.
12. **Important Files**:
    - `app/Http/Controllers/SuperAdmin/SalarySlipController.php::sendEmail()`
    - `app/Mail/SalarySlipMail.php`
    - `resources/views/super-admin/salary-slips/preview-page.blade.php`
