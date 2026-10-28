<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public ?string $actorName = null,
        public ?string $actorDesignation = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusText = $this->leaveRequest->status;
        $progress   = (int) $this->leaveRequest->approval_chain_progress;
        $target     = (int) $this->leaveRequest->approval_chain_target;
        $hasChain   = $target > 0;

        // Use the acting manager's name if passed, else fallback to reviewer or immediate manager
        $reviewerUser = $this->leaveRequest->reviewer;
        $reviewerName = $this->actorName ?: ($reviewerUser?->name ?: ($this->leaveRequest->user->manager?->name ?: ''));
        
        $actorDesig = $this->actorDesignation;
        if (!$actorDesig) {
            $actorDesig = $reviewerUser?->designation?->name 
                ?? ($reviewerUser?->special_order === 1 ? 'CEO' : ($reviewerUser?->special_order === 2 ? 'CTO' : ($reviewerUser?->special_order === 3 ? 'HR Manager' : null)));
        }
        $designationStr = $actorDesig ? " ({$actorDesig})" : '';

        if ($statusText === 'approved_by_manager' || ($statusText === 'pending' && $progress > 0)) {
            $levelText = "Level {$progress} of {$target}";
            if ($progress < $target && $statusText === 'pending') {
                $message = $reviewerName
                    ? "Your leave was approved by {$reviewerName}{$designationStr} ({$levelText}). Awaiting next level manager approval."
                    : "Your leave was approved at {$levelText}. Awaiting next level manager approval.";
            } else {
                $message = $reviewerName
                    ? "Your leave was approved by {$reviewerName}{$designationStr} ({$levelText}). Pending CEO final approval."
                    : "Your leave was approved at {$levelText}. Pending CEO final approval.";
            }
        } elseif ($statusText === 'approved') {
            $message = $reviewerName 
                ? "Your leave request was fully approved by {$reviewerName}{$designationStr}." 
                : "Your leave request was fully approved.";
        } elseif ($statusText === 'rejected_by_manager') {
            $rejLevel  = $this->leaveRequest->rejectionLevel();
            $levelText = ($hasChain && $rejLevel > 0) ? " (Level {$rejLevel} of {$target})" : '';
            $message   = $reviewerName
                ? "Your leave request was rejected by {$reviewerName}{$designationStr}{$levelText}."
                : "Your leave request was rejected by Line Manager{$levelText}.";
        } elseif ($statusText === 'rejected') {
            $message = $reviewerName
                ? "Your leave request was rejected by {$reviewerName}{$designationStr}."
                : "Your leave request was rejected.";
        } else {
            $message = "Your leave request is currently pending review.";
        }

        $url = route('admin.leaves.record', ['type' => 'leaves']);

        return [
            'type'             => 'leave_reviewed',
            'leave_request_id' => $this->leaveRequest->id,
            'status'           => $this->leaveRequest->status,
            'message'          => $message,
            'admin_comment'    => $this->leaveRequest->admin_comment,
            'url'              => $url,
        ];
    }
}
