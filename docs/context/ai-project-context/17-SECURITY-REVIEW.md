# BeetleOps HRMS — Project Knowledge Pack
## 17-SECURITY-REVIEW.md: Defensive Security Audit

---

### 1. Executive Summary & Findings Matrix

| Finding ID | Title | Severity | Confidence | Status |
|---|---|---|---|---|
| **SEC-01** | Slack Webhook Signature Bypassed if Secret is Unset | **HIGH** | VERIFIED FACT | Confirmed Configuration Risk |
| **SEC-02** | Unauthenticated Access to Internal System Documentation | **MEDIUM** | VERIFIED FACT | Confirmed Architecture Decision |
| **SEC-03** | Lack of Rate Limiting on Slack Webhook Endpoint | **MEDIUM** | VERIFIED FACT | Potential DoS / Worker Saturation |
| **SEC-04** | Missing Concurrency Locking on Quota Deductions | **LOW** | VERIFIED FACT | Theoretical Race Condition |
| **SEC-05** | Public Letterhead Overwrite | **LOW** | VERIFIED FACT | Confirmed File Overwrite Pattern |
| **SEC-06** | CSRF Exemption for Slack Webhook | **INFORMATIONAL** | VERIFIED FACT | Standard Architecture Pattern |

---

### 2. Detailed Findings

#### SEC-01: Slack Webhook Signature Check Bypassed when Secret is Empty [HIGH]
- **Location**: `app/Http/Controllers/SlackWebhookController.php` (lines 88–109)
- **Code Observation**:
  ```php
  $signingSecret = config('services.slack.signing_secret');
  if ($signingSecret) {
      ...
      if (!hash_equals($computedSignature, $signature)) {
          return response()->json(['error' => 'Unauthorized signature.'], 401);
      }
  } else {
      Log::warning('Slack signing secret is not configured in services.php config. Skipping signature check for testing purposes.');
  }
  ```
- **Risk Assessment**: If `SLACK_SIGNING_SECRET` is left unset, misconfigured, or deleted in the production environment, the webhook endpoint silently accepts unverified, forged HTTP POST payloads. An attacker knowing Slack User IDs could submit fabricated `signin`, `break`, or override requests to manipulate attendance records.
- **Defensive Recommendation**:
  ```php
  if (app()->isProduction() && empty($signingSecret)) {
      abort(500, 'Slack signing secret is not configured in production.');
  }
  ```

#### SEC-02: Public Unauthenticated Access to Internal Documentation [MEDIUM]
- **Location**: `routes/web.php` (lines 214–240)
- **Code Observation**:
  Route `/docs/{path?}` serves the contents of `doc website/` to anyone without checking authentication.
- **Path Traversal Check**:
  ```php
  $filePath = realpath($basePath . '/' . $path);
  if ($filePath && is_file($filePath) && str_starts_with($filePath, realpath($basePath))) {
      ...
      return response()->file($filePath, ['Content-Type' => $contentType]);
  }
  ```
  The traversal protection using `realpath()` and `str_starts_with()` is structurally sound and successfully prevents directory traversal outside of `doc website/`.
- **Information Disclosure Risk**: The markdown guides in `doc website/content/` describe internal company attendance policies, approval hierarchy mechanics, Slack mapping rules, and admin panel capabilities. This documentation should ideally be restricted to authenticated staff.
- **Defensive Recommendation**: Wrap `Route::get('/docs/{path?}', ...)` inside the `['auth', 'verified']` middleware group.

#### SEC-03: Lack of Rate Limiting on Slack Webhook [MEDIUM]
- **Location**: `routes/web.php` (line 32)
- **Observation**:
  `Route::post('/webhook/slack', [SlackWebhookController::class, 'handle']);` has no `throttle` middleware attached.
- **Risk Assessment**: Because `SlackWebhookController` performs database queries, user lookups, regex parsing, and potentially queued job dispatches, a flood of requests to this endpoint could cause database connection pool exhaustion or high CPU utilization.
- **Defensive Recommendation**: Apply a throttle middleware (e.g., `throttle:120,1` — 120 requests per minute).

#### SEC-04: Lack of Pessimistic Locking on Quota Deductions [LOW]
- **Location**: `app/Services/LeaveRequestService.php::submit()`
- **Observation**:
  ```php
  $balance = $this->balanceService->ensureBalanceRow($user, $leaveType, $year);
  if ($days > $this->balanceService->availableDays($balance)) {
      throw ValidationException::withMessages(...);
  }
  $balance->increment('pending', $days);
  ```
- **Risk Assessment**: Although wrapped in `DB::transaction`, the query does not acquire a lock (`lockForUpdate()`). If an employee submits two concurrent requests across separate tabs for their remaining 1 day of balance at the exact same millisecond, both could pass the balance check before the increment commits.
- **Defensive Recommendation**: Use `LeaveBalance::where(...)->lockForUpdate()->first()` when evaluating quota availability.

#### SEC-05: Fixed Target Path for Letterhead Upload [LOW]
- **Location**: `app/Http/Controllers/SuperAdmin/SalarySlipController.php::uploadLetterhead()`
- **Observation**:
  ```php
  $request->validate([
      'letterhead_image' => 'required|image|mimes:png,jpg,jpeg|max:2048',
  ]);
  $file->move($destinationPath, 'salary_slip_letterhead.png');
  ```
- **Security Posture**: MIME type validation prevents arbitrary executable uploads. The hardcoded target name `salary_slip_letterhead.png` prevents user-controlled file name path traversal.
- **Architectural Note**: Overwriting a file in `public/images` is bad practice in distributed environments, but does not present an immediate code execution vulnerability.

---

### 3. Authentication & IDOR Verification Results

- **Notifications IDOR**: `NotificationController` exclusively queries `Auth::user()->notifications()`. Cross-user reading or marking is impossible.
- **Leave Requests IDOR**: `LeaveRequestPolicy::review()` verifies that `$reviewer->subordinate_ids` contains `$leaveRequest->user_id`. Non-managers cannot approve peer leaves.
- **Salary Data IDOR**: `SalarySlipController` and `SalaryEstimationController` are strictly protected under `role:super_admin`. Regular employees cannot query other users' gross pay or bank account numbers.
- **SQL Injection**: No vulnerable string interpolations found. All dynamic queries use Eloquent parameterized statements or bound raw queries (`whereRaw('LOWER(name) = ?', [...])`).
