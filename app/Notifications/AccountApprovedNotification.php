<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountApprovedNotification extends Notification
{
    use Queueable;

    public function __construct() {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = "[Account Approved] Your account has been approved by HR! Full portal access to System Hierarchy, Attendance Logs, and Leaves has been unlocked.";

        return [
            'type'    => 'account_approved',
            'title'   => 'Account Approved',
            'message' => $message,
            'url'     => route('admin.dashboard', [], false),
        ];
    }
}
