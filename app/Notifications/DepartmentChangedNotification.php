<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DepartmentChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public string $oldDepartmentName,
        public string $newDepartmentName,
        public ?int $employeeId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = "[Department Updated] {$this->employeeName} has updated their department from '{$this->oldDepartmentName}' to '{$this->newDepartmentName}'.";

        if ($notifiable->isSuperAdmin() && $this->employeeId) {
            $url = route('super-admin.users.edit', $this->employeeId, false);
        } else {
            $url = route('dashboard', [], false);
        }

        return [
            'type'        => 'department_changed',
            'title'       => 'Department Updated',
            'message'     => $message,
            'url'         => $url,
            'employee_id' => $this->employeeId,
        ];
    }
}
