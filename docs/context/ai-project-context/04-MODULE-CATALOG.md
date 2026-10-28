# BeetleOps HRMS — Project Knowledge Pack
## 04-MODULE-CATALOG.md: Detailed Module Catalog

---

### Module 1: Authentication & Account Approval

- **Purpose**: Manages user authentication, account registration, self-service activation requests, and Super Admin review of pending registrations.
- **Location**:
  - Controllers: `app/Http/Controllers/Auth/*`, `app/Http/Controllers/SuperAdmin/AccountApprovalController.php`
  - Middleware: `app/Http/Middleware/EnsureAccountApproved.php`
  - Models: `app/Models/User.php`, `app/Models/AccountApprovalRequest.php`
  - Views: `resources/views/auth/*`, `resources/views/super-admin/users/approvals.blade.php`
- **Exposed APIs & Routes**:
  - `POST /login`, `POST /logout`
  - `POST /register`, `POST /register/guest`
  - `POST /admin/account/request-activation` (`admin.account.request-activation`)
  - `GET /super-admin/users/approvals` (`super-admin.users.approvals`)
  - `POST /super-admin/users/approvals/{accountApprovalRequest}/approve`
  - `POST /super-admin/users/approvals/{accountApprovalRequest}/reject`
- **Database Entities Used**: `users`, `account_approval_requests`, `roles`, `password_reset_tokens`, `sessions`.
- **Security & Authorization**: Gated by `EnsureAccountApproved` middleware. Unapproved users are restricted from viewing organizational hierarchy, submitting leaves, or viewing team records.
- **Notable Details**: Registration defaults `approval_status` to `'pending'`. Super Admins receive `NewUserRegisteredNotification`. Approving a user transitions status to `'approved'` and dispatches `AccountApprovedNotification`.

---

### Module 2: User & Organization Hierarchy

- **Purpose**: Manages company departments, job designations, user profiles, compensation metadata, and the organizational hierarchy tree.
- **Location**:
  - Controllers: `app/Http/Controllers/SuperAdmin/UserController.php`, `DepartmentController.php`, `DesignationController.php`, `HierarchyController.php`, `app/Http/Controllers/Admin/HierarchyController.php`
  - Services: `app/Services/HierarchyService.php`
  - Models: `app/Models/User.php`, `Department.php`, `Designation.php`, `Role.php`
  - Views: `resources/views/super-admin/users/*`, `departments/*`, `hierarchy/*`, `admin/hierarchy/*`
- **Exposed APIs & Routes**:
  - `GET /super-admin/users`, `PATCH /super-admin/users/{user}`, `DELETE /super-admin/users/{user}`
  - `GET /super-admin/hierarchy/data`, `GET /admin/hierarchy/data` (AJAX tree data)
  - `GET /super-admin/departments`, `POST /super-admin/departments`
  - `POST /super-admin/designations`, `DELETE /super-admin/designations/{designation}`
- **Database Entities Used**: `users`, `departments`, `designations`, `roles`.
- **Security & Authorization**: Super Admin restricted (`role:super_admin`) for user and department modifications. View-only tree rendering for staff (`role:staff,guest`).
- **Notable Details**:
  - `HierarchyService::computeOrganizationTree()` builds the full organizational chart using a BFS algorithm.
  - Cycle detection: `HierarchyService::wouldCreateCycle(User $user, int $bossId)` prevents circular manager assignments.
  - Model lifecycle hooks in `AppServiceProvider` clear the tree cache (`org_hierarchy_cache_version`) on user, department, designation, attendance, or leave mutations.

---

### Module 3: Leave Quotas & Entitlements

- **Purpose**: Defines company leave categories, yearly day caps, carry-forward rules, half-day flags, and per-user entitlement overrides.
- **Location**:
  - Controllers: `app/Http/Controllers/SuperAdmin/LeaveTypeController.php`, `LeaveSettingsController.php`
  - Services: `app/Services/LeaveBalanceService.php`
  - Commands: `app/Console/Commands/RolloverLeaveBalances.php`
  - Models: `app/Models/LeaveType.php`, `LeaveSetting.php`, `LeaveBalance.php`, `LeaveBalanceOverride.php`
  - Views: `resources/views/super-admin/leave-types/*`, `leave-settings/*`
- **Exposed APIs & Routes**:
  - `GET /super-admin/leave-types`, `POST /super-admin/leave-types`, `PATCH /super-admin/leave-types/{leaveType}`
  - `POST /super-admin/leave-types/{leaveType}/toggle-status`
  - `GET /super-admin/leave-settings`, `POST /super-admin/leave-settings/apply-all`
  - `POST /super-admin/leave-settings/overrides`, `DELETE /super-admin/leave-settings/overrides/{override}`
