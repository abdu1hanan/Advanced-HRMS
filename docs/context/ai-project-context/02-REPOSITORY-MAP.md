# BeetleOps HRMS — Project Knowledge Pack
## 02-REPOSITORY-MAP.md: Repository Map & Directory Layout

---

### 1. Meaningful Repository Tree

```
hrms/
├── .env.example                                  # Environment template with application variables
├── README.md                                     # High-level product description and setup commands
├── artisan                                       # Laravel command-line executable
├── composer.json                                 # PHP dependencies, autoload mappings & scripts
├── composer.lock                                 # Exact pinned PHP dependency lockfile
├── package.json                                  # Node.js dependencies (Vite, Tailwind, Puppeteer)
├── package-lock.json                             # Exact pinned Node.js dependency lockfile
│
├── app/                                          # Core application backend source code
│   ├── Console/Commands/                         # Custom Artisan CLI commands
│   │   ├── RecalculateBreakBalance.php           # Batch recalculates break exceeded flags for past days
│   │   ├── RolloverLeaveBalances.php             # Rolls over yearly balances with carry-forward limits
│   │   ├── SendIncompleteCycleReminders.php      # Daily 12:00 reminder for unclosed previous day shifts
│   │   └── SyncDepartmentHierarchy.php           # Deprecated command for old department hierarchy
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php                    # Base Laravel controller
│   │   │   ├── DashboardController.php           # Root dashboard router based on user role
│   │   │   ├── NotificationController.php        # Real-time bell, drawer, toggle read, and activity log
│   │   │   ├── ProfileController.php             # User profile editing and password updates
│   │   │   ├── SlackWebhookController.php        # 2,010-line Slack event webhook listener & NLP parser
│   │   │   ├── Admin/                            # Staff & Line Manager portal controllers
│   │   │   │   ├── DashboardController.php       # Staff dashboard & activation request dispatcher
│   │   │   │   ├── HierarchyController.php       # Organization chart viewer for staff
│   │   │   │   └── TeamLeaveController.php       # Team directory, attendance logs & subordinate approvals
│   │   │   ├── Auth/                             # Authentication controllers (Breeze scaffolding)
│   │   │   │   ├── AuthenticatedSessionController.php
│   │   │   │   ├── ConfirmablePasswordController.php
│   │   │   │   ├── EmailVerificationNotificationController.php
│   │   │   │   ├── EmailVerificationPromptController.php
│   │   │   │   ├── NewPasswordController.php
│   │   │   │   ├── PasswordController.php
│   │   │   │   ├── PasswordResetLinkController.php
│   │   │   │   ├── RegisteredUserController.php  # Regular & guest registration with approval status
│   │   │   │   └── VerifyEmailController.php
│   │   │   ├── Employee/
│   │   │   │   ├── DashboardController.php       # Legacy/employee dashboard redirector
│   │   │   │   └── LeaveRequestController.php    # Personal leave submission, cancellation & track record
│   │   │   └── SuperAdmin/                       # Executive administration controllers
│   │   │       ├── AccountApprovalController.php # New registration review queue & activation
│   │   │       ├── AttendanceHolidayController.php# Absents, company holidays, remote work & settings
│   │   │       ├── DashboardController.php       # Executive metric overview cards
│   │   │       ├── DepartmentController.php      # CRUD for company departments
│   │   │       ├── DesignationController.php     # CRUD for employee designations
│   │   │       ├── HierarchyController.php       # Master org-chart tree builder & cycle checks
│   │   │       ├── LeaveRequestController.php    # Company-wide leave history, comments & approvals
│   │   │       ├── LeaveSettingsController.php   # Annual leave entitlement defaults & user overrides
│   │   │       ├── LeaveTypeController.php       # Dynamic leave type management (half-day, active)
│   │   │       ├── SalaryEstimationController.php# Monthly payroll estimation, deductions & Excel export
│   │   │       ├── SalarySlipController.php      # PDF generation (Browsershot), emailer & bank details
│   │   │       ├── SessionManagementController.php# 4,181-line session reconstructor & override audit queue
│   │   │       ├── SystemSettingController.php   # Multi-tier approval chain level settings
│   │   │       └── UserController.php            # User lifecycle, manager assignment, compensation
│   │   ├── Middleware/
│   │   │   ├── EnsureAccountApproved.php         # Gates unapproved accounts with 403 / warning
│   │   │   └── EnsureUserHasRole.php             # Enforces role restrictions (super_admin, staff, guest)
│   │   └── Requests/
│   │       ├── Auth/LoginRequest.php             # Login form validation and rate limiting
│   │       └── ProfileUpdateRequest.php          # Profile attribute validation
│   ├── Jobs/                                     # Queued background jobs
│   │   ├── BreakBalanceAlertJob.php              # Fires when break balance is exhausted mid-break
│   │   ├── BreakBalanceWarningJob.php            # Fires 10m and 5m warnings before break exhaustion
│   │   └── BreakGracePeriodAlertJob.php          # Notifies HR when employee enters 15m grace period
│   ├── Mail/
│   │   └── SalarySlipMail.php                    # Mailable sending PDF salary slip with CC support
│   ├── Models/                                   # Eloquent data models (21 models)
│   │   ├── AccountApprovalRequest.php            # User registration review requests
│   │   ├── AttendanceRecord.php                  # Unexcused absent markings
│   │   ├── CompanyHoliday.php                    # Declared official holidays with user/dept targeting
│   │   ├── Department.php                        # Company departments with head_id
│   │   ├── Designation.php                       # Employee job designations
│   │   ├── EmployeeSalarySlipDetail.php          # Employee bank name, account number, IBAN
│   │   ├── LeaveApprovalChain.php                # Individual approver action records (approved/rejected)
│   │   ├── LeaveBalance.php                      # Yearly leave quota, used, pending, carried forward
│   │   ├── LeaveBalanceOverride.php              # Custom entitlement overrides per user/year
│   │   ├── LeaveRequest.php                      # Leave application lifecycle and state
│   │   ├── LeaveRequestComment.php               # Discussion thread comments on leave requests
│   │   ├── LeaveSetting.php                      # Global default entitlements per leave type
│   │   ├── LeaveType.php                         # Types of leave (Casual, Sick, Unpaid, Emergency, etc.)
│   │   ├── RemoteWorkLog.php                     # Logged remote work days (full/half day)
│   │   ├── Role.php                              # User roles (super_admin, staff, guest)
│   │   ├── SlackLeaveSyncMessage.php             # Slack leave sync notification tracking
│   │   ├── SlackMessage.php                      # Raw ingested Slack attendance messages & calculations
│   │   ├── SlackMessageEdit.php                  # Audit log of message text changes in Slack
│   │   ├── SlackUserMapping.php                  # Mapping between Slack user IDs and HRMS users
│   │   ├── SystemSetting.php                     # Global key-value system configurations
│   │   └── User.php                              # Employee entity with manager_id hierarchy relations
│   ├── Notifications/                            # 24 event notification classes (database/mail)
│   ├── Policies/                                 # Authorization gates
│   │   ├── DepartmentPolicy.php                  # Super admin department management
│   │   ├── LeaveRequestPolicy.php                # Subordinate leave review & creation rules
│   │   └── UserPolicy.php                        # Profile view and subordinate directory policies
│   ├── Providers/
│   │   └── AppServiceProvider.php                # Cache clearing model hooks, Gates & mail customizers
│   ├── Rules/
│   │   ├── PhoneNumber.php                       # Strict phone number regex validator
│   │   └── StrictEmail.php                       # Corporate email validation
│   ├── Services/                                 # Domain services encapsulating business logic
│   │   ├── HierarchyService.php                  # BFS tree builder, boss chains, cycle detection
│   │   ├── LeaveApprovalChainService.php         # Multi-level approval routing, progress & reset
│   │   ├── LeaveBalanceService.php               # Balance entitlement, deduction, rollover calculations
│   │   ├── LeaveRequestService.php               # Leave lifecycle, overlap prevention, cancellation
│   │   └── SlackAlertService.php                 # Direct Message dispatcher via Slack Bot OAuth Token
│   └── View/Components/                          # Blade layout components (AppLayout, GuestLayout)
│
├── bootstrap/                                    # Framework initialization
│   ├── app.php                                   # Routing, middleware alias registration & exceptions
│   └── providers.php                             # Service provider list
│
├── config/                                       # Application configuration files
│   ├── app.php, auth.php, cache.php, database.php, filesystems.php,
│   ├── logging.php, mail.php, queue.php, services.php, session.php
│
├── database/                                     # Database migrations, seeders & factories
│   ├── factories/                                # User and Department test factories
│   ├── migrations/                               # 43 chronological schema migration files
│   └── seeders/                                  # Initial data seeders (Roles, SuperAdmins, Leaves)
│
├── doc website/                                  # Public documentation static site
│   ├── index.html                                # Standalone documentation web app
│   ├── content/                                  # Markdown user guides (admin, manager, employee)
│   ├── css/ & js/                                # Custom styles, search, markdown parser, chatbot
│   └── data/navigation.json                      # Sidebar documentation navigation tree
│
├── public/                                       # Web server document root
│   ├── index.php                                 # Single entry point for all HTTP requests
│   ├── css/custom.css                            # Spotify dark glassmorphism stylesheet
│   ├── js/                                       # Client-side JavaScript bundles
│   │   ├── app.js, hierarchy.js, leave-request.js, leave-review.js, notifications.js, org-hierarchy.js
│   └── images/                                   # Logos and salary slip letterhead background
│
├── resources/                                    # Frontend presentation source
│   └── views/                                    # Blade templates
│       ├── admin/                                # Staff and Line Manager portal views
│       ├── auth/                                 # Login, registration, password reset views
│       ├── components/                           # Reusable Blade UI components (buttons, modals)
│       ├── emails/                               # Email templates (Password reset, Salary slip)
│       ├── employee/                             # Employee leave records & dashboard views
│       ├── layouts/                              # Master app layout, navigation bars & sidebar
│       ├── profile/                              # Profile edit partials
│       └── super-admin/                          # Super Admin portal views (leaves, salary, sessions)
│
├── routes/                                       # Application routing definitions
│   ├── auth.php                                  # Authentication routes (login, register, reset)
│   ├── console.php                               # Artisan CLI commands & daily cron schedules
│   └── web.php                                   # Web routes for portals, notifications, and webhook
│
├── scratch/                                      # Ad-hoc development verification scripts
│   └── verify_account_approval_flow.php          # Account approval simulation script
│
└── storage/                                      # Ephemeral logs, framework cache & JSON settings
    └── app/                                      # Flat JSON settings (break_settings.json, etc.)
```

