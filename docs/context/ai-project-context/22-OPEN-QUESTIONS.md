# BeetleOps HRMS — Project Knowledge Pack
## 22-OPEN-QUESTIONS.md: Unresolved Architectural & Operational Questions

---

### 1. Overview

While the source code and configuration files reveal the application's implementation details, certain external operational factors, business policy clarifications, and infrastructure configurations cannot be verified from code inspection alone.

---

### 2. Operational & Infrastructure Questions

#### 1. Production Queue Worker Implementation
- **Context**: The application relies heavily on queued delay jobs (`BreakBalanceAlertJob`, `BreakGracePeriodAlertJob`) and delayed Slack notifications.
- **Question**: How is the Laravel queue worker executed and monitored in production? Is it managed via Supervisor, systemd, or a container task?
- **Risk if Unknown**: If `queue:work` is not actively running as a persistent daemon in production, delayed break warnings and grace period alerts will never execute.

#### 2. Headless Chrome Binary in Production
- **Context**: `SalarySlipController::sendEmail()` invokes `spatie/browsershot`, which defaults to Puppeteer or system Chromium via environment variables (`CHROME_PATH`, `NODE_PATH`).
- **Question**: What is the exact deployment host operating system and path to Google Chrome / Chromium in production? Are the necessary Linux shared libraries (`libnss3`, `libatk`, `libgbm1`) provisioned?
- **Risk if Unknown**: Attempting to send salary slip emails in an environment lacking Chrome or its shared libraries throws fatal exceptions.

#### 3. Single-Server vs Multi-Server / Container Deployment
- **Context**: Several critical runtime settings (`salary_deduction_settings.json`, `break_settings.json`, `slack_auto_connect_setting.json`) and uploaded letterhead images (`public/images/salary_slip_letterhead.png`) are stored directly on the local filesystem.
- **Question**: Is the production application deployed on a single dedicated virtual machine, or across multiple autoscaling containers / serverless instances?
- **Risk if Unknown**: If deployed in multi-container or autoscaled environments without a shared network volume (NFS/EFS), changes made to break settings or deduction rules on one container will not propagate to others.

---

### 3. Business Logic & Policy Questions

#### 4. Half-Day Leave Spanning Multiple Days
- **Context**: Migration `2026_09_15_170000_add_is_half_day_to_leave_types_table.php` added the `is_half_day` flag, and `LeaveRequestService::submit()` multiplies total working days by `0.5`.
- **Question**: Is an employee allowed to apply for a multi-day span of half-day leaves (e.g., Monday through Friday resulting in 2.5 days), or was the feature intended to enforce single-day applications (`start_date == end_date`)?
- **Risk if Unknown**: Multi-day fractional leaves create visual ambiguities on monthly attendance sheets and track records.

#### 5. Slack Channel Topography
- **Context**: `SlackWebhookController` listens to all message events from any channel the Slack Bot is invited into.
- **Question**: Is attendance tracking restricted to a single designated channel (e.g., `#attendance`), or are employees allowed to post `signin` / `break` messages in multiple departmental channels?
- **Risk if Unknown**: If the bot is invited into general discussion channels, casual messages containing keywords (e.g., "let's take a break") could trigger unintended session events.
