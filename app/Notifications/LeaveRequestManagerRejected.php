<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestManagerRejected extends Notification
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
        $levelText   = ($this->level > 0 && $this->totalLevels > 0)
            ? " [Level {$this->level} of {$this->totalLevels}]"
            : '';
        $designation = $this->managerDesignation ? " ({$this->managerDesignation})" : '';

        // Determine destination based on user role
        $url = $notifiable->isSuperAdmin()
            ? route('super-admin.leave-requests.index')
            : route('admin.team.leaves');

        $chainSuffix = $notifiable->isSuperAdmin()
            ? "Line manager chain stopped — pending CEO decision."
            : "The chain has stopped.";

        return [
            'type'             => 'leave_manager_rejected',
            'leave_request_id' => $this->leaveRequest->id,
            'level'            => $this->level,
            'total_levels'     => $this->totalLevels,
            'message'          => "{$this->leaveRequest->user->name}'s leave was rejected by {$this->managerName}{$designation}{$levelText}. {$chainSuffix}",
            'url'              => $url,
        ];
    }
}
