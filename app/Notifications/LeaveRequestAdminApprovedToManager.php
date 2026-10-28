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
        public string $previousStatus = 'approved_by_manager',
        public ?string $superAdminDesignation = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $actionDesc = 'that was in your approval chain';
        if ($this->previousStatus === 'approved_by_manager') {
            $actionDesc = 'that you previously approved';
        } elseif ($this->previousStatus === 'rejected_by_manager') {
            $actionDesc = 'that you previously rejected';
        } elseif ($this->previousStatus === 'reviewed') {
            $actionDesc = 'that you previously reviewed';
        }

        $desigStr = $this->superAdminDesignation ? " ({$this->superAdminDesignation})" : '';

        $url = $notifiable->isSuperAdmin()
            ? route('super-admin.leave-requests.index')
            : route('admin.team.leaves.history');

        return [
            'type' => 'leave_admin_approved_to_manager',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "{$this->superAdminName}{$desigStr} approved the leave request for {$this->leaveRequest->user->name} {$actionDesc}.",
            'url' => $url,
        ];
    }
}
