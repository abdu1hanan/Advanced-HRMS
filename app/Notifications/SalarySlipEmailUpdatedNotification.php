<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalarySlipEmailUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public ?string $oldEmail,
        public ?string $newEmail,
        public string $changedByName,
        public bool $changedByHr = false,
        public ?int $employeeId = null,
        public string $fallbackLoginEmail = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $newDisplay = $this->newEmail ?: "login email ({$this->fallbackLoginEmail})";

        if ($this->changedByHr) {
            // Notification sent to the Employee
            $message = "[Salary Slip Email] {$this->changedByName} (HR) updated your salary slip recipient email to '{$newDisplay}'.";
            $url = route('profile.edit', [], false);
        } else {
            // Notification sent to HR / Super Admin
            $message = "[Salary Slip Email] {$this->employeeName} updated their salary slip recipient email to '{$newDisplay}'.";
            $url = $this->employeeId
                ? route('super-admin.salary-slips.index', ['user_id' => $this->employeeId], false)
                : route('super-admin.salary-slips.index', [], false);
        }

        return [
            'type'        => 'salary_slip_email_updated',
            'title'       => 'Salary Slip Email Updated',
            'message'     => $message,
            'url'         => $url,
            'employee_id' => $this->employeeId,
        ];
    }
}
