<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HolidayDeclaredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $holidayName,
        public string $dateRange,
        public int $daysCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $days = $this->daysCount === 1 ? '1 day' : "{$this->daysCount} days";

        $message = "[Holiday] System: \"Company holiday '{$this->holidayName}' declared for {$this->dateRange} ({$days}).\"";

        return [
            'type'    => 'holiday_declared',
            'message' => $message,
            'url'     => route($notifiable->dashboardRoute(), [], false),
        ];
    }
}
