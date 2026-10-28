# BeetleOps HRMS — Project Knowledge Pack
## 07-API-CATALOG.md: Complete API Surface & Endpoint Inventory

---

### 1. Overview of API Surfaces

BeetleOps HRMS exposes two primary categories of endpoints:
1. **Server-Rendered Web Routes with AJAX APIs**: Traditional Laravel Blade routes supplemented with specialized JSON APIs for asynchronous modal submissions, real-time polling, and tabular report hydration.
2. **Slack Ingestion Webhook**: A dedicated public callback endpoint accepting real-time Slack Event API payloads verified via HMAC signatures.

---

### 2. External Webhook API

| Method | URI | Controller Action | Middleware | Description |
|---|---|---|---|---|
| `POST` | `/webhook/slack` | `SlackWebhookController@handle` | None (CSRF exempt) | Ingests Slack message events, challenge verification, edits, and deletions. |

- **Security**: Verifies `X-Slack-Signature` and `X-Slack-Request-Timestamp` against `config('services.slack.signing_secret')` with a 300-second replay attack threshold.
- **Input Payload**: Slack Event Callback JSON (`url_verification` challenge or `event_callback` containing message details).
- **Responses**:
  - Challenge: `{"challenge": "string"}` (200 OK)
  - Handled: `{"status": "success" | "edit_recorded" | "message_deleted_processed" | ...}` (200 OK)
  - Unauthorized: `{"error": "Unauthorized signature."}` (401 Unauthorized)

---

### 3. Real-Time Notification & Activity APIs

All routes require `['auth', 'verified']`.

| Method | URI | Action | Input | Response |
|---|---|---|---|---|
| `GET` | `/notifications/unread-count` | `NotificationController@unreadCount` | None | `{"count": int}` |
| `GET` | `/notifications` | `NotificationController@index` | None | `{"notifications": [...]}` |
| `POST` | `/notifications/{id}/read` | `NotificationController@markRead` | Route param `{id}` | `{"status": "success"}` |
| `POST` | `/notifications/{id}/toggle-read` | `NotificationController@toggleRead` | Route param `{id}` | `{"status": "success", "read_at": ...}` |
| `POST` | `/notifications/{id}/toggle-mark` | `NotificationController@toggleMark` | Route param `{id}` | `{"status": "success", "is_marked": bool}` |
| `DELETE`| `/notifications/{id}` | `NotificationController@destroy` | Route param `{id}` | `{"status": "success"}` |
| `POST` | `/notifications/read-all` | `NotificationController@markAllRead` | None | `{"status": "success"}` |
| `POST` | `/notifications/bulk` | `NotificationController@bulkAction` | `action`, `ids[]` | `{"status": "success", "affected": int}` |
| `GET` | `/activity-log` | `NotificationController@activityLog` | Query filters | HTML View: Activity log table |

---

### 4. Staff & Line Manager Portal (`/admin/*`)

Enforced by `middleware(['auth', 'verified', 'role:staff,guest'])`. Routes within `account.approved` require active account approval.

| Method | URI | Controller Action | Gated By | Description |
|---|---|---|---|---|
| `GET` | `/admin/dashboard` | `Admin\DashboardController` | Role | Staff dashboard overview & leave stats. |
| `POST`| `/admin/account/request-activation` | `Admin\DashboardController@requestActivation` | Role | Re-requests account activation from Super Admin. |
| `GET` | `/admin/leaves/record` | `Employee\LeaveRequestController@recordView` | `account.approved` | Personal leave history & remaining quotas. |
| `GET` | `/admin/track-record` | `Employee\LeaveRequestController@trackRecord` | `account.approved` | Personal work hours & daily attendance log. |
| `GET` | `/admin/track-record/api` | `Employee\LeaveRequestController@getTrackRecordApi`| `account.approved` | JSON API: Personal reconstructed sessions for month/year. |
| `GET` | `/admin/track-record/export` | `Employee\LeaveRequestController@exportTrackRecord` | `account.approved` | Excel export of personal monthly track record. |
| `GET` | `/admin/hierarchy` | `Admin\HierarchyController@index` | `account.approved` | Interactive organization chart view. |
| `GET` | `/admin/hierarchy/data` | `Admin\HierarchyController@getTreeData` | `account.approved` | JSON API: Computed organization hierarchy tree. |
| `POST`| `/admin/leave-requests` | `Employee\LeaveRequestController@store` | `account.approved` | Submits new leave application. |
| `POST`| `/admin/leave-requests/{id}/cancel` | `Employee\LeaveRequestController@cancel` | `account.approved` | Cancels own future or active leave. |
| `POST`| `/admin/leave-requests/{id}/comment` | `Employee\LeaveRequestController@comment` | `account.approved` | Posts comment to leave request discussion thread. |
| `POST`| `/admin/leave-requests/{id}/change-request` | `Employee\LeaveRequestController@changeRequest` | `account.approved` | Modifies parameters of in-flight leave (resets chain). |
| `GET` | `/admin/team/directory` | `Admin\TeamLeaveController@directoryView` | `account.approved` | Subordinates directory (visible if user has reports). |
| `GET` | `/admin/team/directory/{user}/attendance-logs` | `Admin\TeamLeaveController@subordinateLogs` | `account.approved` | Raw attendance logs for a specific direct report. |
| `GET` | `/admin/team/attendance-logs` | `Admin\TeamLeaveController@attendanceLogs` | `account.approved` | Team-wide attendance presence view. |
| `GET` | `/admin/team/track-record` | `Admin\TeamLeaveController@trackRecord` | `account.approved` | Team track record overview. |
| `GET` | `/admin/team/track-record/api` | `Admin\TeamLeaveController@getTrackRecordApi` | `account.approved` | JSON API: Monthly track record data for team member. |
| `GET` | `/admin/team/track-record/export` | `Admin\TeamLeaveController@exportTrackRecord` | `account.approved` | Excel export for subordinate track record. |
| `GET` | `/admin/team/leaves` | `Admin\TeamLeaveController@index` | `account.approved` | Pending subordinate leave requests requiring review. |
| `GET` | `/admin/team/leaves/history` | `Admin\TeamLeaveController@history` | `account.approved` | Historical subordinate leave decisions. |
| `POST`| `/admin/leave-requests/{id}/approve` | `Admin\TeamLeaveController@approve` | `account.approved` | Line manager records Level N approval. |
| `POST`| `/admin/leave-requests/{id}/reject` | `Admin\TeamLeaveController@reject` | `account.approved` | Line manager records Level N rejection. |

