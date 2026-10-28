<?php

namespace App\Jobs;

use App\Models\Role;
use App\Models\SlackMessage;
use App\Models\User;
use App\Notifications\BreakGracePeriodNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BreakGracePeriodAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new grace period alert job instance.
     *
     * @param  string  $slackUserId     The Slack user ID
     * @param  int     $breakMessageId  The DB id of the break message
     * @param  string  $breakMessageAt  ISO8601 timestamp of the break message
     * @param  string  $dateStr         'Y-m-d' in Asia/Karachi
     * @param  string  $userName        Display name of the employee
     */
    public function __construct(
        public string $slackUserId,
        public int    $breakMessageId,
        public string $breakMessageAt,
        public string $dateStr,
        public string $userName,
        public bool   $isMidBreak = true,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tz = 'Asia/Karachi';

        // 1. Check if the break message still exists
        $breakMessage = SlackMessage::find($this->breakMessageId);
        if (!$breakMessage) {
            return;
        }

        // 2. Check if the employee already returned or signed out before the grace period was entered
        $hasEndingEventAfterBreak = SlackMessage::where('slack_user_id', $this->slackUserId)
            ->where('message_at', '>', $breakMessage->message_at)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                  ->orWhere('approval_status', '!=', 'rejected');
            })
            ->where(function ($q) {
                $q->where('message_text', 'like', 'back%')
                  ->orWhere('message_text', 'like', '%signout%')
                  ->orWhere('message_text', 'like', '%sign out%')
                  ->orWhere('message_text', 'like', '%signin%')
                  ->orWhere('message_text', 'like', '%sign in%');
            })
            ->exists();

        if ($hasEndingEventAfterBreak) {
            // Employee already returned or signed out before entering grace period
            return;
        }

        // 3. Format time and date strings
        $breakTimeLocal = Carbon::parse($this->breakMessageAt)->timezone($tz);
        $breakTimeFormatted = $breakTimeLocal->format('h:i A');
        $dateFormatted = $breakTimeLocal->format('l, F jS');

        $officialBalanceMin = (int) round(SlackMessage::getBreakBalanceSeconds() / 60);
        $gracePeriodMin     = (int) round(SlackMessage::getBreakGracePeriodSeconds() / 60);

        if ($gracePeriodMin <= 0) {
            return; // No grace period configured
        }

        if ($this->isMidBreak) {
            $inAppMsg = "[Grace Period Alert] {$this->userName} has reached their {$officialBalanceMin}-minute daily break allowance mid-break and entered the {$gracePeriodMin}-minute grace period buffer for break sent at {$breakTimeFormatted} on {$dateFormatted}.";
        } else {
            $inAppMsg = "[Grace Period Alert] {$this->userName} started a new break at {$breakTimeFormatted} on {$dateFormatted} utilizing their remaining {$gracePeriodMin}-minute grace period buffer after having exhausted their {$officialBalanceMin}-minute daily allowance.";
        }

        // 4. Send In-App notification to Super Admin (HR) users only
        $superAdmins = User::whereHas('role', function ($q) {
            $q->where('name', Role::SUPER_ADMIN);
        })->get();

        foreach ($superAdmins as $admin) {
            $alreadyNotified = DB::table('notifications')
                ->where('notifiable_id', $admin->id)
                ->where('data->slack_message_id', $breakMessage->id)
                ->where('data->type', 'break_grace_period')
                ->exists();

            if (!$alreadyNotified) {
                $admin->notify(new BreakGracePeriodNotification(
                    $breakMessage,
                    $this->userName,
                    $inAppMsg,
                    $officialBalanceMin,
                    $gracePeriodMin,
                    false
                ));
            }
        }
    }
}
