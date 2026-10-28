<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountActivationRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $user
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $roleName = $this->user->role?->name ? ucfirst($this->user->role->name) : 'User';
        $message = "[Activation Requested] {$this->user->name} ({$this->user->email}, Role: {$roleName}) has requested account activation review.";

        return [
            'type'          => 'account_activation_requested',
            'title'         => 'Account Activation Requested',
            'message'       => $message,
            'url'           => route('super-admin.users.index', ['show' => 'pending'], false),
            'employee_name' => $this->user->name,
            'employee_id'   => $this->user->id,
        ];
    }
}