- **Database Entities Used**: `leave_types`, `leave_settings`, `leave_balances`, `leave_balance_overrides`.
- **Security & Authorization**: Super Admin only (`role:super_admin`).
- **Notable Details**:
  - `LeaveBalanceService::ensureBalanceRow()` creates initial annual balance rows for users.
  - `Unpaid`, `Emergency`, and `Absent` leave types are exempt from available balance checks.
  - `is_half_day` flag (added in migration `2026_09_15_170000`) applies a `0.5` multiplier to computed working days.

---

### Module 4: Leave Request Lifecycle & Approval Chain

- **Purpose**: End-to-end leave request processing: employee application, overlap validation, multi-level line manager approvals, in-flight change requests, and cancellation workflows.
- **Location**:
  - Controllers: `app/Http/Controllers/Employee/LeaveRequestController.php`, `Admin/TeamLeaveController.php`, `SuperAdmin/LeaveRequestController.php`
  - Services: `app/Services/LeaveRequestService.php`, `LeaveApprovalChainService.php`
  - Models: `app/Models/LeaveRequest.php`, `LeaveApprovalChain.php`, `LeaveRequestComment.php`
  - Views: `resources/views/employee/record.blade.php`, `admin/team/leaves.blade.php`, `super-admin/leave-requests/*`
- **Exposed APIs & Routes**:
  - `POST /admin/leave-requests` (`admin.leave-requests.store`)
  - `POST /admin/leave-requests/{leaveRequest}/cancel`
  - `POST /admin/leave-requests/{leaveRequest}/comment`
  - `POST /admin/leave-requests/{leaveRequest}/change-request`
  - `POST /admin/leave-requests/{leaveRequest}/approve`, `POST /admin/leave-requests/{leaveRequest}/reject`
  - `POST /super-admin/leave-requests/{leaveRequest}/approve`, `reject`, `approve-cancellation`, `reject-cancellation`
- **Database Entities Used**: `leave_requests`, `leave_approval_chains`, `leave_request_comments`, `leave_balances`, `users`.
- **Security & Authorization**: Policy enforced by `LeaveRequestPolicy`. Managers can only approve direct/indirect subordinates (`in_array($request->user_id, $manager->subordinate_ids)`).
- **Notable Details**:
  - Multi-tier escalation dynamically reads target levels from `SystemSetting::approvalLevels()`.
  - In-flight modification resets the approval chain back to Level 1 and logs an automated audit comment.
  - Leaves with `start_date <= today` require Super Admin cancellation approval.

---

### Module 5: Touchless Slack Attendance & Session Management

- **Purpose**: Webhook listener that ingests Slack messages, parses attendance keywords, manages daily break balances, auto-heals unclosed shifts, and provides a retroactive override approval queue.
- **Location**:
  - Controllers: `app/Http/Controllers/SlackWebhookController.php`, `SuperAdmin/SessionManagementController.php`
  - Services: `app/Services/SlackAlertService.php`
  - Models: `app/Models/SlackMessage.php`, `SlackMessageEdit.php`, `SlackUserMapping.php`, `SlackLeaveSyncMessage.php`
  - Jobs: `app/Jobs/BreakBalanceAlertJob.php`, `BreakBalanceWarningJob.php`, `BreakGracePeriodAlertJob.php`
  - Views: `resources/views/super-admin/session-management/*`
- **Exposed APIs & Routes**:
  - `POST /webhook/slack` (Public with HMAC-SHA256 verification)
  - `GET /super-admin/session-management/overrides`
  - `POST /super-admin/session-management/messages/{message}/approve`, `reject`, `reset`
  - `GET /super-admin/session-management/mappings`, `POST /super-admin/session-management/mappings/save-mapping`
  - `GET /super-admin/session-management/track-record/api/report` (AJAX shift details)
- **Database Entities Used**: `slack_messages`, `slack_message_edits`, `slack_user_mappings`, `slack_leave_sync_messages`.
- **External Integrations**: Slack API (`chat.postMessage`), Slack Events API.
- **Security & Authorization**: Webhook verifies `X-Slack-Signature`. Management routes require `role:super_admin`.
- **Notable Details**:
  - Recognizes `signin`, `break`, `back`, `signout`, and retroactive time modifications.
  - Automatically heals forgotten sign-outs across calendar days with 1-minute virtual sign-outs.
  - Real-time queued warning jobs for break balance limits (60m official + 15m grace).

---

### Module 6: Attendance Records, Absents & Company Holidays

- **Purpose**: Administrative declaration of official company holidays, logging unexcused employee absents, recording remote work days, and converting absents into approved leaves.
- **Location**:
  - Controllers: `app/Http/Controllers/SuperAdmin/AttendanceHolidayController.php`
  - Models: `app/Models/AttendanceRecord.php`, `CompanyHoliday.php`, `RemoteWorkLog.php`
  - Views: `resources/views/super-admin/attendance-holidays/*`
