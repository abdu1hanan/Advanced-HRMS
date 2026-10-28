<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\CompanyHoliday;
use App\Models\LeaveRequest;
use App\Models\SlackMessage;
use App\Services\SlackAlertService;
use App\Http\Controllers\SuperAdmin\SessionManagementController;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendIncompleteCycleReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:incomplete-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Slack DM reminders to users with incomplete cycles from the previous day';

    /**
     * Execute the console command.
     */
    public function handle(SlackAlertService $slackAlertService): int
    {
        $now = Carbon::now('Asia/Karachi');
        $previousDay = $now->copy()->subDay();
        $previousDayStr = $previousDay->format('Y-m-d');

        // 1. Skip weekends
        if ($previousDay->isWeekend()) {
            $this->info("Yesterday was a weekend ({$previousDay->format('l')}). Skipping alerts.");
            return 0;
        }

        // Fetch all active employees and line managers (non-super-admins)
        $users = User::whereHas('role', function($query) {
            $query->where('name', '!=', \App\Models\Role::SUPER_ADMIN);
        })->get();

        $sessionController = app(SessionManagementController::class);
        $alertCount = 0;

        foreach ($users as $user) {
            // 2. Skip if yesterday was an applicable holiday for this user
            if (CompanyHoliday::isHolidayForUser($user, $previousDayStr)) {
                continue;
            }

            // 3. Skip approved leaves
            $isLeave = LeaveRequest::where('user_id', $user->id)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('start_date', '<=', $previousDayStr)
                ->where('end_date', '>=', $previousDayStr)
                ->exists();

            if ($isLeave) {
                continue;
            }

            // 4. Skip if no Slack mapping
            $mapping = $user->slackMapping;
            if (!$mapping) {
                continue;
            }
            $slackUserId = $mapping->slack_user_id;

            // Fetch yesterday's Slack messages with a 24h forward lookahead to support overnight shifts
            $startUTC = $previousDay->copy()->startOfDay()->timezone('UTC');
            $endUTC = $previousDay->copy()->endOfDay()->timezone('UTC');
            $forwardLookaheadUTC = $endUTC->copy()->addHours(24);

            // 5. Skip if user already submitted a manual override fixing yesterday's session
            $hasPendingOverride = SlackMessage::where('slack_user_id', $slackUserId)
                ->where('approval_status', 'pending')
                ->where(function($q) use ($startUTC, $endUTC) {
                    $q->whereBetween('message_at', [$startUTC, $endUTC])
                      ->orWhereBetween('override_at', [$startUTC, $endUTC]);
                })
                ->exists();

            if ($hasPendingOverride) {
                continue;
            }

            $messages = SlackMessage::where('slack_user_id', $slackUserId)
                ->whereBetween('message_at', [$startUTC, $forwardLookaheadUTC])
                ->orderBy('message_at', 'asc')
                ->get();

            if ($messages->isEmpty()) {
                continue; // Only alert if they did work yesterday but left it incomplete
            }

            // Reconstruct sessions
            $sessionsByDate = $sessionController->reconstructSessions(
                $messages,
                $previousDay->copy()->startOfDay(),
                $previousDay->copy()->endOfDay(),
                'Asia/Karachi'
            );

            $daySessions = $sessionsByDate[$previousDayStr] ?? [];
            if (empty($daySessions)) {
                continue;
            }

            // Check if any session is incomplete (out is null OR was auto-appended as a sign out)
            $hasIncomplete = false;
            $incompleteCheckInTime = null;
            foreach ($daySessions as $session) {
                $isVirtualSignOut = ($session['is_virtual_out'] ?? false) && ($session['virtual_out_reason'] === 'Auto-Appended Sign Out');
                if ($session['out'] === null || $isVirtualSignOut) {
                    $hasIncomplete = true;
                    $incompleteCheckInTime = $session['in'];
                    break;
                }
            }

            if ($hasIncomplete && $incompleteCheckInTime) {
                $checkInLocal = $incompleteCheckInTime->timezone('Asia/Karachi');
                $timeFormatted = $checkInLocal->format('h:i A');
                $dateFormatted = $checkInLocal->format('l, M d, Y');
                $slackAlertService->sendDM(
                    $slackUserId,
                    "Hey {$user->name}, you forgot to complete your cycle from yesterday. You signed in at {$timeFormatted} on {$dateFormatted}, but did not sign out. Please send the correct format message on Slack to fix it, e.g., 'sign out at 06:00 PM' or similar."
                );

                $user->notify(new \App\Notifications\ForgotToSignOutNotification(
                    $messages->last(),
                    $user->name,
                    "[Forgot Sign Out] You forgot to sign out from your shift on {$dateFormatted} (checked in at {$timeFormatted}).",
                    true
                ));
                $alertCount++;
            }
        }

        $this->info("Successfully sent {$alertCount} incomplete cycle reminders to Slack.");
        return 0;
    }
}