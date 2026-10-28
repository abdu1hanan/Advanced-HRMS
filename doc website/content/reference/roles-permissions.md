---
title: Roles & Permissions
category: Reference
description: Complete permission matrix showing what each role can access and do in BeetleOps HRMS.
---

## Permission matrix

| Feature / Page | Employee | Manager | Super Admin |
|----------------|:--------:|:-------:|:-----------:|
| **Dashboard** | Own | Own + team stats | System-wide |
| **Apply for Leave** | ✓ (dashboard) | ✓ (dedicated page) | ✓ |
| **Approve Leave** | — | Own team | All users |
| **My Attendance & Logs** | Own | Own | Own |
| **Team Directory & Logs** | — | Own team | All users |
| **Pending Leaves** | — | Own team | — |
| **Leave History** | — | Own team | All users |
| **Track Records** | Own (read) | Team (read) | All (edit + export) |
| **User Management** | — | — | ✓ |
| **Departments** | — | — | ✓ |
| **Leave Types** | — | — | ✓ |
| **Leave Settings** | — | — | ✓ |
| **All Leave Requests** | — | — | ✓ |
| **Attendance Management** | — | — | ✓ |
| **Holiday Management** | — | — | ✓ |
| **Beetle Sync** | — | — | ✓ |
| **Slack User Mapping** | — | — | ✓ |
| **Employee Track Record** | — | — | ✓ |
| **Profile** | ✓ | ✓ | ✓ |
| **Notifications** | ✓ | ✓ | ✓ |
| **Activity Log** | ✓ | ✓ | ✓ |

## Role definitions

| Role | Database value | Description |
|------|---------------|-------------|
| Employee | `employee` | Self-service — own leave, attendance, track record |
| Admin (Manager) | `admin` | Team supervision — approve leaves, view team data |
| Super Admin | `super_admin` | Full system access — configure everything |

## How roles are assigned

Super Admins assign roles when creating or editing users in **User Management**. A user's role determines which sidebar items and pages they see after login.

## Manager hierarchy

- Each user has a **Line Manager** field (except top-level admins)
- Managers see only employees who report directly to them
- Leave requests route to the assigned Line Manager automatically
