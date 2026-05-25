<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestReviewed extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusText = $this->leaveRequest->status;
        $reviewer = $this->leaveRequest->reviewer;
        
        if ($statusText === 'approved_by_manager') {
            $reviewerName = $reviewer ? $reviewer->name : 'Line Manager';
            if (stripos($reviewerName, 'manager') !== false) {
                $message = "Your leave request was recommended for approval by {$reviewerName}. CEO final approval is pending.";
            } else {
                $message = "Your leave request was recommended for approval by your Line Manager ({$reviewerName}). CEO final approval is pending.";
            }
        } elseif ($statusText === 'approved') {
            if ($reviewer && $reviewer->isSuperAdmin()) {
                $message = "Your leave request was fully approved by the CEO / System Administrator.";
            } else {
                $reviewerName = $reviewer ? $reviewer->name : 'Line Manager';
                if (stripos($reviewerName, 'manager') !== false) {
                    $message = "Your leave request was approved by {$reviewerName}.";
                } else {
                    $message = "Your leave request was approved by your Line Manager ({$reviewerName}).";
                }
            }
        } elseif ($statusText === 'rejected') {
            if ($reviewer && $reviewer->isSuperAdmin()) {
                $message = "Your leave request was rejected by the CEO / System Administrator.";
            } else {
                $reviewerName = $reviewer ? $reviewer->name : 'Line Manager';
                if (stripos($reviewerName, 'manager') !== false) {
                    $message = "Your leave request was rejected by {$reviewerName}.";
                } else {
                    $message = "Your leave request was rejected by your Line Manager ({$reviewerName}).";
                }
            }
        } else {
            $message = "Your leave request was {$statusText}.";
        }

        return [
            'type' => 'leave_reviewed',
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->leaveRequest->status,
            'message' => $message,
            'admin_comment' => $this->leaveRequest->admin_comment,
            'url' => route('employee.dashboard'),
        ];
    }
}