---

### 5. Super Admin Portal (`/super-admin/*`)

Enforced by `middleware(['auth', 'verified', 'role:super_admin'])`.

#### A. Users & Account Approvals
| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/super-admin/dashboard` | `SuperAdmin\DashboardController` | Executive metrics overview. |
| `GET` | `/super-admin/users/approvals` | `AccountApprovalController@index` | Pending user registrations review queue. |
| `POST`| `/super-admin/users/approvals/{id}/approve` | `AccountApprovalController@approve` | Activates pending employee account. |
| `POST`| `/super-admin/users/approvals/{id}/reject` | `AccountApprovalController@reject` | Rejects pending employee account. |
| `GET` | `/super-admin/users` | `UserController@index` | Company user directory with filters. |
| `GET` | `/super-admin/users/{user}/edit` | `UserController@edit` | Edit user details, boss assignment, salary. |
| `PATCH`| `/super-admin/users/{user}` | `UserController@update` | Save user profile, designation, manager. |
| `DELETE`| `/super-admin/users/{user}` | `UserController@destroy` | Soft-delete user account. |
| `POST`| `/super-admin/users/{id}/restore` | `UserController@restore` | Restore soft-deleted user. |

#### B. Hierarchy, Departments & Designations
| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/super-admin/hierarchy` | `HierarchyController@index` | Master org chart page. |
| `GET` | `/super-admin/hierarchy/data` | `HierarchyController@getTreeData` | JSON API: Hierarchy data for SVG/DOM tree. |
| `POST`| `/super-admin/designations` | `DesignationController@store` | Inline creation of job designation. |
| `DELETE`| `/super-admin/designations/{id}` | `DesignationController@destroy` | Deletes unassigned designation. |
| `GET` | `/super-admin/departments` | `DepartmentController@index` | Department management list. |
| `POST`| `/super-admin/departments` | `DepartmentController@store` | Creates new company department. |
| `PATCH`| `/super-admin/departments/{id}` | `DepartmentController@update` | Updates department name, code, head. |
| `DELETE`| `/super-admin/departments/{id}` | `DepartmentController@destroy` | Soft-deletes department. |

