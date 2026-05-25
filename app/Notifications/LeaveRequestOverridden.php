<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestOverridden extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest, public string $superAdminName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_overridden',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "{$this->superAdminName} rejected the leave request for {$this->leaveRequest->user->name} that you previously approved.",
            'url' => route('admin.team.leaves.history'),
        ];
    }
}
