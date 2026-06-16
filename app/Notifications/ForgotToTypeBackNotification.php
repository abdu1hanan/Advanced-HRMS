<?php

namespace App\Notifications;

use App\Models\SlackMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForgotToTypeBackNotification extends Notification
{
    use Queueable;

    public function __construct(public SlackMessage $slackMessage, public string $employeeName, public SlackMessage $breakMessage) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tz = 'Asia/Karachi';
        $breakTimeFormatted = $this->breakMessage->message_at->timezone($tz)->format('h:i A');
        $autoTimeFormatted = $this->slackMessage->message_at->timezone($tz)->format('h:i A');

        return [
            'type' => 'forgot_to_type_back',
            'slack_message_id' => $this->slackMessage->id,
            'employee_name' => $this->employeeName,
            'message_text' => $this->slackMessage->message_text,
            'message' => "{$this->employeeName} forgot to type back after break at {$breakTimeFormatted}. An auto-appended back has been pending at {$autoTimeFormatted}.",
            'url' => route('super-admin.session-management.overrides', [], false),
        ];
    }
}
