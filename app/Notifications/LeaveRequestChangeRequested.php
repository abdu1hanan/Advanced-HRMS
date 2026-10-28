<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestChangeRequested extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public bool $isActionable = false,
        public int $level = 1,
        public int $totalLevels = 1
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = $notifiable->isSuperAdmin() 
            ? route('super-admin.leave-requests.index') 
            : route('admin.team.leaves');

        $levelStr = ($this->totalLevels > 0) ? " [Level {$this->level} of {$this->totalLevels}]" : '';

        if ($this->leaveRequest->is_admin_marked) {
            if ($this->isActionable) {
                $message = "{$this->leaveRequest->user->name} requested changes on a Super Admin-marked leave. Your review is required.";
            } else {
                $message = "{$this->leaveRequest->user->name} requested changes on a Super Admin-marked leave. Pending CEO review.";
            }
        } else {
            if ($this->isActionable) {
                $message = "{$this->leaveRequest->user->name} submitted an updated leave request{$levelStr}. Your approval is required.";
            } else {
                $message = "{$this->leaveRequest->user->name} updated their leave request. The approval chain has restarted from Level 1.";
            }
        }

        return [
            'type'             => 'leave_change_requested',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name'    => $this->leaveRequest->user->name,
            'leave_type'       => $this->leaveRequest->leaveType->name,
            'message'          => $message,
            'url'              => $url,
        ];
    }
}
