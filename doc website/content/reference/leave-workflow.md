---
title: Leave Workflow
category: Reference
description: The complete leave request lifecycle — from application to approval or rejection.
---

## Leave request lifecycle

Here is the full flow of a leave request in BeetleOps HRMS:

<div class="sitemap-tree">Employee submits leave
        |
        v
System validates (dates, balance, overlaps)
        |
        v
Status: Pending  -->  Line Manager notified
        |
        v
Manager opens Pending Leaves
        |
   +----+----+
   v         v
Approved   Rejected
   |         |
   v         v
Balance    Employee
updated    notified
   |
   v
Recorded in Leave History</div>

### Step-by-step detail

1. **Employee applies** — selects leave type, dates, and submits from Dashboard (Employee) or Apply for Leave page (Manager)
2. **System validates** — checks for date overlaps, sufficient balance, and valid leave type
3. **Request created** — status set to "Pending", routed to the employee's Line Manager
4. **Manager notified** — bell icon alert and optional email sent to the line manager
5. **Manager reviews** — opens Pending Leaves, reads details, and decides
6. **Decision made** — Manager clicks Approve or Reject (with optional comment)
7. **Employee notified** — bell icon alert and optional email sent to the employee
8. **Balance updated** — if approved, leave days deducted from the employee's balance
9. **History recorded** — request appears in Leave History for audit

## Status transitions

| From | To | Trigger |
|------|----|---------|
| — | Pending | Employee submits request |
| Pending | Approved | Manager approves |
| Pending | Rejected | Manager rejects |
| Pending | Cancelled | Employee or system cancels |
| Approved | Cancelled | Employee cancels before start date |

## Validation rules

- Cannot apply if leave balance is zero for the selected type
- Cannot apply for overlapping dates with an existing pending or approved request
- Start date must not be in the past (unless policy allows)
- End date must be on or after start date

## Who can act at each step

| Step | Employee | Manager | Super Admin |
|------|----------|---------|-------------|
| Apply | ✓ | ✓ | ✓ |
| Approve/Reject | — | Own team | All users |
| Cancel own request | ✓ | ✓ | ✓ |
| View history | Own | Team | All |
