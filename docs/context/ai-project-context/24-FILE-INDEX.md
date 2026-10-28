# BeetleOps HRMS — Project Knowledge Pack
## 24-FILE-INDEX.md: Index of Important Source Files

---

### 1. File Catalog

| File Path | Primary Purpose | Architectural Module | Importance |
|---|---|---|---|
| [`bootstrap/app.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/bootstrap/app.php) | Application kernel, middleware aliases, CSRF exclusions | Core Infrastructure | **Critical** |
| [`routes/web.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/routes/web.php) | Primary HTTP route definitions & role middleware groups | Routing & Dispatch | **Critical** |
| [`routes/console.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/routes/console.php) | Scheduled cron tasks (notification purge, cycle reminders) | Background Scheduler | **High** |
| [`app/Providers/AppServiceProvider.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Providers/AppServiceProvider.php) | Model lifecycle cache clearing hooks, gates & mail templates | Application Provider | **Critical** |
| [`app/Services/HierarchyService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/HierarchyService.php) | BFS organization tree calculation, boss chains, cycle checks | Organization Structure | **Critical** |
| [`app/Services/LeaveApprovalChainService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/LeaveApprovalChainService.php) | Multi-tier approval routing, manager progression, chain reset | Leave Management | **Critical** |
| [`app/Services/LeaveRequestService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/LeaveRequestService.php) | Leave submission, balance deduction, conflict validations, cancellation | Leave Management | **Critical** |
| [`app/Services/LeaveBalanceService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/LeaveBalanceService.php) | Quota initialization, available days formula, year-end rollover | Leave Management | **Critical** |
| [`app/Services/SlackAlertService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/SlackAlertService.php) | Slack Bot DM dispatcher via REST API (`chat.postMessage`) | Integrations | **High** |
| [`app/Http/Controllers/SlackWebhookController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SlackWebhookController.php) | 2,010-line Slack event webhook receiver & NLP keyword parser | Touchless Attendance | **Critical** |
| [`app/Http/Controllers/SuperAdmin/SessionManagementController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/SessionManagementController.php) | 4,181-line session reconstruction engine, overrides & reports | Touchless Attendance | **Critical** |
| [`app/Http/Controllers/SuperAdmin/SalaryEstimationController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/SalaryEstimationController.php) | Monthly payroll deduction calculations & Excel export | Payroll & Compensation | **Critical** |
| [`app/Http/Controllers/SuperAdmin/SalarySlipController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/SalarySlipController.php) | Headless Chrome PDF generation (Browsershot) & email dispatcher | Payroll & Compensation | **Critical** |
| [`app/Http/Controllers/SuperAdmin/AttendanceHolidayController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/AttendanceHolidayController.php) | Absents, company holidays, remote logs, settings updater | Attendance & Calendar | **High** |
| [`app/Http/Controllers/SuperAdmin/AccountApprovalController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/AccountApprovalController.php) | New user registration review queue & activation | Identity & Access | **High** |
| [`app/Http/Controllers/SuperAdmin/UserController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SuperAdmin/UserController.php) | Administrative user management, manager assignments, salaries | Organization Structure | **High** |
| [`app/Http/Controllers/Employee/LeaveRequestController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/Employee/LeaveRequestController.php) | Employee leave application, personal track record, cancellation | Staff Portal | **High** |
| [`app/Http/Controllers/Admin/TeamLeaveController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/Admin/TeamLeaveController.php) | Line manager team leaves review, directory & subordinate logs | Staff Portal | **High** |
| [`app/Http/Controllers/NotificationController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/NotificationController.php) | Unread count polling, notification drawer, mark read, activity log | Notifications | **High** |
| [`app/Http/Middleware/EnsureUserHasRole.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Middleware/EnsureUserHasRole.php) | Role gate enforcement (`super_admin`, `staff`, `guest`) | Security & RBAC | **Critical** |
| [`app/Http/Middleware/EnsureAccountApproved.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Middleware/EnsureAccountApproved.php) | Gating unapproved accounts from accessing portal features | Security & Access | **Critical** |
| [`app/Policies/LeaveRequestPolicy.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Policies/LeaveRequestPolicy.php) | Authorization checks for leave creation, review & cancellation | Authorization | **High** |
| [`app/Models/User.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/User.php) | Employee entity, manager relationships, recursive subordinate BFS | Domain Entities | **Critical** |
| [`app/Models/SlackMessage.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/SlackMessage.php) | Attendance events, break balance calculations, auto-appends | Domain Entities | **Critical** |
| [`app/Models/LeaveRequest.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/LeaveRequest.php) | Leave request lifecycle state, approval chain tracking | Domain Entities | **Critical** |
| [`app/Models/LeaveBalance.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/LeaveBalance.php) | Yearly quota balances (`entitled`, `used`, `pending`, `carried`) | Domain Entities | **Critical** |
| [`app/Models/AttendanceRecord.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/AttendanceRecord.php) | Unexcused absent markings | Domain Entities | **Normal** |
| [`app/Models/CompanyHoliday.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/CompanyHoliday.php) | Official holidays with department & user targeting | Domain Entities | **Normal** |
| [`app/Models/RemoteWorkLog.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/RemoteWorkLog.php) | Remote work authorization records | Domain Entities | **Normal** |
| [`app/Models/EmployeeSalarySlipDetail.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/EmployeeSalarySlipDetail.php) | Employee bank account, title, account number, IBAN | Domain Entities | **Normal** |
| [`app/Jobs/BreakBalanceAlertJob.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Jobs/BreakBalanceAlertJob.php) | Queued alert fired when break balance exhausts mid-break | Background Queue | **High** |
| [`app/Jobs/BreakBalanceWarningJob.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Jobs/BreakBalanceWarningJob.php) | Queued 10m and 5m break balance warnings | Background Queue | **Normal** |
| [`app/Jobs/BreakGracePeriodAlertJob.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Jobs/BreakGracePeriodAlertJob.php) | Queued alert dispatched to HR when grace period buffer begins | Background Queue | **Normal** |
| [`app/Mail/SalarySlipMail.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Mail/SalarySlipMail.php) | Mailable delivering PDF salary slip with CC recipients | Mail Subsystem | **High** |
| [`app/Console/Commands/SendIncompleteCycleReminders.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Console/Commands/SendIncompleteCycleReminders.php) | Daily 12:00 PM CLI command reminding users about unclosed shifts | CLI Schedulers | **High** |
| [`app/Console/Commands/RecalculateBreakBalance.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Console/Commands/RecalculateBreakBalance.php) | Batch command recalculating historical break exceeded flags | CLI Schedulers | **Normal** |
| [`resources/views/layouts/app.blade.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/resources/views/layouts/app.blade.php) | Master authenticated layout, Spotify theme, searchable selects | Frontend | **High** |
| [`public/js/notifications.js`](file:///Users/adeelbukhari/Documents/Projects/hrms/public/js/notifications.js) | Real-time notification polling & drawer management | Frontend | **High** |
| [`public/js/org-hierarchy.js`](file:///Users/adeelbukhari/Documents/Projects/hrms/public/js/org-hierarchy.js) | Interactive SVG/DOM organization chart renderer | Frontend | **High** |
| [`scratch/verify_account_approval_flow.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/scratch/verify_account_approval_flow.php) | Verification script testing account registration and approval | Testing / Dev | **Normal** |
