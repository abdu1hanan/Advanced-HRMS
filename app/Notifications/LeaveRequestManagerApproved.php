<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestManagerApproved extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $managerName,
        public string $managerDesignation = '',
        public int $level = 0,
        public int $totalLevels = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $levelText = ($this->level > 0 && $this->totalLevels > 0)
            ? " [Level {$this->level} of {$this->totalLevels}]"
            : '';

        $designation = $this->managerDesignation ? " ({$this->managerDesignation})" : '';

        $isComplete = $this->leaveRequest->status === 'approved_by_manager' || $this->leaveRequest->chainComplete();

        $pending = ($this->level > 0 && $this->level < $this->totalLevels && !$isComplete)
            ? ' Next level approval is pending.'
            : ' Pending CEO final approval.';

        $url = $notifiable->isSuperAdmin()
            ? route('super-admin.leave-requests.index')
            : route('admin.team.leaves');

        return [
            'type'             => 'leave_manager_approved',
            'leave_request_id' => $this->leaveRequest->id,
            'level'            => $this->level,
            'total_levels'     => $this->totalLevels,
            'message'          => "{$this->leaveRequest->user->name}'s leave was approved by {$this->managerName}{$designation}{$levelText}.{$pending}",
            'url'              => $url,
        ];
    }
}