#### C. Leave Types, Settings & Requests
| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/super-admin/leave-types` | `LeaveTypeController@index` | Leave type configuration list. |
| `POST`| `/super-admin/leave-types` | `LeaveTypeController@store` | Creates leave type (half-day, quotas). |
| `PATCH`| `/super-admin/leave-types/{id}` | `LeaveTypeController@update` | Updates leave type properties. |
| `POST`| `/super-admin/leave-types/{id}/toggle-status` | `LeaveTypeController@toggleStatus`| Enables/disables leave category. |
| `GET` | `/super-admin/leave-settings` | `LeaveSettingsController@index` | Global quota defaults. |
| `POST`| `/super-admin/leave-settings/apply-all` | `LeaveSettingsController@applyToAll` | Applies default quotas to all users. |
| `POST`| `/super-admin/leave-settings/overrides` | `LeaveSettingsController@storeOverride` | Creates custom quota override. |
| `POST`| `/super-admin/settings/approval-levels` | `SystemSettingController@saveApprovalLevels`| Sets required approval tiers. |
| `GET` | `/super-admin/leave-requests` | `SuperAdmin\LeaveRequestController@index` | Company-wide pending leaves queue. |
| `POST`| `/super-admin/leave-requests/{id}/approve` | `SuperAdmin\LeaveRequestController@approve` | Final executive approval (bypasses chain). |
| `POST`| `/super-admin/leave-requests/{id}/reject` | `SuperAdmin\LeaveRequestController@reject` | Final executive rejection. |
| `POST`| `/super-admin/leave-requests/{id}/approve-cancellation` | `SuperAdmin\LeaveRequestController@approveCancellation`| Approves cancellation of active/past leave. |
| `POST`| `/super-admin/leave-requests/{id}/reject-cancellation` | `SuperAdmin\LeaveRequestController@rejectCancellation`| Rejects cancellation request. |

#### D. Attendance, Holidays & Remote Work
| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/super-admin/attendance-holidays/absents` | `AttendanceHolidayController@absents` | Absence management interface. |
| `GET` | `/super-admin/attendance-holidays/holidays` | `AttendanceHolidayController@holidays` | Holiday calendar management. |
| `POST`| `/super-admin/attendance-holidays/holidays` | `AttendanceHolidayController@storeHoliday` | Declares official company holiday. |
| `DELETE`| `/super-admin/attendance-holidays/holidays/{id}` | `AttendanceHolidayController@destroyHoliday`| Deletes declared company holiday. |
| `POST`| `/super-admin/attendance-holidays/absents` | `AttendanceHolidayController@storeAbsent` | Marks unexcused absence for employee. |
| `DELETE`| `/super-admin/attendance-holidays/absents/{id}` | `AttendanceHolidayController@destroyAbsent` | Deletes absence record. |
| `POST`| `/super-admin/attendance-holidays/absents/{id}/convert` | `AttendanceHolidayController@convertAbsent` | Converts absent record to approved leave. |
| `POST`| `/super-admin/attendance-holidays/remote` | `AttendanceHolidayController@storeRemote` | Logs remote work day (full/half). |
| `DELETE`| `/super-admin/attendance-holidays/remote/{id}` | `AttendanceHolidayController@destroyRemote` | Deletes remote work log. |
| `POST`| `/super-admin/attendance-holidays/settings` | `AttendanceHolidayController@updateDeductionRule`| Updates `salary_deduction_settings.json`. |
| `POST`| `/super-admin/attendance-holidays/break-settings` | `AttendanceHolidayController@updateBreakSettings`| Updates `break_settings.json`. |

#### E. Touchless Session Management & Overrides
| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/super-admin/session-management/overrides` | `SessionManagementController@overrides` | Retroactive time override audit queue. |
| `GET` | `/super-admin/session-management/mappings` | `SessionManagementController@mappings` | Slack user to HRMS user mapping table. |
| `POST`| `/super-admin/session-management/mappings/save-mapping` | `SessionManagementController@saveMapping` | Manually assigns Slack user to employee. |
| `GET` | `/super-admin/session-management/track-record` | `SessionManagementController@trackRecord` | Company-wide shift track records. |
| `GET` | `/super-admin/session-management/track-record/api/report` | `SessionManagementController@getTrackRecordApi` | JSON API: Aggregated daily shift metrics. |
| `GET` | `/super-admin/session-management/track-record/export` | `SessionManagementController@exportTrackRecord` | Excel export of monthly track sheet. |
| `POST`| `/super-admin/session-management/messages/{id}/approve` | `SessionManagementController@approveOverride`| Approves retroactive Slack override. |
| `POST`| `/super-admin/session-management/messages/{id}/reject` | `SessionManagementController@rejectOverride` | Rejects retroactive Slack override. |
| `POST`| `/super-admin/session-management/messages/{id}/reset` | `SessionManagementController@resetOverride` | Resets override back to original timestamp. |

#### F. Salary & Payroll Management
| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/super-admin/salary-estimations` | `SalaryEstimationController@index` | Payroll estimation overview table. |
| `GET` | `/super-admin/salary-estimations/api/data` | `SalaryEstimationController@getDataApi` | JSON API: Dynamic payroll calculation rows. |
| `POST`| `/super-admin/salary-estimations/update-salary` | `SalaryEstimationController@updateSalary` | Inline update of expected gross pay. |
| `POST`| `/super-admin/salary-estimations/update-commission` | `SalaryEstimationController@updateCommission` | Inline update of sales commission. |
| `GET` | `/super-admin/salary-estimations/export` | `SalaryEstimationController@exportExcel` | Excel export of payroll deductions sheet. |
| `GET` | `/super-admin/salary-slips` | `SalarySlipController@index` | Salary slip generator & email console. |
| `GET` | `/super-admin/salary-slips/api/details` | `SalarySlipController@getDetailsApi` | JSON API: Salary slip breakdown & bank info. |
| `POST`| `/super-admin/salary-slips/save-bank-details` | `SalarySlipController@saveBankDetails` | Saves employee bank, title, account, IBAN. |
| `POST`| `/super-admin/salary-slips/upload-letterhead` | `SalarySlipController@uploadLetterhead` | Uploads branded PDF letterhead image. |
| `GET` | `/super-admin/salary-slips/preview` | `SalarySlipController@previewPdf` | HTML preview of salary slip / tracksheet. |
| `POST`| `/super-admin/salary-slips/send-email` | `SalarySlipController@sendEmail` | Generates PDF (Browsershot) and emails user. |
