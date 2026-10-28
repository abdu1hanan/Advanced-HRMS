---
title: Attendance & Holidays
category: Features Overview
description: How BeetleOps tracks daily attendance, marks absences, and handles company holidays.
---

## What is Attendance Management?

Attendance Management tracks whether employees are present, absent, on break, or off duty each day. Sessions are recorded through Slack (Beetle Sync) or manually by Super Admins.

## Session statuses

| Status | Meaning |
|--------|---------|
| Active | Employee is checked in and working |
| On Break | Employee stepped away temporarily |
| Absent | Employee did not check in |
| Off Duty | Employee checked out for the day |

## How attendance is recorded

1. **Slack messages (automatic)** — employees send "signin", "signout", "brb", "back" in Slack
2. **Beetle Sync** — parses messages and updates session tables
3. **Manual marking (Super Admin)** — mark absents, convert to leave, flag remote work
4. **Holidays** — declared holidays automatically adjust attendance for affected dates

## Company holidays

Super Admins declare official company holidays from **Holiday Management**. When a holiday is set:

- Attendance metrics adjust for that date
- Leave calculations account for the holiday
- Employees are not marked absent on holiday dates

## Who does what

| Action | Employee | Manager | Super Admin |
|--------|----------|---------|-------------|
| View own attendance | ✓ | ✓ | ✓ |
| View team attendance | — | ✓ | ✓ |
| Mark absents | — | — | ✓ |
| Declare holidays | — | — | ✓ |
| Convert absent to leave | — | — | ✓ |

## Related pages

- Employee: **My Attendance & Logs**
- Manager: **My Attendance & Logs**, **Team Directory & Logs**
- Super Admin: **Attendance Management**, **Holiday Management**

See **Attendance & Slack Sync** in Reference for Slack keyword details.
