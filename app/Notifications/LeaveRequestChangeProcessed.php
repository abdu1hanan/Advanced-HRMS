<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestChangeProcessed extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest, 
        public string $status, 
        public string $reviewerName,
        public ?string $reviewerDesignation = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $desigStr = $this->reviewerDesignation ? " ({$this->reviewerDesignation})" : '';

        $isCancel = !empty($this->leaveRequest->change_request['request_cancellation']);
        if ($isCancel) {
            $message = $this->status === 'rejected'
                ? "Your leave cancellation request was rejected by {$this->reviewerName}{$desigStr}. The leave remains Fully Approved."
                : "Your leave cancellation request was approved by {$this->reviewerName}{$desigStr}. The leave has been cancelled.";
        } else {
            if ($this->status === 'approved') {
                $message = "Your requested changes to your leave were approved by {$this->reviewerName}{$desigStr}.";
            } else {
                $message = "Your requested changes to your leave were rejected by {$this->reviewerName}{$desigStr}. Your original approved leave remains active.";
            }
        }

        // Sent to the leave requester — route to their own leave record (role-aware)
        // All staff use admin routes
        $url = route('admin.leaves.record', ['type' => 'leaves']);


        return [
            'type' => 'leave_change_processed',
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->status,
            'message' => $message,
            'url' => $url,
        ];
    }
}
