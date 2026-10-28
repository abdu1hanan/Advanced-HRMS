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
        $user = $this->leaveRequest->user;
        $designation = $user->designation?->name ? " ({$user->designation->name})" : '';
        $leaveTypeName = $this->leaveRequest->leaveType->name;

        $message = "{$user->name}{$designation} submitted a {$leaveTypeName} leave request but has no immediate boss assigned.";

        return [
            'type' => 'leave_no_manager',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $user->name,
            'leave_type' => $leaveTypeName,
            'message' => $message,
            'url' => route('super-admin.leave-requests.index'),
        ];
    }
}
