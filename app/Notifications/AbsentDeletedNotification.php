<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AbsentDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $dateFormatted,
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
            $message = "[Absent Cancelled] Your absent record for {$this->dateFormatted} was removed by HR.";
            // All staff use admin routes
            $url = route('admin.leaves.record', ['type' => 'absent'], false);
        } else {
            $message = "[Absent Cancelled] {$this->employeeName}'s absent record for {$this->dateFormatted} was removed by HR.";
            $params = ['type' => 'absent'];
            if ($this->employeeId) {
                $params['user_id'] = $this->employeeId;
            }
            $url = route('admin.team.attendance-logs', $params, false);
        }

        return [
            'type'          => 'absent_deleted',
            'employee_name' => $this->employeeName,
            'employee_id'   => $this->employeeId,
            'is_self'       => $this->isSelf,
            'message'       => $message,
            'url'           => $url,
        ];
    }
}
