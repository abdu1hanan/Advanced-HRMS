<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RemoteDeletedNotification extends Notification
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
            $message = "[Remote Cancelled] {$this->employeeName}: \"You cancelled your remote work declaration on {$this->dateFormatted}.\"";
        } else {
            $message = "[Remote Cancelled] {$this->employeeName}: \"Cancelled remote work declaration on {$this->dateFormatted}.\"";
        }

        // isSelf = true  → the notifiable themselves cancelled
        // isSelf = false → a subordinate cancelled; notifiable is the line manager or Super Admin
        if ($this->isSelf) {
            $url = route('admin.leaves.record', ['type' => 'remote'], false);
        } else {
            $params = ['type' => 'remote'];
            if ($this->employeeId) {
                $params['user_id'] = $this->employeeId;
            }
            $url = route('admin.team.attendance-logs', $params, false);
        }

        return [
            'type'          => 'remote_deleted',
            'employee_name' => $this->employeeName,
            'employee_id'   => $this->employeeId,
            'is_self'       => $this->isSelf,
            'message'       => $message,
            'url'           => $url,
        ];
    }
}
