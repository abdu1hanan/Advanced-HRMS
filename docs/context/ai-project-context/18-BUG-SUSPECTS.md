# BeetleOps HRMS — Project Knowledge Pack
## 18-BUG-SUSPECTS.md: Suspected Logic Bugs & Edge-Case Vulnerabilities

---

### 1. Overview of Code Suspects

This technical audit identified 5 suspicious implementation patterns that appear logically incomplete, inconsistent, or prone to edge cases. **No code modifications have been made; these items are documented for verification.**

---

### 2. Suspect Catalog

#### Suspect 1: Multi-Day Range Allowed for Half-Day Leave Types
- **Severity**: **HIGH**
- **Confidence**: **HIGH** (Confirmed directly in source)
- **File**: [`app/Services/LeaveRequestService.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Services/LeaveRequestService.php#L71-L75)
- **Symbol**: `LeaveRequestService::submit()` and `requestChange()`
- **Why Suspicious**:
  Migration `2026_09_15_170000_add_is_half_day_to_leave_types_table.php` added the `is_half_day` boolean column to `leave_types`. In `submit()`:
  ```php
  $days = $this->calculateDays($data['start_date'], $data['end_date']);
  if ($leaveType->is_half_day) {
      $days = $days * 0.5;
  }
  ```
  However, `LeaveRequestService` does **not** assert that `start_date === end_date`. If an employee selects a 5-day span (e.g., Monday through Friday) while choosing a Half-Day leave type, the system computes $5 \times 0.5 = 2.5$ days.
  This produces fractional multi-day spans that confuse attendance track records (which display leaves as either full day or half day on a specific calendar date) and creates unpredictable behavior in `SessionManagementController` when reconciling daily hours.
- **Verification Plan**:
  Check whether business policy allows multi-day half-day applications (e.g., working 5 consecutive half-days) or whether half-day leaves must be strictly restricted to single dates (`start_date === end_date`).

---

#### Suspect 2: Inconsistency Between `hasSubordinates()` and `subordinate_ids`
- **Severity**: **MEDIUM**
- **Confidence**: **HIGH** (Confirmed directly in source)
- **File**: [`app/Models/User.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/User.php#L140-L170)
- **Symbol**: `User::hasSubordinates()` vs `User::getSubordinateIdsAttribute()`
- **Why Suspicious**:
  `User::getSubordinateIdsAttribute()` performs a multi-tier Breadth-First Search (BFS) to find all subordinates at any depth below the user.
  In contrast, `hasSubordinates()` only checks one level deep:
  ```php
  public function hasSubordinates(): bool
  {
      return self::query()->where('manager_id', $this->id)->exists();
  }
  ```
  If an organization hierarchy has intermediate nodes (e.g., Director $\rightarrow$ Manager $\rightarrow$ Employee) and the intermediate Manager record is temporarily soft-deleted or assigned as a guest supervisor, `hasSubordinates()` could evaluate to `false` even if indirect subordinates exist in the company tree.
- **Verification Plan**:
  Update `hasSubordinates()` to check `!empty($this->subordinate_ids)` or verify that all navigation views explicitly check `!empty(auth()->user()->subordinate_ids)`.

---

#### Suspect 3: Auto-Appended Virtual Sign-Out Collapses Day to 1 Minute
- **Severity**: **MEDIUM**
- **Confidence**: **HIGH** (Confirmed directly in source)
- **File**: [`app/Models/SlackMessage.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Models/SlackMessage.php#L160-L175)
- **Symbol**: `SlackMessage::recreateAutoAppendIfNecessary()` and `ensureDayAutoAppends()`
- **Why Suspicious**:
  When an employee signs in on Day 2 without signing out on Day 1, the auto-healing engine generates a synthetic sign-out timestamped 1 minute after their Day 1 check-in:
  ```php
  $autoMsgAt = $prevSignIn->message_at->copy()->addMinute();
  self::create([
      ...
      'message_text' => 'sign out',
      'message_at' => $autoMsgAt,
      'approval_status' => 'pending',
      'is_auto_appended' => true,
  ]);
  ```
  While this successfully closes the open segment to prevent run-away hours, the reconstructed session gives the employee only **1 minute of worked time** for that day. If neither the employee nor HR notices the pending auto-append before monthly payroll is generated, the employee suffers an unexcused shortfall penalty.
- **Verification Plan**:
  Verify whether `SalaryEstimationController` warns Super Admins about unverified auto-appended virtual signouts before generating finalized salary slips.

---

#### Suspect 4: Hardcoded 16-Hour Lookback Window for Stale Check-Ins
- **Severity**: **LOW**
- **Confidence**: **MEDIUM**
- **File**: [`app/Http/Controllers/SlackWebhookController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SlackWebhookController.php#L705-L707)
- **Symbol**: `SlackWebhookController::checkRealTimeSlackAlerts()`
- **Why Suspicious**:
  The controller checks if an unclosed check-in should be considered stale using a static 16-hour threshold:
  ```php
  $isStaleCheckIn = $prevIsCheckIn && ($prevMessage ? $prevMessage->message_at->diffInHours($message->message_at) > 16 : false);
  ```
  If an employee works a long 18-hour emergency or on-call maintenance shift across a weekend, their legitimate continuation is treated as stale, causing real-time sequence anomaly alerts.
- **Verification Plan**:
  Confirm maximum allowable shift lengths with HR and test with overnight maintenance shifts.

---

#### Suspect 5: Ephemeral In-Memory Deleted Message Cache Window
- **Severity**: **LOW**
- **Confidence**: **HIGH**
- **File**: [`app/Http/Controllers/SlackWebhookController.php`](file:///Users/adeelbukhari/Documents/Projects/hrms/app/Http/Controllers/SlackWebhookController.php#L180-L184)
- **Symbol**: `SlackWebhookController::handle()`
- **Why Suspicious**:
  ```php
  $recentlyDeleted = \Illuminate\Support\Facades\Cache::get('recently_deleted_slack_msg_ids', []);
  $recentlyDeleted[] = (int) $messageId;
  \Illuminate\Support\Facades\Cache::put('recently_deleted_slack_msg_ids', array_unique($recentlyDeleted), now()->addMinutes(10));
  ```
  The cache holds deleted IDs for only 10 minutes. If a Super Admin leaves their Session Management tab open in the background without refreshing for longer than 10 minutes, subsequent polling cycles will not receive the deletion signal, resulting in phantom records on the browser screen until a hard page reload occurs.
- **Verification Plan**:
  Test live polling UI behavior when a message is deleted in Slack more than 10 minutes prior to polling.
