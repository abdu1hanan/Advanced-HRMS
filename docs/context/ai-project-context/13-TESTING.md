# BeetleOps HRMS — Project Knowledge Pack
## 13-TESTING.md: Testing Strategy, Coverage & Quality Audit

---

### 1. Current Testing State & Verified Findings

> [!WARNING]
> **CRITICAL VERIFIED FACT**: The application currently has **NO automated test suite**. The entire `tests/` directory and `phpunit.xml` configuration were deleted in Git commit `b41b6fea9f46ce7110f5caaaaa666ab66c11e668` ("Clean up unused configuration files and tests").

#### Evidence:
- Running `find tests` returns `No such file or directory`.
- `composer.json` retains `phpunit/phpunit: ^12.5.12` and `mockery/mockery: ^1.6` in `require-dev`, and defines the composer script `"test": ["@php artisan config:clear --ansi", "@php artisan test"]`.
- However, running `php artisan test` fails because no test root exists.

---

### 2. Ad-Hoc Verification Scripts (`scratch/`)

In lieu of automated CI tests, developer testing has historically relied on manual browser testing and one-off PHP scripts:

- **`scratch/verify_account_approval_flow.php`**:
  - Purpose: Simulates user registration, verifies initial `approval_status = 'pending'`, checks that `EnsureAccountApproved` middleware intercepts unapproved requests with 302 redirects, simulates Super Admin approval and rejection, and verifies in-app notification dispatch.
  - Execution: Run via CLI `php scratch/verify_account_approval_flow.php`.

---

### 3. Critical Untested Risk Areas

Given the complexity of the domain logic, the complete absence of tests creates high regression risk in the following subsystems:

| Subsystem | Key Files | Critical Untested Behaviors | Regression Severity |
|---|---|---|---|
| **Leave Approval Chain** | `LeaveApprovalChainService.php`, `LeaveRequestService.php` | • Multi-level escalation progression<br>• Chain reset to Level 1 on in-flight changes<br>• Reversal of manager rejections<br>• Boss chain exhaustion before target reached | **CRITICAL** |
| **Shift Reconstruction** | `SessionManagementController.php` (4,181 lines), `SlackMessage.php` | • Overnight shifts crossing midnight<br>• Split of break pairs across the 75m allowance boundary<br>• Auto-append virtual signouts on forgotten shifts<br>• Retroactive override approval & reset | **CRITICAL** |
| **Hierarchy Traversal** | `HierarchyService.php` | • Circular manager reference detection (`wouldCreateCycle`)<br>• BFS recursive tree calculation<br>• Fallback to HR Manager for unassigned staff | **HIGH** |
| **Payroll Calculations** | `SalaryEstimationController.php`, `SalarySlipController.php` | • Deductions under `calendar_days` vs `working_days`<br>• Prorated deductions for mid-month joiners<br>• Sales commission additions to net pay | **HIGH** |
| **Slack Webhook Ingestion** | `SlackWebhookController.php` (2,010 lines) | • HMAC-SHA256 signature verification<br>• NLP keyword regex parsing & typo detection<br>• Edit and deletion sync reconciliation | **HIGH** |

---

### 4. Recommended Testing Roadmap

To restore confidence and prevent regressions during future development, the following automated testing foundation must be implemented:

#### Phase 1: Re-establish Test Infrastructure
1. Recreate `phpunit.xml` with standard SQLite in-memory test database configuration:
   ```xml
   <env name="DB_CONNECTION" value="sqlite"/>
   <env name="DB_DATABASE" value=":memory:"/>
   <env name="CACHE_STORE" value="array"/>
   <env name="SESSION_DRIVER" value="array"/>
   <env name="QUEUE_CONNECTION" value="sync"/>
   ```
2. Re-establish directory structure: `tests/Unit/` and `tests/Feature/`.

#### Phase 2: High-Priority Unit Tests
- `LeaveRequestServiceTest`: Verify `calculateDays()` correctly excludes Saturdays and Sundays, rejects inverted date ranges, and handles `is_half_day` scaling.
- `HierarchyServiceTest`: Verify cycle prevention prevents $A \rightarrow B \rightarrow A$ loops and that BFS correctly computes subordinate ID sets.
- `LeaveBalanceServiceTest`: Verify available days formula and year-end carry-forward capping.

#### Phase 3: High-Priority Feature Tests
- `SlackWebhookTest`: Test valid/invalid HMAC signatures, challenge response, and keyword extraction.
- `LeaveApprovalChainTest`: Test Level 1 $\rightarrow$ Level 2 $\rightarrow$ Super Admin progression, and test that requesting changes restarts the chain at Level 1.
- `AccountApprovalMiddlewareTest`: Verify unapproved users cannot access protected `/admin/*` routes.
