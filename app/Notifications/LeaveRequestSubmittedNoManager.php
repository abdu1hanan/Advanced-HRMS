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
        $department = \App\Models\Department::where('head_user_id', $this->leaveRequest->user_id)->first();
        if ($department) {
            $message = "Leave requested by Head of {$department->name} ({$this->leaveRequest->user->name}).";
        } else {
            $message = "{$this->leaveRequest->user->name} submitted leave but has no line manager assigned.";
        }

        return [
            'type' => 'leave_no_manager',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $this->leaveRequest->user->name,
            'message' => $message,
            'url' => route('super-admin.leave-requests.index'),
        ];
    }
}
