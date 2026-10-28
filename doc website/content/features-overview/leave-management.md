---
title: Leave Management
category: Features Overview
description: How leave requests work in BeetleOps — from applying to approval, with custom leave types and balance tracking.
---

## What is Leave Management?

Leave Management handles the entire process of requesting, approving, and tracking time off. Employees apply for leave, managers approve or reject, and the system keeps everyone's balances up to date.

## Key concepts

| Term | Meaning |
|------|---------|
| Leave Type | Category of leave — Casual, Sick, Unpaid, Absent, etc. |
| Leave Balance | Number of days remaining for each leave type this year |
| Leave Request | A formal application for time off with start and end dates |
| Line Manager | The person who approves your leave (your direct boss) |

## How the leave flow works

1. **Employee applies** — selects leave type, dates, and submits
2. **System validates** — checks for overlapping dates and sufficient balance
3. **Routes to manager** — request appears in manager's Pending Leaves
4. **Manager decides** — approves, rejects, or requests changes
5. **Employee notified** — bell icon alert and optional email
6. **Balance updated** — approved days deducted from remaining balance

## Leave types

Super Admins configure leave types. Common examples:

- **Casual Leave** — general personal time off
- **Sick Leave** — medical or health-related absence
- **Unpaid Leave** — time off without pay
- **Absent** — unexcused absence (often auto-marked by system)

Each type can have different yearly limits and rules.

## Who does what

| Action | Employee | Manager | Super Admin |
|--------|----------|---------|-------------|
| Apply for leave | ✓ | ✓ | ✓ |
| Approve/reject team leave | — | ✓ | ✓ (all users) |
| Configure leave types | — | — | ✓ |
| Set company entitlements | — | — | ✓ |
| View all leave history | — | Team only | ✓ (everyone) |

## Related pages

- Employee: **Applying for Leave**, **My Dashboard**
- Manager: **Pending Leaves**, **Leave History**
- Super Admin: **Leave Types**, **Leave Settings**, **All Leave Requests**

See **Leave Workflow** in Reference for a detailed diagram.
