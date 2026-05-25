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
        $url = route('employee.dashboard');
        if ($notifiable->isSuperAdmin()) {
            $url = route('super-admin.leave-requests.index');
        } elseif ($notifiable->isAdmin()) {
            $url = route('admin.team.leaves');
        }

        return [
            'type' => 'leave_commented',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "{$this->commenterName} left a comment on the leave request for {$this->leaveRequest->user->name}.",
            'url' => $url,
        ];
    }
}
