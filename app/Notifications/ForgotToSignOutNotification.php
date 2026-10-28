<?php

namespace App\Notifications;

use App\Models\SlackMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForgotToSignOutNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SlackMessage $slackMessage,
        public string $employeeName,
        public string $notificationMsg,
        public bool $isSelf = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $empId = $this->slackMessage->mapping?->user_id;
        $url = ($notifiable instanceof User && $notifiable->isSuperAdmin())
            ? route('super-admin.session-management.track-record', $empId ? ['user_id' => $empId] : [], false)
            : route('admin.track-record', [], false);

        return [
            'type' => 'forgot_to_sign_out',
            'slack_message_id' => $this->slackMessage->id,
            'employee_name' => $this->employeeName,
            'employee_id' => $empId,
            'message_text' => $this->slackMessage->message_text,
            'message' => $this->notificationMsg,
            'is_self' => $this->isSelf,
            'url' => $url,
        ];
    }
}
