<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestCancelled extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $cancelledBy,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = $notifiable->isSuperAdmin() 
            ? route('super-admin.leave-requests.history') 
            : route('admin.team.leaves.history');

        $dates = $this->leaveRequest->start_date->format('M d, Y') . ($this->leaveRequest->start_date->ne($this->leaveRequest->end_date) ? ' to ' . $this->leaveRequest->end_date->format('M d, Y') : '');
        $reasonStr = $this->reason ? " (Reason: {$this->reason})" : '';

        $userName = $this->leaveRequest->user->name ?? 'Employee';
        $leaveTypeName = $this->leaveRequest->leaveType->name ?? 'Leave';
        $chainEntries = $this->leaveRequest->approvalChain;
        $target = (int) $this->leaveRequest->approval_chain_target;

        $oldStatus = $this->leaveRequest->change_request['old_status'] ?? $this->leaveRequest->status;
        $isFullyApproved = ($oldStatus === LeaveRequest::STATUS_APPROVED);
        $isApprovedByManager = ($oldStatus === 'approved_by_manager');

        $lastAction = $chainEntries->sortBy('level')->last(fn($e) => in_array($e->action, ['approved', 'rejected']));

        $stagePhrase = '';
        if ($isFullyApproved) {
            if ($notifiable->isSuperAdmin()) {
                $isNotifiableReviewer = ($this->leaveRequest->reviewer_id === $notifiable->id);
                $stagePhrase = $isNotifiableReviewer ? " which you fully approved" : " which was fully approved";
            } else {
                $myEntry = $chainEntries->firstWhere('approver_id', $notifiable->id);
                if ($myEntry && $myEntry->action === 'approved') {
                    $stagePhrase = " which you approved [Level {$myEntry->level} of {$target}] and was fully approved";
                } else {
                    $stagePhrase = " which was fully approved";
                }
            }
        } elseif ($isApprovedByManager) {
            if ($notifiable->isSuperAdmin()) {
                $stagePhrase = " after manager approval [Level {$target} of {$target}] while pending your decision";
            } else {
                $myEntry = $chainEntries->firstWhere('approver_id', $notifiable->id);
                if ($myEntry && $myEntry->action === 'approved') {
                    $stagePhrase = " which you approved [Level {$myEntry->level} of {$target}] while pending CEO approval";
                } else {
                    $stagePhrase = " while pending CEO approval";
                }
            }
        } elseif ($lastAction) {
            $level = (int) $lastAction->level;
            $approverName = $lastAction->approver?->name ?? 'Line Manager';
            $isNotifiableApprover = ($lastAction->approver_id === $notifiable->id);

            if ($lastAction->action === 'approved') {
                if ($isNotifiableApprover) {
                    $stagePhrase = " which you approved [Level {$level} of {$target}]";
                } else {
                    $stagePhrase = " after {$approverName} approved [Level {$level} of {$target}]";
                }
            } elseif ($lastAction->action === 'rejected') {
                if ($isNotifiableApprover) {
                    $stagePhrase = " which you rejected [Level {$level} of {$target}]";
                } else {
                    $stagePhrase = " after {$approverName} rejected [Level {$level} of {$target}]";
                }
            }
        } else {
            $stagePhrase = " before a decision was made";
        }

        $leaveWord = str_ends_with(strtolower($leaveTypeName), 'leave') ? '' : ' leave';
        $message = "{$userName} cancelled their {$leaveTypeName}{$leaveWord} ({$dates}){$stagePhrase}{$reasonStr}.";

        return [
            'type'             => 'leave_cancelled',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name'    => $userName,
            'leave_type'       => $leaveTypeName,
            'start_date'       => $this->leaveRequest->start_date->format('Y-m-d'),
            'end_date'         => $this->leaveRequest->end_date->format('Y-m-d'),
            'reason'           => $this->reason,
            'message'          => $message,
            'url'              => $url,
        ];
    }
}
