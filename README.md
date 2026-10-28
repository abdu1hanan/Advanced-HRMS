# Human Resource Management System

HRMS Advanced is an enterprise Human Resource Management System built on Laravel. It is designed to streamline employee management, handle leave requests and approvals, track daily attendance, and integrate with Slack for automated check-in and presence tracking.

---

## Tech Stack

- **Backend**: Laravel (PHP 8.3+)
- **Frontend**: Blade, Tailwind CSS, Bootstrap 5, Alpine.js, jQuery
- **Database**: MySQL
- **Integrations**: Slack Webhook API

---

## Features

- **Role-Based Dashboards**: Dedicated views and permissions for Super Admins, Line Managers, and Employees.
- **Organization Hierarchy**: Visual organization tree displaying company leadership, departments, and reporting lines.
- **Leave Management**: Submit, track, and review leave requests with customizable leave types, balance tracking, and manager approvals.
- **Attendance & Holidays**: Daily session tracking (check-in, check-out, breaks), unexcused absence detection, and company holiday management.
- **Slack Attendance Integration**: Touchless attendance sync via Slack webhooks with automated message parsing ("signin", "signout", "brb", "back") and user mapping.
- **Time Override Requests**: Review and approve manual session correction requests with full audit history.
- **Notifications**: In-app and email notifications for leave status changes, pending approvals, and system alerts.

---

## Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- Node.js & NPM
- MySQL

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/abdu1hanan/HRMS-Advanced.git
   cd HRMS-Advanced
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update `.env` with your database credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

4. **Run migrations and seed default data**:
   ```bash
   php artisan migrate --seed
   ```

5. **Start the local server**:
   ```bash
   php artisan serve
   ```
   Open `http://127.0.0.1:8000` in your browser.

---

## Default Credentials

Log in using any of the default seeded Super Admin accounts:

| Role | Email | Password |
|---|---|---|
| **CEO** | `ceo@company.com` | `admin` |
| **CTO** | `cto@company.com` | `admin` |
| **HR** | `hr@company.com` | `admin` |

---

## Customizing Company Logo

Replace `public/images/logo.png` with your company logo (recommended 65x65 px square image).
