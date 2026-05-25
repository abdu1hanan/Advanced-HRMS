# BeetleOps HRMS

Internal Human Resource Management System built with **Laravel**, **Blade**, **Bootstrap 5**, **jQuery/AJAX**, and **MySQL**.

## Requirements

- PHP 8.2+ (with `pdo_mysql` extension)
- Composer
- MySQL 8+
- Node.js (for Breeze assets; optional if using CDN in layout)

## Quick start

### 1. Create MySQL database (DBeaver)

Connect to your MySQL server, open an SQL script, and run:

```sql
CREATE DATABASE hrms_beetleops
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Configure Laravel

Copy credentials into `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms_beetleops
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Migrate and seed

```powershell
cd "d:\BeetlEOPS(Internship)\Work Code\HRMS (BeetleOps)"
php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000

### Default Super Admin

| Field | Value |
|-------|-------|
| Email | `admin@company.com` |
| Password | `admin` |

**Change this password before production deployment.**

### Grant Super Admin manually (database only)

Super Admin cannot be assigned from the User Management UI. To promote a user via DBeaver:

```sql
SELECT id FROM roles WHERE name = 'super_admin';
-- Then update the user (replace USER_ID and ROLE_ID):
UPDATE users SET role_id = ROLE_ID WHERE id = USER_ID;
```

## Soft delete

Users and departments are **soft-deleted** by default. Use **Active / Deleted** filters on User Management and Departments to restore records.

```powershell
php artisan hrms:sync-hierarchy
```

Re-links all employees to their department line managers after bulk head changes.

## Roles

| Role | Access |
|------|--------|
| Super Admin | Users, departments, leave types, global leave settings, all leave history |
| Admin (Line Manager) | Team members, approve/reject leave requests |
| Employee | Own dashboard, apply for leave (AJAX), view balances |

New registrations default to **Employee** and must select a department. If that department has an Admin head assigned, they become the employee's line manager automatically.

## Leave defaults (seeded)

| Type | Days/year | Carry forward |
|------|-----------|---------------|
| Annual | 5 | Yes (max 2) |
| Casual | 10 | No |
| Sick | 5 | No |

Super Admin can edit types, apply defaults to all employees, or set per-employee overrides under **Leave Settings**.

## Year-end rollover

```powershell
php artisan hrms:rollover-leave 2026 2027
```

## Tech stack

- PHP / Laravel 13
- Blade templates
- Bootstrap 5.3 (CDN)
- jQuery 3.7 + AJAX
- MySQL only
