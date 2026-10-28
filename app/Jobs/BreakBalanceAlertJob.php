<?php

namespace App\Jobs;

use App\Models\SlackMessage;
use App\Services\SlackAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class BreakBalanceAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  string  $slackUserId     The Slack user ID (not DB user ID)
     * @param  int     $breakMessageId  The DB id of the break message that triggered this job
     * @param  string  $breakMessageAt  ISO8601 timestamp of the break message
     * @param  string  $dateStr         'Y-m-d' in Asia/Karachi of the break day
     * @param  string  $userName        Display name for DM personalisation
     */
    public function __construct(
        public string $slackUserId,
        public int    $breakMessageId,
        public string $breakMessageAt,
        public string $dateStr,
        public string $userName,
    ) {}

    /**
     * Execute the job.
     * This fires at the exact moment the user's remaining break balance would have run out.
     * We check if the break is still open (no 'back', 'signout', or 'signin' received yet). If it is, we send a DM and In-App notification.
     */
    public function handle(SlackAlertService $slackAlertService): void
    {
        $tz = 'Asia/Karachi';

        // ------------------------------------------------------------------
        // 1. Check if the break is still open (no ending event received after it)
        // ------------------------------------------------------------------
        $breakMessage = SlackMessage::find($this->breakMessageId);

        if (!$breakMessage) {
            // Message was deleted - nothing to do
            return;
        }

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
            // Employee already came back, signed out, or signed in before the balance ran out - no alert needed
            return;
        }

        // ------------------------------------------------------------------
        // 2. Compute balance to find the exact split time for the DM & In-App message
        // ------------------------------------------------------------------
        $balanceData = SlackMessage::computeDayBreakBalance($this->slackUserId, $this->dateStr);

        $splitTimeFormatted = null;
        foreach ($balanceData['break_pairs'] as $pair) {
            if ($pair['break_msg_id'] === $this->breakMessageId && $pair['split_time'] !== null) {
                $splitTimeFormatted = Carbon::parse($pair['split_time'])
                    ->timezone($tz)
                    ->format('h:i A');
                break;
            }
        }

        $breakTimeLocal = Carbon::parse($this->breakMessageAt)->timezone($tz);
        $breakTimeFormatted = $breakTimeLocal->format('h:i A');
        $dateFormatted = $breakTimeLocal->format('l, F jS');

        $officialBalanceMin = (int) round(SlackMessage::getBreakBalanceSeconds() / 60);

        // ------------------------------------------------------------------
        // 3. Send the Slack DM with exact backtrack details
        // ------------------------------------------------------------------
        if ($splitTimeFormatted) {
            // Break is still open and balance ran out mid-break
            $slackAlertService->sendDM(
                $this->slackUserId,
                "Hey {$this->userName}, your break started at {$breakTimeFormatted} on {$dateFormatted} has exhausted your daily break balance ({$officialBalanceMin} min). Time since {$splitTimeFormatted} is counted as a sign out. Please send 'back' to resume your session."
            );
            $inAppMsg = "[Break Exceeded] Your break sent at {$breakTimeFormatted} on {$dateFormatted} exhausted your {$officialBalanceMin}-minute daily break balance at {$splitTimeFormatted}. Time since {$splitTimeFormatted} is counted as a sign out.";
        } else {
            // Balance was already zero when the break started (is_fully_session_out = true)
            $slackAlertService->sendDM(
                $this->slackUserId,
                "Hey {$this->userName}, your break sent at {$breakTimeFormatted} on {$dateFormatted} is being treated as a sign out since your daily break balance ({$officialBalanceMin} min) is already exhausted."
            );
            $inAppMsg = "[Break Exceeded] Your break sent at {$breakTimeFormatted} on {$dateFormatted} was treated as a sign out because your {$officialBalanceMin}-minute daily break balance was already exhausted.";
        }

        // ------------------------------------------------------------------
        // 4. Send In-App database notification to the employee
        // ------------------------------------------------------------------
        $breakMessage->loadMissing('mapping.user');
        $targetUser = $breakMessage->mapping?->user;

        if ($targetUser) {
            $alreadyNotified = \Illuminate\Support\Facades\DB::table('notifications')
                ->where('notifiable_id', $targetUser->id)
                ->where('data->slack_message_id', $breakMessage->id)
                ->where('data->type', 'break_exceeded')
                ->exists();

            if (!$alreadyNotified) {
                $targetUser->notify(new \App\Notifications\BreakExceededNotification(
                    $breakMessage,
                    $this->userName,
                    $inAppMsg,
                    true
                ));
            }
        }
    }
}