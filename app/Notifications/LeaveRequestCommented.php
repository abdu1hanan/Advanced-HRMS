<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestCommented extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest, public string $commenterName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_commented',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "{$this->commenterName} commented on the leave request.",
            'url' => route('employee.dashboard'),
        ];
    }
}
