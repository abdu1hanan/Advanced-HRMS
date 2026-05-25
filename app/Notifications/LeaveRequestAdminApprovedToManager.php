<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestAdminApprovedToManager extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest, 
        public string $superAdminName, 
        public string $previousStatus = 'approved_by_manager'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $priorWord = 'reviewed';
        if ($this->previousStatus === 'approved_by_manager') {
            $priorWord = 'approved';
        } elseif ($this->previousStatus === 'rejected_by_manager') {
            $priorWord = 'rejected';
        }

        return [
            'type' => 'leave_admin_approved_to_manager',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "{$this->superAdminName} approved the leave request for {$this->leaveRequest->user->name} that you previously {$priorWord}.",
            'url' => route('admin.team.leaves.history'),
        ];
    }
}
