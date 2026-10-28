<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalarySlipIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $monthName,
        public int $year,
        public string $recipientEmail,
        public ?int $month = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = "[Salary Slip Issued] Your salary slip for {$this->monthName} {$this->year} has been issued and sent to {$this->recipientEmail}.";

        // Clicking the notification takes the employee directly to their Track Record page to view their financial summary
        $url = route('admin.track-record', ['period' => 'this_month'], false);

        return [
            'type'            => 'salary_slip_issued',
            'title'           => 'Salary Slip Issued',
            'message'         => $message,
            'url'             => $url,
            'month'           => $this->month,
            'year'            => $this->year,
            'recipient_email' => $this->recipientEmail,
        ];
    }
}
