# BeetleOps HRMS — Project Knowledge Pack
## 12-CONFIGURATION.md: Configuration & Environment Catalog

---

### 1. Environment Variables Catalog

All sensitive credentials have been sanitized and redacted.

| Variable Name | Purpose | Component | Required? | Type / Secret? | Default Behavior |
|---|---|---|---|---|---|
| `APP_NAME` | Name of the application | Core / Email subjects | Yes | String (Public) | `"BeetleOps HRMS"` |
| `APP_ENV` | Application environment | Laravel Kernel | Yes | String (Public) | `local` / `production` |
| `APP_KEY` | 32-character AES encryption key | Encryption / Sessions | Yes | Secret (Redacted) | Generated via `artisan key:generate` |
| `APP_DEBUG` | Detailed exception stack traces | Debugging / UI | Yes | Boolean (Public) | `false` in production |
| `APP_URL` | Root URL of application | Routing / Reset Links | Yes | URL (Public) | `http://localhost` |
| `BCRYPT_ROUNDS` | Cost factor for password hashing | Auth / Hashing | Optional | Integer (Public) | `12` |
| `LOG_CHANNEL` | Logging destination | Logging | Yes | String (Public) | `stack` |
| `LOG_LEVEL` | Minimum logging severity | Logging | Optional | String (Public) | `debug` (local) / `info` (prod) |
| `DB_CONNECTION` | Database driver | Database | Yes | String (Public) | `mysql` |
| `DB_HOST` | Database server host | Database | Yes | String (Public) | `127.0.0.1` |
| `DB_PORT` | Database server port | Database | Yes | Integer (Public) | `3306` |
| `DB_DATABASE` | Database schema name | Database | Yes | String (Public) | `hrms_beetleops` |
| `DB_USERNAME` | Database username | Database | Yes | String (Public) | `<REDACTED — DB User>` |
| `DB_PASSWORD` | Database password | Database | Yes | Secret (Redacted) | `<REDACTED — DB Password>` |
| `SESSION_DRIVER` | Session storage engine | HTTP Sessions | Yes | String (Public) | `database` |
| `SESSION_LIFETIME` | Session duration in minutes | HTTP Sessions | Yes | Integer (Public) | `120` |
| `QUEUE_CONNECTION` | Background queue backend | Queue Worker | Yes | String (Public) | `database` |
| `CACHE_STORE` | Caching backend | Caching | Yes | String (Public) | `database` |
| `MAIL_MAILER` | Mail transport driver | Mail Subsystem | Yes | String (Public) | `log` / `smtp` |
| `MAIL_HOST` | SMTP server address | Mail Subsystem | Optional | String (Public) | `127.0.0.1` |
| `MAIL_PORT` | SMTP server port | Mail Subsystem | Optional | Integer (Public) | `2525` |
| `MAIL_USERNAME` | SMTP account username | Mail Subsystem | Optional | String (Public) | `<REDACTED — Mail User>` |
| `MAIL_PASSWORD` | SMTP account password | Mail Subsystem | Optional | Secret (Redacted) | `<REDACTED — Mail Password>` |
| `MAIL_FROM_ADDRESS`| Default sender email address | Mail Subsystem | Yes | Email (Public) | `"hello@example.com"` |
| `MAIL_FROM_NAME` | Default sender display name | Mail Subsystem | Yes | String (Public) | `"${APP_NAME}"` |
| `SLACK_SIGNING_SECRET`| HMAC-SHA256 signature secret | Slack Webhook | Yes (Prod) | Secret (Redacted) | `<REDACTED — Slack Signing Secret>` |
| `SLACK_BOT_USER_OAUTH_TOKEN`| Bot user OAuth token (`xoxb-`)| Slack DMs | Yes (Prod) | Secret (Redacted) | `<REDACTED — Slack Bot Token>` |
| `SLACK_BOT_USER_DEFAULT_CHANNEL`| Default alert channel ID | Slack Alerts | Optional | String (Public) | `""` |
| `SLACK_LEAVE_SYNC_CHANNEL_ID`| Dedicated channel for leave sync| Slack Leave Sync | Optional | String (Public) | `""` |
| `SALARY_SLIP_CC_EMAILS`| Executive emails CC'd on slips | Salary Slip Mailer | Optional | CSV String (Public)| `""` |
| `NODE_PATH` | Path to Node.js binary | Browsershot | Optional | Path (Public) | System default `node` |
| `NPM_PATH` | Path to npm binary | Browsershot | Optional | Path (Public) | System default `npm` |
| `CHROME_PATH` | Path to Chromium / Chrome | Browsershot | Optional | Path (Public) | Puppeteer bundled Chromium |
| `NODE_MODULES_PATH`| Path to local node_modules | Browsershot | Optional | Path (Public) | `base_path('node_modules')` |

---

### 2. Runtime JSON Configuration Files (`storage/app/`)

In addition to environment variables, certain business rules are persisted as flat JSON files in `storage/app/` to allow live Super Admin modifications without server restarts:

#### A. `storage/app/salary_deduction_settings.json`
- **Managed By**: `SuperAdmin\AttendanceHolidayController::updateDeductionRule()`
- **Structure**:
  ```json
  {
    "rule": "calendar_days",
    "recalculate_all": true,
    "effective_month": null,
    "previous_rule": null
  }
  ```
- **Allowed Rules**: `calendar_days` (divides gross salary by total calendar days) or `working_days` (divides gross salary by working days).

#### B. `storage/app/break_settings.json`
- **Managed By**: `SuperAdmin\AttendanceHolidayController::updateBreakSettings()`
- **Structure**:
  ```json
  {
    "break_balance_minutes": 60,
    "break_grace_period_minutes": 15
  }
  ```
- **Caching**: Cached via `Cache::remember('hrms_break_balance_seconds', 86400)` in `SlackMessage::getBreakBalanceSeconds()` and automatically cleared on update.

#### C. `storage/app/slack_auto_connect_setting.json`
- **Managed By**: `SuperAdmin\SessionManagementController::toggleAutoConnect()`
- **Structure**:
  ```json
  {
    "enabled": true
  }
  ```
- **Purpose**: Controls whether JSON auto-mapping directives sent in Slack (`{"auto_connect_user_profile": "Name"}`) are processed.

---

### 3. Environment Lifecycle Differences

| Feature | Local Development (`APP_ENV=local`) | Production (`APP_ENV=production`) |
|---|---|---|
| **Slack Webhook Signature** | Ignored if `SLACK_SIGNING_SECRET` is null (warning logged) | Strictly enforced; 401 on missing or mismatched HMAC |
| **Slack API SSL Verification** | `Http::withoutVerifying()` enabled (avoids local cURL cert errors) | Strict SSL verification enforced |
| **Mail Delivery** | Usually `MAIL_MAILER=log` | `MAIL_MAILER=smtp` delivering real emails |
| **Queue Execution** | Often synchronous (`sync`) or manual `artisan queue:listen` | Managed background daemon (`supervisor` / `systemd`) |
| **App Debug** | `APP_DEBUG=true` showing detailed stack traces | `APP_DEBUG=false` returning user-friendly error views |
