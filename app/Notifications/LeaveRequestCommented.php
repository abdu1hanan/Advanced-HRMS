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
        if ($notifiable->id === $this->leaveRequest->user_id) {
            $message = "{$this->commenterName} left a comment on your leave request.";
            $url = $notifiable->isAdmin() ? route('admin.leaves.apply') : route('employee.dashboard');
        } else {
            $message = "{$this->commenterName} left a comment on the leave request for {$this->leaveRequest->user->name}.";
            $url = $notifiable->isSuperAdmin() 
                ? route('super-admin.leave-requests.index') 
                : route('admin.team.leaves');
        }

        return [
            'type' => 'leave_commented',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => $message,
            'url' => $url,
        ];
    }
}
