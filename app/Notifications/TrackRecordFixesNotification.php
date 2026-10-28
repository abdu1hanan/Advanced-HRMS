<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TrackRecordFixesNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ?int $employeeId = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'track_record_fixes',
            'message' => 'HR made manual fixes for your track record.',
            'is_self' => true,
            'employee_id' => $this->employeeId ?? $notifiable->id,
            'url' => route('admin.track-record', [], false),
        ];
    }
}
