<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the NEXT manager in the approval chain when the previous level has approved.
 */
class LeaveRequestChainApprovalRequired extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $approvedByName,
        public string $approvedByDesignation,
        public int $level,
        public int $totalLevels,
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

        $levelText = "Level {$this->level} of {$this->totalLevels}";

        return [
            'type'              => 'chain_approval_required',
            'leave_request_id'  => $this->leaveRequest->id,
            'employee_name'     => $this->leaveRequest->user->name,
            'leave_type'        => $this->leaveRequest->leaveType->name,
            'start_date'        => $this->leaveRequest->start_date->format('Y-m-d'),
            'end_date'          => $this->leaveRequest->end_date->format('Y-m-d'),
            'approved_by'       => $this->approvedByName,
            'approved_by_designation' => $this->approvedByDesignation,
            'level'             => $this->level,
            'total_levels'      => $this->totalLevels,
            'message'           => "{$this->leaveRequest->user->name}'s leave was approved by {$this->approvedByName} ({$this->approvedByDesignation}) [{$levelText}]. Your approval is now required.",
            'url'               => $url,
        ];
    }
}
