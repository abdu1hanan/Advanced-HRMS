# BeetleOps HRMS — Project Knowledge Pack
## 20-ENGINEERING-CONVENTIONS.md: Codebase Patterns & Engineering Standards

---

### 1. PHP & Laravel Language Conventions

BeetleOps HRMS targets **PHP 8.3+** and leverages modern PHP 8 and Laravel 11/12 syntax:

1. **PHP 8 Attributes on Eloquent Models**:
   - Instead of traditional protected properties `$fillable` and `$hidden`, recent models utilize PHP 8 class attributes:
     ```php
     #[Fillable(['name', 'email', 'role_id', 'manager_id', ...])]
     #[Hidden(['password', 'remember_token'])]
     class User extends Authenticatable { ... }
     ```
2. **Constructor Property Promotion**:
   - Used extensively across Domain Services and Mailable classes:
     ```php
     public function __construct(
         protected LeaveBalanceService $balanceService,
         protected LeaveApprovalChainService $chainService,
         protected HierarchyService $hierarchyService
     ) {}
     ```
3. **Array Return Types for Model Casts**:
   - Uses Laravel 11's method-based casts instead of the legacy property:
     ```php
     protected function casts(): array
     {
         return [
             'email_verified_at' => 'datetime',
             'password'          => 'hashed',
             'special_order'     => 'integer',
         ];
     }
     ```

---

### 2. Naming & Directory Conventions

| Artifact | Convention | Examples |
|---|---|---|
| **Controllers** | PascalCase ending in `Controller` | `LeaveRequestController`, `SessionManagementController` |
| **Domain Services** | PascalCase ending in `Service` | `HierarchyService`, `LeaveApprovalChainService` |
| **Eloquent Models** | Singular PascalCase | `User`, `LeaveRequest`, `SlackMessage`, `CompanyHoliday` |
| **Notification Classes**| PascalCase ending in `Notification` | `LeaveRequestSubmitted`, `BreakExceededNotification` |
| **Queued Jobs** | PascalCase ending in `Job` | `BreakBalanceAlertJob`, `BreakGracePeriodAlertJob` |
| **Artisan Commands** | PascalCase command class; kebab-case signature | `SendIncompleteCycleReminders` (`attendance:incomplete-reminders`) |
| **Blade Views** | kebab-case filenames in nested directories | `resources/views/super-admin/salary-slips/preview-page.blade.php` |
| **Database Tables** | snake_case plural | `leave_approval_chains`, `slack_user_mappings` |
| **Database Columns** | snake_case | `approval_chain_target`, `expected_salary_amount` |
| **Route Names** | dot-notation kebab-case | `super-admin.leave-requests.approve`, `admin.track-record.api` |

---

### 3. Service Layer & Persistence Patterns

1. **Explicit Transaction Boundaries**:
   - Every operation altering leave balances, statuses, or chain records must be enclosed in `DB::transaction()`:
     ```php
     return DB::transaction(function () use ($request, $user) {
         ...
         $balance->update(['used' => max(0.0, $balance->used - $days)]);
         $request->update(['status' => LeaveRequest::STATUS_CANCELLED]);
         return $request;
     });
     ```
2. **Self-Healing Model Accessors**:
   - Services often include self-healing reconciliations when fetching records. For example, `LeaveBalanceService::ensureBalanceRow()` automatically resets negative balance anomalies to zero and syncs absent counts on load.
3. **No Repository Pattern**:
   - The application does **not** use an abstract repository interface pattern. Controllers and Services interact directly with Eloquent models using fluent query builder scopes (`User::where(...)`, `SlackMessage::query()`).

---

### 4. Notification & Activity Conventions

1. **Standard Array Payload**:
   Every database notification returns a structured dictionary in `toArray($notifiable)`:
   ```php
   return [
       'type'             => 'leave_submitted', // Canonical type identifier
       'leave_request_id' => $this->leaveRequest->id,
       'employee_name'    => $user->name,
       'message'          => "{$user->name} submitted a leave request.",
       'url'              => $url,              // Fallback destination URL
   ];
   ```
2. **Dynamic URL Resolution**:
   Because notifiable recipients may have different roles (Super Admin vs Line Manager vs Employee), `NotificationController::mapNotificationUrl()` resolves destination URLs dynamically at read time rather than relying strictly on the stored URL.

---

### 5. API Response Schema

All internal AJAX endpoints follow a standard JSON envelope:

#### Successful Response:
```json
{
  "status": "success",
  "message": "Human-readable feedback for toast or alert.",
  "data": { ... }
}
```

#### Validation / Business Rule Failure:
```json
{
  "status": "error",
  "message": "Descriptive reason why the action was rejected."
}
```

---

### 6. Frontend UI Conventions

1. **Dark Spotify Theme Utility Classes**:
   - Dark card container: `.spotify-card`
   - Dark switch input: `.custom-switch-dark`
   - Floating badge: `.status-badge`
2. **Dynamic Searchable Selects**:
   - Any `<select>` named `user_id`, `manager_id`, or carrying the class `.searchable-select` is automatically intercepted by `makeSearchableSelect()` in `app.blade.php`.
3. **Phone Inputs**:
   - Every `<input type="tel">` or input named `phone` / `emergency_number` is sanitized in real time by the client-side keystroke filter in `app.blade.php`.