- **Exposed APIs & Routes**:
  - `GET /super-admin/attendance-holidays/absents`, `GET /super-admin/attendance-holidays/holidays`
  - `POST /super-admin/attendance-holidays/holidays`, `DELETE /super-admin/attendance-holidays/holidays/{holiday}`
  - `POST /super-admin/attendance-holidays/absents`, `DELETE /super-admin/attendance-holidays/absents/{record}`
  - `POST /super-admin/attendance-holidays/absents/{record}/convert` (Converts absent to leave)
  - `POST /super-admin/attendance-holidays/remote`, `DELETE /super-admin/attendance-holidays/remote/{log}`
  - `POST /super-admin/attendance-holidays/settings` (Updates salary deduction rule in storage JSON)
  - `POST /super-admin/attendance-holidays/break-settings` (Updates break minutes in storage JSON)
- **Database Entities Used**: `attendance_records`, `company_holidays`, `company_holiday_departments`, `company_holiday_users`, `remote_work_logs`.
- **Security & Authorization**: Super Admin only (`role:super_admin`).
- **Notable Details**:
  - Company holidays support granular targeting (all company, specific departments, or specific users).
  - Absence records directly increment the used quota on the `absent` leave type.
  - Converting an absent record to a leave automatically creates an approved leave request and soft-deletes/purges the absent record.

---

### Module 7: Payroll Estimation & Salary Slips

- **Purpose**: Computes monthly employee work hours, evaluates salary deductions based on configurable formulas, tracks sales commissions, and dispatches PDF salary slips via email.
- **Location**:
  - Controllers: `app/Http/Controllers/SuperAdmin/SalaryEstimationController.php`, `SalarySlipController.php`
  - Mail: `app/Mail/SalarySlipMail.php`
  - Models: `app/Models/EmployeeSalarySlipDetail.php`, `User.php`
  - Views: `resources/views/super-admin/salary/estimations.blade.php`, `super-admin/salary-slips/*`, `emails/salary-slip.blade.php`
- **Exposed APIs & Routes**:
  - `GET /super-admin/salary-estimations`, `GET /super-admin/salary-estimations/api/data`
  - `POST /super-admin/salary-estimations/update-salary`, `POST /super-admin/salary-estimations/update-commission`
  - `GET /super-admin/salary-estimations/export` (Excel export via PhpSpreadsheet)
  - `GET /super-admin/salary-slips`, `GET /super-admin/salary-slips/api/details`
  - `POST /super-admin/salary-slips/save-bank-details`, `POST /super-admin/salary-slips/save-recipient-email`
  - `POST /super-admin/salary-slips/upload-letterhead`
  - `GET /super-admin/salary-slips/preview`
  - `POST /super-admin/salary-slips/send-email`
- **Database Entities Used**: `users`, `employee_salary_slip_details`, `slack_messages`, `attendance_records`, `leave_requests`.
- **External Integrations**: `Spatie\Browsershot` (Puppeteer Headless Chrome), `PhpOffice\PhpSpreadsheet`, SMTP Mail.
- **Security & Authorization**: Super Admin only (`role:super_admin`).
- **Notable Details**:
  - Deductions follow either `calendar_days` or `working_days` rules from `storage/app/salary_deduction_settings.json`.
  - Salary slips render identical Blade templates for preview and Headless Chrome PDF generation.

---

### Module 8: Notifications & Activity Log Engine

- **Purpose**: System-wide notification dispatcher, unread count badge provider, notification drawer manager, and audit log.
- **Location**:
  - Controllers: `app/Http/Controllers/NotificationController.php`
  - Notifications: `app/Notifications/*` (24 notification classes)
  - Commands: `routes/console.php` (90-day notification purge task)
  - Views: `resources/views/super-admin/notifications/activity.blade.php`
- **Exposed APIs & Routes**:
  - `GET /notifications/unread-count`
  - `GET /notifications`, `POST /notifications/{id}/read`
  - `POST /notifications/{id}/toggle-read`, `POST /notifications/{id}/toggle-mark`
  - `POST /notifications/read-all`, `POST /notifications/bulk`, `DELETE /notifications/{id}`
  - `GET /activity-log` (`activity-log`)
- **Database Entities Used**: `notifications`.
- **Security & Authorization**: Authenticated users only (`auth`, `verified`). Users can only read/toggle their own notifications.
- **Notable Details**:
  - `mapNotificationUrl()` resolves target routes dynamically based on recipient role and notification context.
  - Daily scheduler purges read, unmarked notifications older than 90 days.

---

### Module 9: Documentation Subsystem

- **Purpose**: Self-hosted public documentation portal serving markdown user manuals with integrated search, table of contents, and chatbot UI.
- **Location**:
  - Route: `routes/web.php` (`Route::get('/docs/{path?}', ...)`)
  - Content & Assets: `doc website/`
- **Exposed APIs & Routes**:
  - `GET /docs/{path?}` (Publicly accessible, no auth required)
- **Security & Authorization**: Path traversal protection enforced via `realpath()` check against `base_path('doc website')`.
