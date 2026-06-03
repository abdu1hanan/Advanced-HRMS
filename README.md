# HRMS

A modern, fully-featured Human Resource Management System (HRMS) built with **Laravel**, **Blade**, **Bootstrap 5**, **jQuery/AJAX**, and **MySQL**. It features an elegant dark glassmorphism dashboard, granular role-based permissions, automated department hierarchies, and dynamic leave request/approval workflows.

Perfect as a ready-to-use internal tool or a standout project for your resume.

---

## Quick Start Guide

### 1. Database Setup
Create a new MySQL database for the project (e.g., using DBeaver, phpMyAdmin, or terminal):
```sql
CREATE DATABASE hrms_beetleops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configure Environment
Clone the repository and copy the environment template to create your `.env` file:
```bash
cp .env.example .env
```
Open `.env` and fill in your database details:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

### 3. Install & Initialize
Run the following commands in your terminal to install dependencies, run migrations, and seed the database with initial roles, department structures, and super-admin credentials:
```bash
composer install
npm install && npm run build
php artisan migrate --seed
```

### 4. Run the Application
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

- **Dashboard Role Parity**: Custom UI panels tailored specifically for Super Admins, Admin/Line Managers, and Employees.
- **Dynamic Leave System**: Full workflow from application (AJAX form) to manager approval and history tracking.
- **Manager-Employee Hierarchy**: Automatic linking of line managers to department employees.
- **Interactive Data Tables**: Dynamic server-side pagination, filters, and records.
