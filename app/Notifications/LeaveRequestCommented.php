<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestCommented extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest, 
        public string $commenterName,
        public ?string $commenterDesignation = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $desigStr = $this->commenterDesignation ? " ({$this->commenterDesignation})" : '';

        if ($notifiable->id === $this->leaveRequest->user_id) {
            $message = "{$this->commenterName}{$desigStr} left a comment on your leave request.";
            // It's the requester — take them to their own leave record
            // All staff use admin routes
            $url = route('admin.leaves.record', ['type' => 'leaves']);

        } else {
            $message = "{$this->commenterName}{$desigStr} left a comment on the leave request for {$this->leaveRequest->user->name}.";
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
