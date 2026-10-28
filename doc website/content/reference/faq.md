---
title: FAQ
category: Reference
description: Frequently asked questions about BeetleOps HRMS — quick answers to common issues.
---

## General

### What is BeetleOps HRMS?
A web-based Human Resource Management System for leave tracking, attendance monitoring, and Slack integration.

### How do I log in?
Go to your company's HRMS URL, enter your email and password, and click Sign In. See **First Login** for details.

### I forgot my password. What do I do?
Click **Forgot Password** on the login page, or ask your Super Admin to reset it from User Management.

## Leave

### Why can't I apply for leave?
Common reasons: your balance for that type is zero, you have an overlapping pending request, or the dates are invalid. Check your dashboard balances.

### Who approves my leave?
Your **Line Manager** — the person set as your manager in User Management. If unsure who that is, ask your Super Admin.

### Can I cancel an approved leave?
Contact your manager or Super Admin. Cancellation policies depend on your company's rules.

### Why don't I see "Apply for Leave" in my sidebar?
Employees apply from the **Dashboard**, not a separate menu item. Managers have a dedicated Apply for Leave page.

## Attendance

### How does attendance get recorded?
Primarily through **Beetle Sync** — send "signin" and "signout" in Slack. Super Admins can also mark attendance manually.

### My attendance is wrong. How do I fix it?
Send a Slack override message (e.g. "signin at 10:00 am"). Your Super Admin must approve it in Beetle Sync.

### Why was I marked absent?
You did not check in via Slack and have no approved leave for that day. Contact your Super Admin to convert the absent to leave if applicable.

## Roles & Access

### Why can't I see Team Directory?
Only **Managers** and **Super Admins** can view team data. If you are an Employee, you only see your own pages.

### Why can't I see Session Management?
Session Management (Beetle Sync, Slack Mapping, Track Record admin) is **Super Admin only**.

### How do I become a Manager?
Your Super Admin must change your role to Admin and assign employees to report to you.

## Notifications

### I'm not receiving notifications. Why?
Check the bell icon in the top bar. Email notifications require mail server configuration by your Super Admin.

### How do I mark all notifications as read?
Click the bell icon, then click the checkmark (Mark all read) button in the dropdown.
