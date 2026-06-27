# Human Resource Management System

A modern, fully-featured Human Resource Management System (HRMS) built with **Laravel**, **Blade**, **Bootstrap 5**, **jQuery/AJAX**, and **MySQL**. It features an elegant dark glassmorphism dashboard, granular role-based permissions, automated department hierarchies, and dynamic leave request/approval workflows.

Perfect as a ready-to-use internal tool or a standout project for your resume.

---

## Quick Start Guide

### 1. Database Setup
Create a new MySQL database for the project (e.g., using DBeaver, phpMyAdmin, or terminal):
```sql
CREATE DATABASE <YOUR-DB-NAME> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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

- **Dashboard Role Parity & Hierarchies**: Custom UI panels tailored specifically for Super Admins, Line Managers, and Employees to preserve clean data segregation, combined with an automated organizational tree that handles dynamic reporting lines and leadership updates seamlessly.
- **Real-Time Communication Sync**: Webhook-driven integration with company messaging platforms that transforms chat check-ins instantly into structured attendance records, featuring automated profile handle mapping and intelligent text-parsing triage.
- **Self-Healing Session Management**: Advanced attendance ledger supporting multi-shift days, automatic shift-splitting for late night hours crossing past midnight, and dynamic multi-day gap repair logic designed to eliminate compounding tracking errors.
- **Automated Leave Workflows**: End-to-end asynchronous leave pipelines running from initial employee submission through multi-tier approvals, backed by real-time inline discussion boards and scheduled tasks to manage annual balance rollovers.
- **Administrative Overhaul Suite**: Interactive inline grid utilities that toggle static data listings into live input forms on the fly, high-density historical dataset filtering, and a strict database soft-deletion standard to prevent accidental data loss.
