# BeetleOps HRMS — Project Knowledge Pack
## 01-PROJECT-OVERVIEW.md: System Overview & Business Purpose

---

### 1. Project Background & Purpose

**BeetleOps HRMS** is an enterprise Human Resource Management System tailored for modern tech and professional service organizations. In conventional companies, employee attendance relies on physical biometric fingerprint readers, RFID badges, or complex portal logins. BeetleOps HRMS eliminates hardware requirements by converting an organization's existing **Slack workspace into an automated, touchless attendance and time-tracking terminal**.

The system parses natural-language messages from designated Slack channels, reconstructs complete daily work cycles, accounts for breaks and shifts, validates leave requests through a dynamic manager-hierarchy approval chain, tracks company holidays and admin-marked absents, computes monthly payroll deductions, and generates branded salary slips.

---

### 2. Core Business Problems Solved

1. **Touchless, Zero-Friction Attendance Tracking**:
   - Employees check in, take breaks, return from breaks, and check out directly through Slack by sending short keywords like `signin`, `break`, `back`, and `signout`.
   - The system recognizes timestamps, flags anomalies (e.g., signing in twice, taking a break before signing in, forgetting to sign out), and automatically alerts employees via Slack DMs.

2. **Retroactive Session Correction with Audit Controls**:
   - Employees who forget to sign in or sign out at the exact moment can request manual adjustments via Slack message modifiers (e.g., `signin at 10:00 am` or `sign out at 06:30 PM`).
   - These modifications do not take effect immediately; they enter an **Approval Queue** in the Super Admin portal with side-by-side timestamps, preventing employee time fraud while avoiding rigid HR ticket overhead.

3. **Dynamic Multi-Level Leave Approval Chain**:
   - Leave requests are not tied to static department silos. Instead, the system uses an organizational hierarchy tree where each employee reports to a `manager_id`.
   - The approval chain dynamically routes requests up the boss chain (Level 1 immediate manager, Level 2 director, etc.) according to a global system setting (`SystemSetting::approvalLevels()`).
   - If an employee has fewer managers than the required levels, the chain completes gracefully.

4. **In-Flight Leave Modifications with Automatic Chain Reset**:
   - If an employee realizes they applied for the wrong dates or leave type while the request is in review (or even after intermediate manager approval), they can request changes directly.
   - The system captures a snapshot of the original request, logs an audit comment, updates the request parameters, and resets the approval chain back to Level 1 so the entire management line reviews the altered dates.

5. **Integrated Attendance & Absence Reconcilement**:
   - Super Admins can declare official company holidays with granular targeting (all employees, specific departments, or specific individual users).
   - Super Admins can log unexcused absences (`AttendanceRecord`) or grant administrative leaves.
   - If an employee was incorrectly marked absent, the system provides a one-click action to **convert an absent record into an approved leave**, automatically adjusting leave balances.

6. **Automated Payroll Estimation & Headless Chrome Salary Slips**:
   - Monthly payroll calculates gross pay, sales commissions, absent deductions, unpaid leave deductions, and deductions for days occurring prior to an employee's joining date.
   - It generates pixel-perfect PDF salary slips using headless Chrome (`Spatie\Browsershot`) based on the exact same HTML template viewed in the preview UI, attaching the generated PDF to a branded email sent to the employee.

---

### 3. User Personas & Roles

The system uses three primary roles defined in the `roles` table (with role IDs dynamically resolved via `Role::idByName()`):

#### A. Super Administrator (`role: super_admin`)
- **Key Users**: Top executives configured via `special_order` column:
  - `special_order = 1`: Chief Executive Officer (CEO) — apex of the organizational hierarchy.
  - `special_order = 2`: Chief Technology Officer (CTO) — reports directly to CEO.
  - `special_order = 3`: HR Manager — reports to CTO/CEO and acts as the default manager for employees without an assigned manager.
- **Authority**:
  - Unrestricted access to all configuration, users, departments, designations, and leave settings.
  - Sole authority to approve/reject retroactive Slack session overrides.
  - Sole authority to declare company holidays, log absents, and grant admin leaves.
  - Final approval authority for past/active leave cancellations.
  - Access to company-wide salary estimation, banking configurations, and salary slip email dispatcher.

#### B. Staff Member / Line Manager (`role: staff`)
- **Regular Staff (Leaf Node)**:
  - Applies for leaves (Casual, Sick, Emergency, Unpaid, Half-Day).
  - Tracks personal leave quota balances, carry-forward days, and pending requests.
  - Reviews personal monthly track record (work hours, breaks, expected vs logged hours).
  - Can view the complete company organization chart.
- **Line Manager (Node with Direct Reports)**:
  - Automatically detected when `User::where('manager_id', $user->id)->exists()` is true.
  - Inherits all regular staff capabilities.
  - Receives in-app notifications when direct reports or lower-tier subordinates submit leave requests.
  - Approves or rejects subordinate leave requests within the configured approval chain target.
  - Views team directory, subordinate attendance logs, and team track records.

#### C. Guest Supervisor (`role: guest`)
- Created to accommodate external stakeholders, contract directors, or advisory partners who manage internal staff but are not regular employees.
- Has subordinate line-management permissions (can review and approve leaves for team members).
- **Restricted**: Cannot apply for personal leaves or accrue leave balances (`LeaveRequestPolicy::create()` returns false for guests).

---

### 4. High-Level Subsystems

```
┌────────────────────────────────────────────────────────────────────────┐
│                          BeetleOps HRMS Core                           │
├──────────────────┬───────────────────┬─────────────────────────────────┤
│  Attendance &    │ Leave Management  │ Payroll & Financial             │
│  Slack Sync      │                   │                                 │
│  • Slack Webhook │ • Custom Types    │ • Monthly Hours Aggregator      │
│  • Session Auto- │ • Quota & Balance │ • Working/Calendar Day Formulas │
│    Healing       │ • Chain Approvals │ • Sales Commission Tracker      │
│  • 60m Break +   │ • In-Flight Reset │ • Browsershot Headless PDF      │
│    15m Grace     │ • Cancellation QA │ • SMTP Salary Slip Mailer       │
├──────────────────┼───────────────────┼─────────────────────────────────┤
│  Org Structure   │ Account Approval  │ Notifications & Logs            │
│                  │                   │                                 │
│  • Departments   │ • Registration QA │ • 24 In-App Event Classes       │
│  • Designations  │ • HR Review Queue │ • Dynamic Action Router         │
│  • Interactive   │ • Activation DMs  │ • Slack DM Anomaly Alerts       │
│    Org Tree      │ • Middleware Guard│ • 90-Day Auto Purge Task        │
└──────────────────┴───────────────────┴─────────────────────────────────┘
```

---

### 5. Architectural Facts vs Inferences

- **VERIFIED FACT**:
  - The application relies on Laravel 11/12 with PHP 8.3.
  - Role table has been collapsed to 3 roles (`super_admin`, `staff`, `guest`); designations (`designations` table) now capture job titles.
  - No automated test files exist under `tests/` (deleted in commit `b41b6fe`).
  - Attendance session data is not stored in a separate timesheet table; it is dynamically reconstructed on the fly by querying `slack_messages` within UTC timestamp ranges.
  - Timezone calculations across the attendance engine explicitly enforce `Asia/Karachi`.

- **INFERENCE**:
  - The primary operations and development team operate out of the Pakistan timezone (`Asia/Karachi`), as evidenced by hardcoded timezones in console commands, controllers, and Slack notification messages.
  - Slack is the mandatory communication tool for all employees, and company policy strictly dictates attendance compliance through channel messages.
