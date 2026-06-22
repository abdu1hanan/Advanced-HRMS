<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestChangeRequested extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = $notifiable->isSuperAdmin() 
            ? route('super-admin.leave-requests.index') 
            : route('admin.team.leaves');

        $message = "{$this->leaveRequest->user->name} has requested changes to their leave request ({$this->leaveRequest->leaveType->name}).";

        return [
            'type' => 'leave_change_requested',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $this->leaveRequest->user->name,
            'leave_type' => $this->leaveRequest->leaveType->name,
            'message' => $message,
            'url' => $url,
        ];
    }
}
