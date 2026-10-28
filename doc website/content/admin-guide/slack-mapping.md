---
title: Slack User Mapping
category: Super Admin Guide
description: Link Slack user accounts to HRMS employee accounts for Beetle Sync attendance.
---

## What is Slack User Mapping?

Slack User Mapping connects Slack user profiles to HRMS employee accounts. Without correct mapping, Beetle Sync cannot attribute Slack messages to the right employee.

## How to reach this page

1. Expand **Session Management** in the sidebar
2. Click **Slack User Mapping**

## How mapping works

- Each Slack user has a unique Slack ID and display name
- Each HRMS user has an account with name and email
- Mapping links the two so "signin" messages update the correct employee's session

## Auto-mapping

The system can automatically match Slack users to HRMS accounts by:

- **Exact name match** — Slack display name matches HRMS name
- **Substring match** — partial name match with normalization

Review auto-mapped entries to ensure accuracy.

## Manual mapping

1. Go to **Slack User Mapping**
2. Find unmapped Slack users in the list
3. Select the corresponding HRMS employee
4. Save the mapping

## Tips

- Map all employees who use Slack for attendance
- Re-check mappings when new employees join
- Incorrect mapping causes attendance to be recorded for the wrong person
- Unmapped users' Slack messages are ignored by Beetle Sync

> 📷 Screenshot coming soon
