<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestChangeProcessed extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest, public string $status, public string $reviewerName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = "Your leave change request has been {$this->status} by {$this->reviewerName}.";

        $url = $notifiable->isAdmin() 
            ? route('admin.leaves.apply') 
            : route('employee.leaves.record');

        return [
            'type' => 'leave_change_processed',
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->status,
            'message' => $message,
            'url' => $url,
        ];
    }
}
