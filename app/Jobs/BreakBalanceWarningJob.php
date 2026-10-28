<?php

namespace App\Jobs;

use App\Models\SlackMessage;
use App\Notifications\BreakWarningNotification;
use App\Services\SlackAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BreakBalanceWarningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new warning job instance.
     *
     * @param  string  $slackUserId       The Slack user ID
     * @param  int     $breakMessageId    The DB id of the break message
     * @param  string  $breakMessageAt    ISO8601 timestamp of the break message
     * @param  string  $dateStr           'Y-m-d' in Asia/Karachi
     * @param  string  $userName          Display name for DM
     * @param  int     $minutesRemaining  Warning threshold (e.g. 10 or 5 minutes remaining)
     */
    public function __construct(
        public string $slackUserId,
        public int    $breakMessageId,
        public string $breakMessageAt,
        public string $dateStr,
        public string $userName,
        public int    $minutesRemaining,
    ) {}

    /**
     * Execute the warning job.
     */
    public function handle(SlackAlertService $slackAlertService): void
    {
        $tz = 'Asia/Karachi';

        // 1. Check if the break message still exists
        $breakMessage = SlackMessage::find($this->breakMessageId);
        if (!$breakMessage) {
            return;
        }

        // 2. Check if the employee already returned or signed out before this warning timer fired
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
            // Employee already returned or signed out — no warning needed
            return;
        }

        // 3. Format time and date strings
        $breakTimeLocal = Carbon::parse($this->breakMessageAt)->timezone($tz);
        $breakTimeFormatted = $breakTimeLocal->format('h:i A');
        $dateFormatted = $breakTimeLocal->format('l, F jS');

        $officialBalanceMin = (int) round(SlackMessage::getBreakBalanceSeconds() / 60);

        // 4. Construct warning message text
        if ($this->minutesRemaining === 10) {
            $slackText = "Hey {$this->userName}, you have 10 minutes remaining from your daily break balance ({$officialBalanceMin} min) for your break started at {$breakTimeFormatted} on {$dateFormatted}. Please remember to send 'back' when you return.";
            $inAppMsg = "[Break Warning] You have 10 minutes remaining from your {$officialBalanceMin}-minute daily break balance for your break sent at {$breakTimeFormatted} on {$dateFormatted}.";
        } elseif ($this->minutesRemaining === 5) {
            $slackText = "Hey {$this->userName}, you have 5 minutes remaining from your daily break balance ({$officialBalanceMin} min) for your break started at {$breakTimeFormatted} on {$dateFormatted}. Please send 'back' soon to avoid session deduction.";
            $inAppMsg = "[Break Warning] You have 5 minutes remaining from your {$officialBalanceMin}-minute daily break balance for your break sent at {$breakTimeFormatted} on {$dateFormatted}.";
        } else {
            $slackText = "Hey {$this->userName}, you have {$this->minutesRemaining} minutes remaining from your daily break balance ({$officialBalanceMin} min) for your break started at {$breakTimeFormatted} on {$dateFormatted}.";
            $inAppMsg = "[Break Warning] You have {$this->minutesRemaining} minutes remaining from your {$officialBalanceMin}-minute daily break balance for your break sent at {$breakTimeFormatted} on {$dateFormatted}.";
        }

        // 5. Send the Slack DM
        $slackAlertService->sendDM($this->slackUserId, $slackText);

        // 6. Send the In-App Notification to the user
        $breakMessage->loadMissing('mapping.user');
        $targetUser = $breakMessage->mapping?->user;

        if ($targetUser) {
            $alreadyNotified = DB::table('notifications')
                ->where('notifiable_id', $targetUser->id)
                ->where('data->slack_message_id', $breakMessage->id)
                ->where('data->type', 'break_warning')
                ->where('data->minutes_remaining', $this->minutesRemaining)
                ->exists();

            if (!$alreadyNotified) {
                $targetUser->notify(new BreakWarningNotification(
                    $breakMessage,
                    $this->userName,
                    $inAppMsg,
                    $this->minutesRemaining,
                    true
                ));
            }
        }
    }
}
