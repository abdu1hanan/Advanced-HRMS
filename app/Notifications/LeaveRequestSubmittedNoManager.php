<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmittedNoManager extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_no_manager',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $this->leaveRequest->user->name,
            'message' => "{$this->leaveRequest->user->name} submitted leave but has no line manager assigned.",
            'url' => route('super-admin.leave-requests.index'),
        ];
    }
}
