# BeetleOps HRMS — Project Knowledge Pack
## 11-INTEGRATIONS.md: External Integrations & Third-Party Services

---

### 1. External Integrations Matrix

| Integration | Primary Purpose | Adapter / Service Location | Key Configuration Variables | Authentication Mechanism |
|---|---|---|---|---|
| **Slack Events Webhook** | Ingest employee check-in, check-out, break, and edit messages | `app/Http/Controllers/SlackWebhookController.php` | `SLACK_SIGNING_SECRET` | HMAC-SHA256 signature verification (`X-Slack-Signature`) |
| **Slack Bot REST API** | Send automated DMs for break warnings, forgotten signouts, typos | `app/Services/SlackAlertService.php` | `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL` | Bearer Token (`Authorization: Bearer xoxb-...`) |
| **Headless Chrome (Puppeteer)** | Generate pixel-perfect A4 PDF salary slips from Blade templates | `app/Http/Controllers/SuperAdmin/SalarySlipController.php` via `spatie/browsershot` | `NODE_PATH`, `NPM_PATH`, `CHROME_PATH`, `NODE_MODULES_PATH` | Local binary execution via Node.js CLI process |
| **PhpSpreadsheet** | Export Track Record reports and Salary Estimation sheets to Excel | `SessionManagementController.php`, `SalaryEstimationController.php` | None (Local library) | Local PHP library execution (`PhpOffice\PhpSpreadsheet`) |
| **SMTP / Mail Server** | Deliver salary slips and password reset emails | `app/Mail/SalarySlipMail.php`, `AppServiceProvider.php` | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `SALARY_SLIP_CC_EMAILS` | Standard SMTP / Mailgun / Postmark / SES authentication |

---

### 2. Integration Deep-Dives

#### A. Slack Ingestion Webhook (`SlackWebhookController`)
- **Endpoint**: `POST /webhook/slack`
- **Configuration**:
  - `SLACK_SIGNING_SECRET`: Secret key provided by Slack App dashboard.
  - `SLACK_LEAVE_SYNC_CHANNEL_ID`: Channel ID for syncing leave status announcements.
- **Security & Validation**:
  - Retrieves `X-Slack-Request-Timestamp` and `X-Slack-Signature` headers.
  - Mitigates replay attacks by rejecting requests where `abs(time() - timestamp) > 300`.
  - Computes `v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$rawBody}", $signingSecret)` and verifies via `hash_equals()`.
- **Payload Handling**:
  - Handshake: Automatically responds to `url_verification` events with `{"challenge": "..."}`.
  - Subtype Filtering: Ignores `bot_message`, `channel_join`, `channel_leave`, `tombstone`.
  - Action Dispatch: Routes `message_changed` to edit tracking, `message_deleted` to deletion purging and auto-append healing, and standard messages to keyword parsing and session creation.

#### B. Slack Bot DM Dispatcher (`SlackAlertService`)
- **Endpoint**: `POST https://slack.com/api/chat.postMessage`
- **Configuration**:
  - `SLACK_BOT_USER_OAUTH_TOKEN`: Bot User OAuth token starting with `xoxb-`.
- **Calling Modules**:
  - `SendIncompleteCycleReminders`: Dispatches alerts at 12:00 PM for unclosed shifts from yesterday.
  - `SlackWebhookController`: Dispatches alerts when an employee types an unrecognized session keyword typo (e.g., `sgnin`), when an employee enters their break grace period, or when a break exceeds the daily allowance.
  - Queued Jobs (`BreakBalanceWarningJob`, `BreakBalanceAlertJob`): Sends timed warnings at 10 minutes and 5 minutes before break balance exhaustion.
- **Failure & Retry Handling**:
  - Wraps API calls in `try-catch` blocks.
  - Checks `$response->successful() && ($response->json('ok') ?? false)`.
  - Logs warnings on failure (`Log::error("SlackAlertService: Failed to send DM...")`); does not interrupt the main HTTP thread.

#### C. Headless Chrome PDF Generation (`Spatie\Browsershot`)
- **Location**: `app/Http/Controllers/SuperAdmin/SalarySlipController.php::sendEmail()`
- **Configuration**:
  ```env
  NODE_PATH=/usr/local/bin/node          # Path to Node.js executable
  NPM_PATH=/usr/local/bin/npm            # Path to npm executable
  CHROME_PATH=/usr/bin/google-chrome     # Path to Chromium or Google Chrome binary
  NODE_MODULES_PATH=/path/to/node_modules# Path to local node_modules
  ```
- **Execution Workflow**:
  1. Renders `resources/views/super-admin/salary-slips/preview-page.blade.php` to an HTML string.
  2. Passes HTML string to `Browsershot::html($html)`.
  3. Applies print options: `noSandbox()`, `waitUntilNetworkIdle()`, `format('A4')`, `showBackground()`, `margins(0, 0, 0, 0)`.
  4. Executes Node script via Puppeteer to render and return the raw binary PDF stream.
- **Failure Handling**:
  - If Headless Chrome fails (e.g., missing Chromium dependencies or invalid paths), catches `\Throwable`, logs detailed errors, and returns a `500 JSON` error to the UI.

#### D. Email Dispatcher (`SalarySlipMail`)
- **Transport**: Configured via Laravel's `config/mail.php` (`smtp`, `log`, `ses`, `postmark`, or `resend`).
- **Configuration**:
  - `SALARY_SLIP_CC_EMAILS`: Comma-separated list of executive email addresses to CC on all employee salary slip deliveries.
- **Workflow**:
  - Attaches generated PDF binary data via `Attachment::fromData()`.
  - Determines dynamic subject line based on included pages (`Salary Slip and Hours Summary for {Month} {Year}`).
  - Dispatches email to employee's `salary_slip_email` (or regular `email`) with CC addresses validated via `FILTER_VALIDATE_EMAIL`.
