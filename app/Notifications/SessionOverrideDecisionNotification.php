<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SessionOverrideDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $messageText,
        public string $dateFormatted,
        public bool $isApproved,
        public ?int $employeeId = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $statusText = $this->isApproved ? 'approved' : 'rejected';
        $message = "HR {$statusText} your session override request for \"{$this->messageText}\"";
        if ($this->dateFormatted) {
            $message .= " on {$this->dateFormatted}";
        }
        $message .= ".";

        return [
            'type' => 'session_override_decision',
            'message' => $message,
            'is_approved' => $this->isApproved,
            'is_self' => true,
            'employee_id' => $this->employeeId ?? $notifiable->id,
            'url' => route('admin.track-record', [], false),
        ];
    }
}
