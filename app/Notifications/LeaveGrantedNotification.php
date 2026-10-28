<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $leaveTypeName,
        public string $startFormatted,
        public string $endFormatted,
        public int $daysCount,
        public bool $isSelf = false,
        public ?int $employeeId = null,
        public bool $isConverted = false,
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

        $days = $this->daysCount === 1 ? '1 day' : "{$this->daysCount} days";

        if ($this->isConverted) {
            if ($this->isSelf) {
                $message = "[Leave Converted] Your absent record for {$range} was converted to {$this->leaveTypeName} ({$days}) by HR.";
            } else {
                $message = "[Leave Converted] {$this->employeeName}'s absent record for {$range} was converted to {$this->leaveTypeName} ({$days}) by HR.";
            }
        } else {
            if ($this->isSelf) {
                $message = "[Leave] {$this->employeeName}: \"{$this->leaveTypeName} granted for {$range} ({$days}). Status: Fully Approved.\"";
            } else {
                $message = "[Leave] {$this->employeeName}: \"{$this->leaveTypeName} was granted for {$range} ({$days}).\"";
            }
        }

        // isSelf = true  → the notifiable themselves received the leave
        // isSelf = false → a subordinate received leave; notifiable is always the line manager
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
            'type'          => 'leave_granted',
            'employee_name' => $this->employeeName,
            'employee_id'   => $this->employeeId,
            'is_self'       => $this->isSelf,
            'message'       => $message,
            'url'           => $url,
        ];
    }
}
