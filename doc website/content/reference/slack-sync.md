---
title: Attendance & Slack Sync
category: Reference
description: How Slack messages map to attendance sessions in BeetleOps HRMS.
---

## Slack to HRMS mapping

Beetle Sync listens for specific keywords in Slack messages and updates employee session records accordingly.

## Keyword reference

| Slack message | HRMS action | Session status |
|--------------|-------------|----------------|
| signin, sign in, checkin, check in | Check in | Active |
| signout, sign out, checkout, check out | Check out | Off Duty |
| brb, break, on break | Start break | On Break |
| back, im back, back from break | End break | Active |

## Override messages

Employees can request time corrections by including a time in their message:

```
signin at 10:00 am
signout at 6:30 pm
```

These create **override requests** in the Beetle Sync approval queue. Super Admins approve or reject them.

## How messages are processed

1. Employee sends a message in the configured Slack channel
2. Slack webhook delivers the message to BeetleOps (`POST /webhook/slack`)
3. System verifies the webhook signature for security
4. Slack user is matched to an HRMS account via **Slack User Mapping**
5. Message text is parsed for keywords and optional time overrides
6. Session table is updated with the new status and timestamp

## Security

- All incoming Slack webhooks are verified using a **signing secret**
- Invalid or spoofed requests are rejected
- Only mapped Slack users can update attendance

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Message not recorded | Check Slack User Mapping — user may be unmapped |
| Wrong employee updated | Fix the mapping in Slack User Mapping |
| Override not applied | Super Admin must approve it in Beetle Sync |
| Session time incorrect | Employee sends override message with correct time |
