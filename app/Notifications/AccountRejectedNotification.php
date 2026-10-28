<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountRejectedNotification extends Notification
{
    use Queueable;

    public function __construct() {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = "[Account Rejected] Your account registration request was not approved by HR. You can contact HR or submit a re-activation request from your dashboard.";

        return [
            'type'    => 'account_rejected',
            'title'   => 'Account Rejected',
            'message' => $message,
            'url'     => route('admin.dashboard', [], false),
        ];
    }
}
