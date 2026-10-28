<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestOverridden extends Notification
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
        $priorWord = 'approved';
        if ($this->previousStatus === 'rejected_by_manager') {
            $priorWord = 'rejected';
        } elseif ($this->previousStatus === 'pending') {
            $priorWord = 'reviewed';
        }

        $desigStr = $this->superAdminDesignation ? " ({$this->superAdminDesignation})" : '';

        $url = $notifiable->isSuperAdmin()
            ? route('super-admin.leave-requests.index')
            : route('admin.team.leaves.history');

        return [
            'type' => 'leave_overridden',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "{$this->superAdminName}{$desigStr} rejected the leave request for {$this->leaveRequest->user->name} that you previously {$priorWord}.",
            'url' => $url,
        ];
    }
}
