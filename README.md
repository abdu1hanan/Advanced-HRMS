# Human Resource Management System

BeetleOps HRMS is a production-ready, enterprise-grade Human Resource Management System built on the Laravel framework. The system is designed to streamline organizational workflows, automate employee tracking, manage leaves, and integrate seamlessly with external messaging platforms like Slack for automatic attendance mapping.

Featuring a modern dark glassmorphism dashboard, granular role-based access control (RBAC), automatic manager-employee hierarchies, and a robust event-driven notification architecture, BeetleOps HRMS serves as a complete solution for modern organizations.

---

## Technical Stack

The application is built using standard enterprise web technologies:

- **Backend Framework**: Laravel (PHP 8.3+) utilizing MVC architecture, Eloquent ORM, and event-driven notifications.
- **Frontend Architecture**: Blade Templating Engine combined with Tailwind CSS (v3/v4), Bootstrap 5, Alpine.js, and jQuery/AJAX for dynamic asynchronous UI updates.
- **Database**: MySQL 8.0+ storing relational tables with optimized indexing for attendance logging, leave metrics, and system configuration.
- **Integrations**: Slack Webhook API for real-time presence synchronization and automated attendance logging.
- **Asset Bundler**: Vite for building and packaging frontend dependencies.

---

## Core Modules and Features

### 1. Role-Based Access Control (RBAC) and Dashboards
The system enforces strict role-based dashboards with clear separation of duties:
- **Super Administrator Dashboard**: Centralized panel for managing users, updating company-wide leave settings, declaring official holidays, auditing attendance logs, and approving Slack-based time overrides.
- **Admin/Line Manager Dashboard**: Intermediate panel to supervise direct reports, view department leave histories, process leave applications, and track daily team presence.
- **Employee Portal**: Self-service interface for submitting leave requests, monitoring personal leave balances, tracking daily check-in histories, and reviewing notification cards.

### 2. Leave Management Workflow
A robust, validated approval lifecycle:
- **Custom Leave Types**: Dynamic creation of leave structures (e.g., Casual, Sick, Unpaid, and Absent) with distinct rules.
- **Leave Request Process**: Employees apply via an AJAX form. The system dynamically updates the remaining leave balances based on predefined yearly limits.
- **Automated Routing**: Leave requests are automatically routed to the employee's designated line manager for approval or rejection.
- **Conflict Resolution**: Real-time validation checks for overlapping dates and insufficient leave balances.
- **Notifications**: Automated database and mail notifications keep employees informed of changes to their leave request status.

### 3. Attendance and Holiday Management
Comprehensive tracking of employee presence:
- **Daily Sessions**: Automated check-in/check-out session tracking.
- **Presence Log**: Granular status updates including Active, On Break, Absent, and Off Duty.
- **Official Holidays**: Super Admins can declare company holidays, which automatically adjust attendance metrics and leave calculations for affected periods.
- **Absent Markings**: System automation to flag unexcused absences and manage historical attendance records.

### 4. Slack Presence and Attendance Synchronization
A deep integration with Slack to handle touchless attendance tracking:
- **Automated Check-Ins**: Syncs Slack check-in, check-out, break, and back-from-break messages directly to the HRMS session tables.
- **Keyword Normalization**: An intelligent matching engine parses natural phrases (e.g., "signin", "sign out", "brb", "back") to update session states.
- **Slack User Auto-Mapping**: Automatically links Slack user profiles to registered HRMS user accounts based on exact name or normalized substring matching.
- **Session Overrides**: Allows employees to request manual session corrections directly through Slack message modifiers (e.g., "signin at 10:00 am").
- **Audit Logs and Approval Queue**: Override requests are held in a pending state and routed to the Super Admin approval queue, generating real-time notification alerts.
- **Security Check**: Implements strict cryptographic signature verification on incoming Slack webhook payloads using signing secrets to prevent replay and spoofing attacks.

---

## Quick Start and Setup Guide

### 1. Prerequisites
Ensure your local machine or server meets the following requirements:
- PHP 8.3 or higher
- Composer
- Node.js and NPM
- MySQL 8.0 or higher
- Git

### 2. Database Creation
Create a new MySQL database for the application:
```sql
CREATE DATABASE <YOUR-DB-NAME> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Environment Configuration
Copy the sample environment file to create your local `.env`:
```bash
cp .env.example .env
```
Open the `.env` file and configure your database, application, and mail server credentials:
```env
APP_NAME="BeetleOps HRMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=your_db_name
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

### 4. Dependency Installation
Install backend PHP packages and frontend Node modules:
```bash
composer install --optimize-autoloader
npm install
```

### 5. Application Key Generation
Generate the unique application encryption key:
```bash
php artisan key:generate
```

### 6. Database Migrations and Seeding
Run database migrations and seed system tables with essential roles, default departments, initial leave types, and a super admin user:
```bash
php artisan migrate --seed
```

### 7. Compile Frontend Assets
Build production-ready CSS and JS assets:
```bash
npm run build
```

### 8. Run the Application
Start the local development server:
```bash
php artisan serve
```
Open your browser and navigate to: `http://127.0.0.1:8000`

---

## Default Credentials

To explore the dashboard, log in with the default seeded credentials:

* **Email:** `admin@company.com`
* **Password:** `admin`

---

## Customizing the Company Logo

To make this HRMS your own, swap the default placeholder logo with your company logo:

1. Place your custom logo in `public/images/logo.png`.
2. **Recommended Size:** The logo should be **65x65 pixels** (or a square aspect ratio) so it matches the navbar toggle layout and doesn't break the responsive side menu container.

---

## ✨ Features

- **Dashboard Role Parity & Hierarchies**: Custom UI panels tailored specifically for Super Admins, Line Managers, and Employees to preserve clean data segregation, combined with an automated organizational tree that handles dynamic reporting lines and leadership updates seamlessly.
- **Real-Time Communication Sync**: Webhook-driven integration with company messaging platforms that transforms chat check-ins instantly into structured attendance records, featuring automated profile handle mapping and intelligent text-parsing triage.
- **Self-Healing Session Management**: Advanced attendance ledger supporting multi-shift days, automatic shift-splitting for late night hours crossing past midnight, and dynamic multi-day gap repair logic designed to eliminate compounding tracking errors.
- **Automated Leave Workflows**: End-to-end asynchronous leave pipelines running from initial employee submission through multi-tier approvals, backed by real-time inline discussion boards and scheduled tasks to manage annual balance rollovers.
- **Administrative Overhaul Suite**: Interactive inline grid utilities that toggle static data listings into live input forms on the fly, high-density historical dataset filtering, and a strict database soft-deletion standard to prevent accidental data loss.

