<?php

namespace App\Notifications;

use App\Models\SlackMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForgotToSignOutNotification extends Notification
{
    use Queueable;

    public function __construct(public SlackMessage $slackMessage, public string $employeeName, public string $notificationMsg) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'forgot_to_sign_out',
            'slack_message_id' => $this->slackMessage->id,
            'employee_name' => $this->employeeName,
            'message_text' => $this->slackMessage->message_text,
            'message' => $this->notificationMsg,
            'url' => route('super-admin.session-management.track-record', [], false),
        ];
    }
}
