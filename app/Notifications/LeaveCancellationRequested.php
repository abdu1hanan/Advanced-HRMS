<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveCancellationRequested extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $employeeName,
        public ?string $reason = null,
        public bool $isActionable = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = $notifiable->isSuperAdmin() 
            ? route('super-admin.leave-requests.index') 
            : route('admin.team.leaves.history');

        $dates = $this->leaveRequest->start_date->format('M d, Y') . ($this->leaveRequest->start_date->ne($this->leaveRequest->end_date) ? ' to ' . $this->leaveRequest->end_date->format('M d, Y') : '');
        $reasonStr = $this->reason ? " (Reason: {$this->reason})" : '';

        if ($this->isActionable) {
            $message = "{$this->employeeName} requested cancellation for past/approved leave ({$dates}){$reasonStr}. CEO approval required.";
        } else {
            $message = "{$this->employeeName} requested cancellation for past/approved leave ({$dates}){$reasonStr}. Awaiting CEO review.";
        }

        return [
            'type'             => 'leave_cancellation_requested',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name'    => $this->employeeName,
            'leave_type'       => $this->leaveRequest->leaveType->name,
            'reason'           => $this->reason,
            'message'          => $message,
            'url'              => $url,
        ];
    }
}
