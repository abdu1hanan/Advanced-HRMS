# BeetleOps HRMS — Project Knowledge Pack
## 16-RISKS-AND-TECH-DEBT.md: Technical Debt & Architectural Risks

---

### 1. Risk Matrix Overview

| Risk / Debt Item | Category | Severity | Confidence | Impact Summary |
|---|---|---|---|---|
| **Complete Absence of Automated Tests** | Quality / CI | **CRITICAL** | VERIFIED FACT | Entire `tests/` directory was deleted. High probability of breaking complex multi-tier approval or session math during future edits. |
| **Monstrous God-Controllers** | Maintainability | **HIGH** | VERIFIED FACT | `SessionManagementController` (4,181 lines) and `SlackWebhookController` (2,010 lines) mix HTTP, regex parsing, shift math, and reporting. |
| **Storage JSON Files for Critical Settings** | Reliability | **HIGH** | VERIFIED FACT | Deduction rules and break limits rely on unversioned flat JSON files in `storage/app/` without ACID locking or schema validation. |
| **Synchronous Headless Chrome PDF Generation** | Performance | **MEDIUM** | VERIFIED FACT | Spawning Puppeteer Headless Chrome inside the synchronous HTTP request thread blocks web workers for 3–8 seconds per salary slip. |
| **Hardcoded `Asia/Karachi` Timezone** | Architecture | **MEDIUM** | VERIFIED FACT | Timezone string is hardcoded across 40+ controllers and models rather than referenced via application config. |
| **Local Filesystem Coupling for Assets** | Infrastructure | **MEDIUM** | VERIFIED FACT | Letterhead uploads write directly to `public_path('images/salary_slip_letterhead.png')`, preventing multi-server or containerized scaling. |
| **Missing Concurrency Locks on Leave Balances** | Consistency | **MEDIUM** | VERIFIED FACT | `LeaveRequestService::submit()` checks quota and increments balance without database row-level locking (`lockForUpdate()`). |
| **Iterative N+1 Traversal in Hierarchy** | Performance | **LOW** | VERIFIED FACT | `HierarchyService::getBossChain()` executes single `User::find()` queries inside a `while` loop for each level in the chain. |

---

### 2. Deep Dive: Technical Debt Items

#### Item 1: Complete Absence of Automated Tests [CRITICAL]
- **Evidence**: `find tests` returns no files. Commit `b41b6fea9f46ce7110f5caaaaa666ab66c11e668` explicitly deleted all tests.
- **Impact**: Any modification to `LeaveApprovalChainService` (escalations, in-flight resets), `SlackMessage` (break balance, grace period, auto-appends), or `SalaryEstimationController` (prorated deduction math) carries extreme risk of silent regressions.
- **Remediation**: Immediately re-introduce PHPUnit/Pest with an SQLite in-memory test suite targeting core services first.

#### Item 2: Monstrous "God-Class" Controllers [HIGH]
- **Evidence**:
  - `app/Http/Controllers/SuperAdmin/SessionManagementController.php`: 4,181 lines, 190 KB.
  - `app/Http/Controllers/SlackWebhookController.php`: 2,010 lines, 98 KB.
  - `app/Http/Controllers/SuperAdmin/AttendanceHolidayController.php`: 1,471 lines, 67 KB.
- **Code Smells**:
  - `SessionManagementController` contains HTTP request routing, Excel spreadsheet formatting (`PhpOffice\PhpSpreadsheet`), overnight date window slicing, time duration string formatters, and employee deduction lookups.
  - `SlackWebhookController` contains natural language regex extraction, HMAC signature verification, manual auto-mapping algorithms, break alert dispatching, and auto-append reconciliation.
- **Remediation**:
  - Extract session reconstruction into a dedicated `AttendanceReconstructionService`.
  - Extract Excel generation into Laravel Excel or dedicated Export classes.
  - Extract Slack message parsing into a `SlackMessageParser` domain value object.

#### Item 3: Core Settings Stored in Flat JSON Files [HIGH]
- **Evidence**:
  - `storage/app/salary_deduction_settings.json`
  - `storage/app/break_settings.json`
  - `storage/app/slack_auto_connect_setting.json`
- **Code Smell**:
  ```php
  $settingsFile = storage_path('app/break_settings.json');
  if (file_exists($settingsFile)) {
      $data = json_decode(file_get_contents($settingsFile), true);
      ...
  }
  ```
- **Risks**:
  - Concurrent writes can result in corrupted JSON or truncated files.
  - No transactional rollback if settings updates fail.
  - In distributed, load-balanced container environments (Kubernetes, AWS ECS), changes made on one web container are lost on others unless using shared NFS/EFS.
- **Remediation**: Migrate these settings into the existing `system_settings` database table.

#### Item 4: Synchronous Puppeteer Execution During Web Requests [MEDIUM]
- **Evidence**: `SalarySlipController::sendEmail()` calls `Browsershot::html($html)->pdf()` directly inside the HTTP request controller.
- **Risks**:
  - Spawning Node.js and Chromium takes 3,000–8,000ms.
  - If a web server has limited PHP-FPM workers (e.g., 4 or 8), generating salary slips concurrently will quickly exhaust available workers, causing 504 Gateway Timeouts for other staff members.
- **Remediation**: Move PDF generation and email sending into a background queued job (`GenerateAndSendSalarySlipJob`).

#### Item 5: Hardcoded `Asia/Karachi` Timezone [MEDIUM]
- **Evidence**: Grep reveals 45+ occurrences of `'Asia/Karachi'` directly written inside queries, controller methods, and console commands.
- **Risks**:
  - Makes multi-region deployment or timezone reconfiguration impossible without widespread code modifications.
  - Risk of subtle timezone bugs if some dates are parsed with Carbon's default UTC while others use `Asia/Karachi`.
- **Remediation**: Define `config('hrms.timezone', 'Asia/Karachi')` and reference it consistently throughout the codebase.

#### Item 6: Local Disk Coupling for Letterhead Images [MEDIUM]
- **Evidence**:
  ```php
  $destinationPath = public_path('images');
  $file->move($destinationPath, 'salary_slip_letterhead.png');
  ```
- **Risks**:
  - Overwrites a static asset in `public/images/`.
  - If deployed in Docker or serverless containers where filesystems are ephemeral, redeploying destroys the uploaded letterhead image.
- **Remediation**: Use Laravel's Storage disk abstraction (`Storage::disk('public')` or `s3`).
