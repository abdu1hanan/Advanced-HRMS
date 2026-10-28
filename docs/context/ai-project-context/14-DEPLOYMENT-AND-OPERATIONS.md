# BeetleOps HRMS — Project Knowledge Pack
## 14-DEPLOYMENT-AND-OPERATIONS.md: Build, Deployment & Operations Guide

---

### 1. Production Runtime Prerequisites

| Component | Minimum Version | Operational Role |
|---|---|---|
| **PHP Runtime** | PHP 8.3+ | CLI and PHP-FPM execution with `bcmath`, `curl`, `mbstring`, `pdo_mysql`, `gd`, `zip` extensions |
| **Database** | MySQL 8.0+ | Relational data persistence with `utf8mb4` encoding |
| **Node.js** | Node.js v18+ & NPM v9+ | Asset bundling (Vite) and Headless Chrome execution (Puppeteer) |
| **Headless Chrome** | Chromium / Google Chrome | Required by `spatie/browsershot` for rendering salary slip PDFs |
| **Process Manager** | Supervisor or Systemd | Keeps queue workers and background daemons alive |
| **Web Server** | Nginx or Apache | Reverse proxy terminating SSL, serving `/public`, proxying PHP-FPM |

---

### 2. Standard Production Deployment Lifecycle

When deploying updates to production, execute the following pipeline:

```bash
# 1. Enter maintenance mode
php artisan down --render="errors::503" --secret="<DEPLOYMENT_BYPASS_TOKEN>"

# 2. Pull latest code from repository
git pull origin main

# 3. Install optimized PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Execute database migrations
php artisan migrate --force

# 5. Build optimized frontend assets
npm ci
npm run build

# 6. Rebuild Laravel framework caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Restart background queue workers
php artisan queue:restart

# 8. Clear maintenance mode
php artisan up
```

---

### 3. Required Background Daemons & Process Management

A functioning BeetleOps HRMS instance requires three concurrent runtime processes:

#### Process 1: Web Server & PHP-FPM
Serves the HTTP web interface and accepts incoming Slack webhook callbacks (`/webhook/slack`).
- **Nginx FastCGI Root**: `/path/to/hrms/public`
- **Request Timeout**: Minimum 60 seconds (for PDF generation or large Excel exports).

#### Process 2: Laravel Queue Worker (`queue:work`)
Processes queued delay jobs (`BreakBalanceWarningJob`, `BreakGracePeriodAlertJob`, `BreakBalanceAlertJob`).
- **Command**:
  ```bash
  php /path/to/hrms/artisan queue:work database --sleep=3 --tries=3 --timeout=90
  ```
- **Supervisor Configuration Sample** (`/etc/supervisor/conf.d/hrms-worker.conf`):
  ```ini
  [program:hrms-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=php /path/to/hrms/artisan queue:work database --sleep=3 --tries=3 --timeout=90
  autostart=true
  autorestart=true
  stopasgroup=true
  killasgroup=true
  user=www-data
  numprocs=2
  redirect_stderr=true
  stdout_logfile=/path/to/hrms/storage/logs/worker.log
  stopwaitsecs=3600
  ```

#### Process 3: Cron Scheduler (`schedule:run`)
Executes daily notification table pruning and the 12:00 PM incomplete cycle reminder.
- **System Crontab Entry** (`crontab -e -u www-data`):
  ```cron
  * * * * * cd /path/to/hrms && php artisan schedule:run >> /dev/null 2>&1
  ```

---

### 4. Local Development Startup

For local engineering work, the project provides an integrated `composer dev` script that launches all services concurrently:

```bash
# Defined in composer.json
composer dev
```

This runs `npx concurrently` with color-coded logs:
1. `php artisan serve`: Local web server on `http://127.0.0.1:8000`.
2. `php artisan queue:listen --tries=1 --timeout=0`: Local queue runner.
3. `php artisan pail --timeout=0`: Real-time application log tailer.
4. `npm run dev`: Vite development server with Hot Module Replacement (HMR).

---

### 5. Headless Chrome / Puppeteer Configuration for Linux

Because `spatie/browsershot` relies on Puppeteer, Linux server environments must have necessary Chromium shared libraries installed:

```bash
# Ubuntu/Debian server dependencies
apt-get update && apt-get install -y \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libpango-1.0-0 \
    libcairo2 \
    libasound2
```

In `.env`, ensure the Node and Chrome paths point to the server binaries:
```env
NODE_PATH=/usr/bin/node
NPM_PATH=/usr/bin/npm
CHROME_PATH=/usr/bin/google-chrome
```
