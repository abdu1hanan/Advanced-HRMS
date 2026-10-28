# Revamp Project Workflow & Execution Rules

## Core Principles
1. **Frontend-Only Scope**:
   - NEVER alter, break, or remove backend logic, controllers, routes, Eloquent models, form submission actions, AJAX URLs, or hidden input parameters.
   - Preserved functionality: All forms, buttons, drop-downs, AJAX calls, filters, and dynamic Blade tags (`{{ ... }}`, `@if`, `@foreach`, `@can`, etc.) MUST retain 100% of their operational behavior.

2. **One Page at a Time**:
   - Complete and verify a single page redesign fully before moving to the next.
   - Each page redesign must be tested against existing data hooks and verified.

3. **Global Design System Consistency**:
   - All styling MUST derive from central design system tokens (CSS variables in `public/css/revamp-theme.css` / global stylesheet).
   - Component patterns (Cards, Stats, Headers, Tables, Modals, Action Buttons, Badges) must strictly follow the system design documented in `.agents/rules/design_system.md`.

4. **Continuous Learning & Rule Updating**:
   - Whenever a mistake, edge case, or user feedback occurs, immediately record the lesson learned in this file under the "Lessons Learned & Retrospective" section.

5. **Commit Message Rules**:
   - Keep commit messages simple, plain, and human-written. Avoid AI buzzwords/slop (e.g. use `update user directory page`, `add ignore rules`, `fix holiday card styling`).

---

## Page Redesign Checklist
- [ ] Inspect existing Blade template for all data variables, routes, AJAX endpoints, modals, and conditional logic.
- [ ] Implement design mockup using global CSS design tokens and reusable UI component standards.
- [ ] Verify form fields, names, action URLs, CSRF tokens, and method directives are intact.
- [ ] Confirm layout responsiveness (Desktop, Laptop, Mobile).
- [ ] Verify zero regression on backend features.

---

## Lessons Learned & Retrospective
- **Rule File Isolation**: Put `.agents` and `.gemini` in `.gitignore` so local instruction/rule files are never pushed to git.
- **Commit Style**: Use short, plain, natural commit messages. Avoid verbose AI phrasing.
- **Dashboard Neumorphism**: Used `public/css/dashboard-light.css` as the global stylesheet with `.soft-card`, `.kpi-card`, `.btn-neumorphic`, `.absence-pill`, and `.status-pill` classes. Preserved live `$recentRequests` stream at bottom with exact route filter parameters.
- **Git Merge Flag**: Always advise `git merge origin/main --no-edit` to prevent Git from opening the Vim text editor.
- **Pure App-Shell Layout**: For the light redesign, eliminate Bootstrap column/body background overrides and use the standalone `.app-shell` + `.sidebar` + `.app-main` + `.topbar` container architecture from `HRMS-light-design`.
- **Heading Typography Thickness**: Heading font-weight must be `400` (regular) to `500` (medium) with `letter-spacing: -0.035em` on `Manrope` to match the exact slim, geometric aesthetic of the Northstar mockup rather than thick bold.
- **Full Sidebar Navigation**: Retain all 11 Super Admin navigation items with clean indented expandable submenus for Attendance and Slack & Sessions.
- **Pinned Sidebar Headers**: The `.sidebar` has `overflow: hidden; display: flex; flex-direction: column;` while the `<nav>` has `flex: 1; overflow-y: auto;` so the logo brand and footer stay permanently pinned when scrolling.
- **Windows Shortcuts & Real Data**: The topbar includes `<kbd>Ctrl K</kbd>` which focuses the search bar on keydown and displays real system database users dynamically.
