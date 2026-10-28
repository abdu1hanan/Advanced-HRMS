<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveTypeChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $oldLeaveTypeName,
        public string $newLeaveTypeName,
        public string $startFormatted,
        public string $endFormatted,
        public $daysCount,
        public bool $isSelf = false,
        public ?int $employeeId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $range = $this->startFormatted === $this->endFormatted
            ? $this->startFormatted
            : "{$this->startFormatted} – {$this->endFormatted}";

        $days = $this->daysCount == 1 ? '1 day' : "{$this->daysCount} days";

        if ($this->isSelf) {
            $message = "[Leave Details Updated] Your leave for {$range} ({$days}) was updated by HR.";
        } else {
            $message = "[Leave Details Updated] {$this->employeeName}'s leave for {$range} ({$days}) was updated by HR.";
        }

        if ($this->isSelf) {
            if ($notifiable->isSuperAdmin()) {
                $url = route('super-admin.dashboard', [], false);
            } else {
                // All staff use admin routes
                $url = route('admin.leaves.record', ['type' => 'leaves'], false);
            }
        } else {
            $params = [];
            if ($this->employeeId) {
                $params['user_id'] = $this->employeeId;
            }
            if ($notifiable->isSuperAdmin()) {
                $url = route('super-admin.leave-requests.history', $params, false);
            } else {
                $url = route('admin.team.leaves.history', $params, false);
            }
        }

        return [
            'type'          => 'leave_type_changed',
            'employee_name' => $this->employeeName,
            'employee_id'   => $this->employeeId,
            'is_self'       => $this->isSelf,
            'message'       => $message,
            'url'           => $url,
        ];
    }
}
