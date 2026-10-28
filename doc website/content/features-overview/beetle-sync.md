---
title: Beetle Sync
category: Features Overview
description: Slack integration that automatically records employee attendance from chat messages.
---

## What is Beetle Sync?

Beetle Sync is BeetleOps' Slack integration. It listens for attendance-related messages in Slack and automatically updates employee session records in HRMS — no manual check-in required.

## How it works

1. Employee sends a message in Slack (e.g. "signin" or "signout")
2. Slack sends the message to BeetleOps via a **webhook**
3. Beetle Sync parses the message and identifies the employee
4. The session table is updated with the correct status and timestamp

## Supported Slack keywords

| Message | Action |
|---------|--------|
| signin, sign in, checkin | Check in (Active) |
| signout, sign out, checkout | Check out (Off Duty) |
| brb, break | Start break (On Break) |
| back, im back | Return from break (Active) |

The system uses intelligent matching — variations like "signin at 10:00 am" are also recognized.

## Session overrides

Sometimes an employee needs to correct a session time. They can send a modified message like:

```
signin at 10:00 am
```

This creates an **override request** that goes to the Super Admin approval queue in **Beetle Sync**.

## Slack user mapping

For Beetle Sync to work, each Slack user must be linked to their HRMS account. Super Admins manage this from **Slack User Mapping**. The system can also auto-match users by name.

## Who manages Beetle Sync

Only **Super Admins** can:

- Approve or reject session override requests
- Manage Slack user mappings
- View the override approval queue

## Related pages

- Super Admin: **Beetle Sync**, **Slack User Mapping**
- Reference: **Attendance & Slack Sync**, **Glossary**
