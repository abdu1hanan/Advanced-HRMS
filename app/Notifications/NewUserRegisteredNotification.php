<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification
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
        $dept = $this->user->department?->name ?? 'No Department';
        $message = "[Account Approval Required] {$this->user->name} ({$this->user->email}) has registered as {$roleName} ({$dept}) and requires your approval.";

        return [
            'type'          => 'account_approval_required',
            'title'         => 'Account Approval Required',
            'message'       => $message,
            'url'           => route('super-admin.users.index', ['show' => 'pending'], false),
            'employee_name' => $this->user->name,
            'employee_id'   => $this->user->id,
        ];
    }
}
