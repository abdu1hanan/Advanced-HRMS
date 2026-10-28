# BeetleOps HRMS — Project Knowledge Pack
## 09-FRONTEND.md: Frontend Architecture & User Interface

---

### 1. Frontend Architectural Overview

BeetleOps HRMS implements a **Hybrid Server-Rendered UI** built with Laravel Blade templates and enriched with modern client-side reactive components:
- **Styling**: Bootstrap 5.3 + Tailwind CSS + Custom CSS (`public/css/custom.css`).
- **Aesthetic Theme**: Dark Spotify Glassmorphism theme (deep dark backgrounds `#121212`, elevated card surfaces `#181818`, neon green accent `#1db954`, subtle glass borders `rgba(255, 255, 255, 0.08)`, and an inverted black-and-white mode stored in `localStorage`).
- **Interactivity**: Alpine.js for lightweight dropdown/modal toggling, jQuery for dynamic AJAX submissions, and specialized vanilla JavaScript scripts (`public/js/*`) for organizational tree visualization and notifications.
- **Asset Pipeline**: Vite 8 + PostCSS + Autoprefixer (`package.json`).

---

### 2. Layout Hierarchy & Component Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php             # Master authenticated layout (sidebar, topbar, toast alerts)
│   ├── guest.blade.php           # Public/unauthenticated layout (login, register, forgot password)
│   ├── navbar.blade.php          # Shared header (logo, notification bell, user profile dropdown)
│   ├── nav-super-admin.blade.php # Sidebar navigation links for Super Administrators
│   └── nav-admin.blade.php       # Sidebar navigation links for Staff & Line Managers
├── components/                   # Reusable Blade UI primitives
│   ├── modal.blade.php           # Accessible modal container with backdrop blur
│   ├── text-input.blade.php      # Styled input fields
│   ├── primary-button.blade.php  # Emerald / Spotify-green action buttons
│   ├── danger-button.blade.php   # Crimson warning/delete buttons
│   └── input-error.blade.php     # Validation error feedback
```

---

### 3. Core JavaScript Modules (`public/js/`)

#### A. Notifications Engine (`public/js/notifications.js`)
- **Unread Badge Polling**: Periodically requests `GET /notifications/unread-count` (interval: 30 seconds) and updates the red bell badge in the top navigation bar.
- **Notification Drawer**: Fetches `GET /notifications` when clicked, rendering up to 50 recent notifications with icons, relative timestamps (`diffForHumans`), and status badges.
- **Actions**:
  - Click to view: Automatically triggers `POST /notifications/{id}/read` and navigates to the target URL resolved by `NotificationController::mapNotificationUrl()`.
  - Toggle Star / Mark: `POST /notifications/{id}/toggle-mark` to keep important items pinned.
  - Mark All Read: `POST /notifications/read-all`.
  - Bulk Actions: Select multiple items to mark read or delete.

#### B. Organization Hierarchy Visualizer (`public/js/org-hierarchy.js` & `hierarchy.js`)
- Powers the interactive Org Chart on `/super-admin/hierarchy` and `/admin/hierarchy`.
- Queries `/super-admin/hierarchy/data` or `/admin/hierarchy/data` for recursive tree JSON.
- Dynamically renders hierarchical node cards with employee avatars, designation badges, live attendance status (In Office, Remote, On Leave, Marked Absent), and subordinate counts.
- Features: Zoom in/out controls, pan navigation, search highlighting, and department filtering.

#### C. Leave Application & Calculations (`public/js/leave-request.js`)
- Client-side validation of leave dates prior to AJAX submission.
- Dynamically computes and displays the estimated working days count as the user selects dates, automatically excluding Saturdays and Sundays.
- Live balance deduction preview: warns the employee immediately if the selected date range exceeds their remaining available quota for the selected leave type.

#### D. Leave Review & Comment Modal (`public/js/leave-review.js`)
- Embedded in manager and super admin leave dashboards.
- Fetches discussion thread comments asynchronously.
- Provides forms to approve, reject, or comment on leave requests with optional hidden internal notes for management.

---

### 4. Custom Client-Side Form Enhancements (`layouts/app.blade.php`)

#### A. Dynamic Searchable Selects (`makeSearchableSelect`)
Native HTML `<select>` elements for selecting users, managers, or employees are automatically transformed into searchable, keyboard-navigable combobox widgets:
- Supports text filtering on employee names, codes, and departments.
- Automatically synchronizes with the underlying `<select>` element and observes DOM mutations (`MutationObserver`) when option lists reload via AJAX.

#### B. Real-Time Phone Number Sanitizer
All inputs of type `tel` or named `phone` / `emergency_number`:
- Restricts keystrokes in real time to valid digits, hyphens, parentheses, and spaces.
- Preserves a leading `+` symbol for international dial codes while stripping all subsequent `+` signs.

---

### 5. Page-to-Backend Execution Map

| Page / Route Name | Primary Blade Template | Interactive Components / JS | Backend Endpoints Called |
|---|---|---|---|
| `admin.dashboard` | `resources/views/admin/dashboard.blade.php` | Leave stats cards, quick apply modal | `GET /admin/dashboard`, `POST /admin/account/request-activation` |
| `admin.leaves.record` | `resources/views/employee/record.blade.php` | `leave-request.js`, balance tables, cancel modal | `POST /admin/leave-requests`, `POST /admin/leave-requests/{id}/cancel` |
| `admin.track-record` | `resources/views/employee/record.blade.php` | Daily session segments, monthly datepicker | `GET /admin/track-record/api`, `GET /admin/track-record/export` |
| `admin.team.leaves` | `resources/views/admin/team/leaves.blade.php` | `leave-review.js`, approval modal | `POST /admin/leave-requests/{id}/approve`, `reject` |
| `super-admin.hierarchy` | `resources/views/super-admin/hierarchy/index.blade.php` | `org-hierarchy.js`, SVG tree, department filter | `GET /super-admin/hierarchy/data` |
| `super-admin.session-management.overrides` | `resources/views/super-admin/session-management/overrides.blade.php` | Overrides table, side-by-side time badges | `POST /super-admin/session-management/messages/{id}/approve`, `reject`, `reset` |
| `super-admin.salary.estimations` | `resources/views/super-admin/salary/estimations.blade.php` | Inline salary editing, commission updater | `GET /super-admin/salary-estimations/api/data`, `POST /super-admin/salary-estimations/update-salary` |
| `super-admin.salary-slips.index` | `resources/views/super-admin/salary-slips/index.blade.php` | Modal preview, Browsershot PDF dispatcher | `GET /super-admin/salary-slips/preview`, `POST /super-admin/salary-slips/send-email` |
