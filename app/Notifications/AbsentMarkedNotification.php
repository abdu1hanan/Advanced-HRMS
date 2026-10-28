<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AbsentMarkedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $dateFormatted,
        public ?string $notes = null,
        public bool $isSelf = false,
        public ?int $employeeId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->isSelf) {
            $message = "[Absent] {$this->employeeName}: \"You were marked absent on {$this->dateFormatted}" . ($this->notes ? " ({$this->notes})" : '') . ".\"";
        } else {
            $message = "[Absent] {$this->employeeName}: \"Marked absent on {$this->dateFormatted}" . ($this->notes ? " — {$this->notes}" : '') . ".\"";
        }

        // isSelf = true  → the notifiable themselves was marked (could be employee or line manager)
        // isSelf = false → a subordinate was marked; notifiable is always the line manager
        if ($this->isSelf) {
            // Employee → their own record; Line Manager → their own record (admin side)
            // All staff use admin routes
            $url = route('admin.leaves.record', ['type' => 'absent'], false);
        } else {
            // Always the line manager being notified about a subordinate → team logs
            $params = ['type' => 'absent'];
            if ($this->employeeId) {
                $params['user_id'] = $this->employeeId;
            }
            $url = route('admin.team.attendance-logs', $params, false);
        }

        return [
            'type'          => 'absent_marked',
            'employee_name' => $this->employeeName,
            'employee_id'   => $this->employeeId,
            'is_self'       => $this->isSelf,
            'message'       => $message,
            'url'           => $url,
        ];
    }
}
