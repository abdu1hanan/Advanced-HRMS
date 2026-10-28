<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification
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

        $user = $this->leaveRequest->user;
        $designation = $user->designation?->name ? " ({$user->designation->name})" : '';
        $leaveTypeName = $this->leaveRequest->leaveType->name;

        $message = "{$user->name}{$designation} submitted a {$leaveTypeName} leave request.";

        return [
            'type' => 'leave_submitted',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $user->name,
            'leave_type' => $leaveTypeName,
            'start_date' => $this->leaveRequest->start_date->format('Y-m-d'),
            'end_date' => $this->leaveRequest->end_date->format('Y-m-d'),
            'message' => $message,
            'url' => $url,
        ];
    }
}
