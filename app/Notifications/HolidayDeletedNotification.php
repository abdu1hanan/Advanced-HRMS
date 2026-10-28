<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HolidayDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $holidayName,
        public string $dateRange,
        public int $daysCount,
        public ?string $customMessage = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $days = $this->daysCount === 1 ? '1 day' : "{$this->daysCount} days";

        $message = $this->customMessage ?? "[Holiday Cancelled] System: \"Company holiday '{$this->holidayName}' for {$this->dateRange} ({$days}) has been cancelled by HR.\"";

        return [
            'type'    => 'holiday_deleted',
            'message' => $message,
            'url'     => route($notifiable->dashboardRoute(), [], false),
        ];
    }
}
