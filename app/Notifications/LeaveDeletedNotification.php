<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $leaveTypeName,
        public string $dateRangeFormatted,
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
            $message = "[Leave Cancelled] Your marked leave ({$this->leaveTypeName}) for {$this->dateRangeFormatted} was removed by HR.";
            // All staff use admin routes
            $url = route('admin.leaves.record', ['type' => 'leaves'], false);
        } else {
            $message = "[Leave Cancelled] {$this->employeeName}'s marked leave ({$this->leaveTypeName}) for {$this->dateRangeFormatted} was removed by HR.";
            $params = ['type' => 'leave'];
            if ($this->employeeId) {
                $params['user_id'] = $this->employeeId;
            }
            $url = route('admin.team.attendance-logs', $params, false);
        }

        return [
            'type'          => 'leave_deleted',
            'employee_name' => $this->employeeName,
            'employee_id'   => $this->employeeId,
            'is_self'       => $this->isSelf,
            'message'       => $message,
            'url'           => $url,
        ];
    }
}
