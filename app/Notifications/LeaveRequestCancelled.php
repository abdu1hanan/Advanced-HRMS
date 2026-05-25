<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestCancelled extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest, public string $cancelledBy) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = $notifiable->isSuperAdmin() 
            ? route('super-admin.leave-requests.history') 
            : route('admin.team.leaves.history');

        return [
            'type' => 'leave_cancelled',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $this->leaveRequest->user->name,
            'leave_type' => $this->leaveRequest->leaveType->name,
            'start_date' => $this->leaveRequest->start_date->format('Y-m-d'),
            'end_date' => $this->leaveRequest->end_date->format('Y-m-d'),
            'message' => "{$this->leaveRequest->user->name} cancelled their {$this->leaveRequest->leaveType->name} leave request.",
            'url' => $url,
        ];
    }
}
