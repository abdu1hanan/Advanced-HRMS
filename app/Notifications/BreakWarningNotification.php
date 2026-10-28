<?php

namespace App\Notifications;

use App\Models\SlackMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BreakWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SlackMessage $slackMessage,
        public string $employeeName,
        public string $notificationMsg,
        public int $minutesRemaining,
        public bool $isSelf = true
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $empId = $this->slackMessage->mapping?->user_id;

        return [
            'type' => 'break_warning',
            'minutes_remaining' => $this->minutesRemaining,
            'slack_message_id' => $this->slackMessage->id,
            'employee_name' => $this->employeeName,
            'employee_id' => $empId,
            'message_text' => $this->slackMessage->message_text,
            'message' => $this->notificationMsg,
            'is_self' => $this->isSelf,
            'url' => route('super-admin.session-management.track-record', $empId ? ['user_id' => $empId] : [], false),
        ];
    }
}