---

### 2. Major Configuration Files

| File | Purpose | Key Details |
|---|---|---|
| `composer.json` | PHP dependency definitions | PHP `^8.3`, Laravel `^13.8`, `phpoffice/phpspreadsheet`, `spatie/browsershot` |
| `package.json` | Frontend assets & Puppeteer | Vite 8, Tailwind CSS, Alpine.js, `puppeteer: ^25.5.0` |
| `.env.example` | Environment variable template | Database, Redis, Mail, Slack tokens, AWS credentials |
| `bootstrap/app.php` | Application kernel configuration | Registers middleware aliases (`role`, `account.approved`), CSRF exemption for `/webhook/slack` |
| `routes/web.php` | Main HTTP route registry | Maps `/dashboard`, `/admin/*`, `/super-admin/*`, `/webhook/slack`, and `/docs/*` |
| `routes/console.php` | Scheduled task registry | Daily notification purge (90 days old) and 12:00 incomplete cycle reminder |
| `config/services.php` | Integration credentials | Slack signing secret, bot OAuth token, leave sync channel, salary slip paths |

---

### 3. Excluded Directories

The following directories exist at the repository root but are intentionally excluded from application logic analysis:
- `node_modules/`: NPM client dependencies.
- `vendor/`: Composer PHP framework and library packages.
- `storage/framework/`: Compiled Blade views, cached sessions, and file-based cache.
- `storage/logs/`: Daily and single application runtime log files.
- `.git/`: Git version control internals.
