<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestManagerRejected extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest, public string $managerName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_manager_rejected',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "{$this->managerName} rejected {$this->leaveRequest->user->name}'s leave request.",
            'url' => route('super-admin.leave-requests.index'),
        ];
    }
}
