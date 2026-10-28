<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveCancellationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $reviewerName,
        public ?string $reason = null,
        public ?string $reviewerDesignation = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = match(true) {
            $notifiable->isSuperAdmin() => route('super-admin.leave-requests.history'),
            $notifiable->hasSubordinates() => route('admin.team.leaves.history'),
            default => route('admin.leaves.record'),
        };

        $dates = $this->leaveRequest->start_date->format('M d, Y') . ($this->leaveRequest->start_date->ne($this->leaveRequest->end_date) ? ' to ' . $this->leaveRequest->end_date->format('M d, Y') : '');
        $userName = $this->leaveRequest->user->name ?? 'Employee';
        $leaveTypeName = $this->leaveRequest->leaveType->name ?? 'Leave';
        $desigStr = $this->reviewerDesignation ? " ({$this->reviewerDesignation})" : '';

        $message = "{$this->reviewerName}{$desigStr} rejected {$userName}'s cancellation request for {$leaveTypeName} ({$dates}). The leave remains Fully Approved.";

        return [
            'type'             => 'leave_cancellation_rejected',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name'    => $userName,
            'leave_type'       => $leaveTypeName,
            'message'          => $message,
            'url'              => $url,
        ];
    }
}
