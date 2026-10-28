<?php

namespace App\Notifications;

use App\Models\SlackMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SlackOverrideAwaitingApproval extends Notification
{
    use Queueable;

    public function __construct(public SlackMessage $slackMessage, public string $employeeName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tz = 'Asia/Karachi';
        $dateFormatted = $this->slackMessage->message_at->timezone($tz)->format('l, F jS');
        return [
            'type' => 'slack_override_awaiting_approval',
            'slack_message_id' => $this->slackMessage->id,
            'employee_name' => $this->employeeName,
            'employee_id' => $this->slackMessage->mapping?->user_id,
            'message_text' => $this->slackMessage->message_text,
            'message' => "[Session Override] {$this->employeeName}: \"Requested override: {$this->slackMessage->message_text} on {$dateFormatted}\"",
            'url' => route('super-admin.session-management.overrides', [], false),
        ];
    }
}
