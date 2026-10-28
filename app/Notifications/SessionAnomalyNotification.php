<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SessionAnomalyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $notificationMsg,
        public ?int $employeeId = null,
        public string $anomalyType = 'session_anomaly',
        public bool $isSelf = true
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = ($notifiable instanceof User && $notifiable->isSuperAdmin())
            ? route('super-admin.session-management.track-record', $this->employeeId ? ['user_id' => $this->employeeId] : [], false)
            : route('admin.track-record', [], false);

        return [
            'type' => $this->anomalyType,
            'employee_id' => $this->employeeId,
            'message' => $this->notificationMsg,
            'is_self' => $this->isSelf,
            'url' => $url,
        ];
    }
}
